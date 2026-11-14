# Routes and actions

A route is a class with a `#[Route]` attribute. Each public method with an `#[Action]` attribute handles one or several HTTP methods on that route.

## `#[Route]`

```php
#[Route(
    namespace: 'app/v1',
    route: '/documents/{id:\d+}',
    permissionCallback: null, // optional, see Permissions
)]
```

| Argument             | Description                                                                                                              |
|----------------------|--------------------------------------------------------------------------------------------------------------------------|
| `namespace`          | The first segment after `/wp-json/`, usually your vendor name and a version: `app/v1`.                                   |
| `route`              | The path. Placeholders (`{id:\d+}`) become request parameters, see [Path placeholders](#path-placeholders). |
| `permissionCallback` | Default permission for every action of the route. See [Permissions](permissions.md).                                    |

The final URL is `/wp-json/{namespace}{route}`, for example `/wp-json/app/v1/documents/18`.

## Path placeholders

Use placeholders in `#[Route]` and `#[Action(path: …)]` to declare path parameters:

| Placeholder         | Matches                     | Compiled to             |
|---------------------|-----------------------------|-------------------------|
| `{id}`              | any segment, up to the next `/` | `(?P<id>[^/]+)`     |
| `{id:\d+}`          | digits only                 | `(?P<id>\d+)`           |
| `{slug:[a-z0-9-]+}` | your own regex              | `(?P<slug>[a-z0-9-]+)`  |

```php
#[Route('app/v1', '/users/{userId:\d+}/posts/{slug}')]
class UserPostController
{
    #[Action(Method::GET)]
    public function get(int $userId, string $slug): \WP_REST_Response
    {
        // GET /wp-json/app/v1/users/7/posts/hello-world
    }
}
```

- The name is the request parameter name, so it is [injected](#argument-injection) into the argument with the same name. It must be a valid PHP variable name: `{document-id}` throws an `ApiException` when routes are loaded.
- The regex can contain braces: `{code:[A-Z]{3}}`.
- Raw WordPress regex keeps working: `/documents/(?P<id>\d+)` is the same as `/documents/{id:\d+}`.

> 💡 Without a regex, `{id}` accepts any value: `/documents/abc` reaches your action. Add a regex such as `{id:\d+}` to let WordPress return a `404` for invalid values.

## `#[Action]`

```php
#[Action(
    methods: Method::GET,
    permissionCallback: null, // optional, overrides the route permission
    path: null,               // optional, appended to the route path
)]
```

Available methods: `Method::GET`, `Method::POST`, `Method::PUT`, `Method::PATCH` and `Method::DELETE` (`Dbout\WpRestApi\Enums\Method`).

An action can handle several methods:

```php
#[Action([Method::POST, Method::PUT])]
public function save(\WP_REST_Request $request): \WP_REST_Response
{
    // ...
}
```

> ⚠️ A method can only be used once per path. Declaring two `GET` actions on the same path throws an `ApiException` when routes are loaded.

## Several paths in one class

A resource usually needs two paths: the collection (`/documents`) and the item (`/documents/18`). Use the `path` argument of `#[Action]` to serve both from one class. It is appended to the `#[Route]` path:

```php
#[Route('app/v1', '/documents')]
class DocumentController
{
    #[Action(Method::GET)]
    public function list(): \WP_REST_Response
    {
        // GET /wp-json/app/v1/documents
    }

    #[Action(Method::POST)]
    public function create(\WP_REST_Request $request): \WP_REST_Response
    {
        // POST /wp-json/app/v1/documents
    }

    #[Action(Method::GET, path: '/{id:\d+}')]
    public function get(int $id): \WP_REST_Response
    {
        // GET /wp-json/app/v1/documents/18
    }

    #[Action([Method::PUT, Method::PATCH], path: '/{id:\d+}')]
    public function update(int $id, \WP_REST_Request $request): \WP_REST_Response
    {
        // PUT or PATCH /wp-json/app/v1/documents/18
    }

    #[Action(Method::DELETE, path: '/{id:\d+}', permissionCallback: IsAdministrator::class)]
    public function delete(int $id): \WP_REST_Response
    {
        // DELETE /wp-json/app/v1/documents/18, administrators only
    }
}
```

- Actions with the same final path are registered as one WordPress route.
- The two parts are joined with a single slash: `'/{id:\d+}'` and `'{id:\d+}'` give the same path. As in WordPress, leading and trailing slashes do not matter.
- The namespace and the default permission still come from `#[Route]`.

## Argument injection

Instead of reading `$request->get_param()` by hand, declare the parameters you need as method arguments. The module matches each argument **by name** with the request parameters (URL groups, query string, body).

```php
#[Route('app/v1', '/documents/{id:\d+}')]
class DocumentController
{
    #[Action(Method::GET)]
    public function get(int $id, string $format = 'json'): \WP_REST_Response
    {
        // GET /wp-json/app/v1/documents/18?format=xml
        // $id === 18, $format === 'xml'
    }
}
```

Rules:

- The value is cast to the declared type: `int`, `float`, `string`, `bool`. A `bool` follows WordPress rules: `false`, `0` and an empty string give `false`.
- A backed enum argument receives the matching case. An unknown value returns a `400` error with the `invalid-parameter` code and the accepted values.
- An `array` argument receives an array: a string such as `1,2` is split like WordPress does for the `array` type, and gives `['1', '2']`.
- Other types (classes…) receive the raw value, except `\WP_REST_Request` and `\WP_User`, see below.
- Arguments **without a type** are never injected.
- If the parameter is missing from the request:
    - an argument with a default value keeps it;
    - a nullable argument without default value (`?int $id`) receives `null`;
    - otherwise, the request fails with a `400` error and the `missing-parameter` code, before your action runs.
- To use a different name in the request, see [`#[Param(name: ...)]`](parameters.md#renaming-a-parameter).

### Getting the request object

Type an argument as `\WP_REST_Request` to receive the full request (headers, JSON body, files…). You can combine it with other arguments:

```php
#[Action(Method::POST)]
public function update(int $id, \WP_REST_Request $request): \WP_REST_Response
{
    $payload = $request->get_json_params();
    // ...
}
```

### Getting the current user

Type an argument as `\WP_User` to receive the logged-in user (`wp_get_current_user()`):

```php
#[Action(Method::DELETE)]
public function delete(int $id, \WP_User $user): \WP_REST_Response
{
    // $user is the logged-in user
}
```

- When nobody is logged in, a `\WP_User` argument returns a `401` error with the `not-logged-in` code, before your action runs.
- Use `?\WP_User` to accept anonymous requests: the argument then receives `null`.

> 💡 This is not a permission check: any logged-in user is accepted. Use a [permission](permissions.md) to restrict the action to some roles.

### Custom resolvers with `#[Resolve]`

To build an argument yourself, for example load a post from its ID, create a class implementing `Dbout\WpRestApi\Resolvers\ParameterResolverInterface`:

```php
use Dbout\WpRestApi\Exceptions\NotFoundException;
use Dbout\WpRestApi\Resolvers\ParameterResolverInterface;

class PostResolver implements ParameterResolverInterface
{
    public function resolve(\ReflectionParameter $parameter, \WP_REST_Request $request): \WP_Post
    {
        $post = get_post((int) $request->get_param('id'));
        if (!$post instanceof \WP_Post) {
            throw new NotFoundException('Post');
        }

        return $post;
    }
}
```

Then add `#[Resolve]` on the argument:

```php
use Dbout\WpRestApi\Attributes\Resolve;

#[Route('app/v1', '/posts/{id:\d+}')]
class PostController
{
    #[Action(Method::GET)]
    public function get(#[Resolve(PostResolver::class)] \WP_Post $post): \WP_REST_Response
    {
        // $post is loaded, or a 404 was already returned
    }
}
```

- The resolver receives the argument (`\ReflectionParameter`: name, type, attributes) and the request. Its return value is passed to the action, whatever the argument type.
- `#[Resolve]` wins over every other rule, including the request parameter with the same name.
- Throw a `RouteException` to return an error, see [Error handling](error-handling.md).
- The resolver class is checked when routes are loaded: a class that does not exist or does not implement the interface throws an `ApiException`. It is instantiated without arguments.

## Return value

An action can return:

- a `\WP_REST_Response`, sent as is: use it to set the status code or headers;
- an array, a scalar, `null` or a `JsonSerializable` object: it becomes the body of a `200` response, like a native WordPress callback;
- a `\WP_Error`: it is converted to the error format, and its `status` data sets the status code, see [Error handling](error-handling.md#returning-a-wp_error).

```php
#[Action(Method::GET)]
public function get(int $id): array
{
    return ['id' => $id, 'title' => get_the_title($id)];
}
```

To return an error, you can also throw a `RouteException`, see [Error handling](error-handling.md).

> ⚠️ A value that cannot be sent as JSON (a closure, a resource) returns a `500` error. The detail is shown in [debug mode](configuration.md#debug).

Next: [Request parameters](parameters.md).
