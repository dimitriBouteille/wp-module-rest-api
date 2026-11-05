# Routes and actions

A route is a class with a `#[Route]` attribute. Each public method with an `#[Action]` attribute handles one or several HTTP methods on that route.

## `#[Route]`

```php
#[Route(
    namespace: 'app/v1',
    route: '/documents/(?P<id>\d+)',
    permissionCallback: null, // optional, see Permissions
)]
```

| Argument             | Description                                                                                                              |
|----------------------|--------------------------------------------------------------------------------------------------------------------------|
| `namespace`          | The first segment after `/wp-json/`, usually your vendor name and a version: `app/v1`.                                   |
| `route`              | The path, as accepted by [`register_rest_route()`](https://developer.wordpress.org/reference/functions/register_rest_route/). Named groups (`(?P<id>\d+)`) become request parameters. |
| `permissionCallback` | Default permission for every action of the route. See [Permissions](permissions.md).                                    |

The final URL is `/wp-json/{namespace}{route}`, for example `/wp-json/app/v1/documents/18`.

## `#[Action]`

```php
#[Action(
    methods: Method::GET,
    permissionCallback: null, // optional, overrides the route permission
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

> ⚠️ A method can only be used once per route. Declaring two `GET` actions on the same class throws an `ApiException` when routes are loaded.

## Argument injection

Instead of reading `$request->get_param()` by hand, declare the parameters you need as method arguments. The module matches each argument **by name** with the request parameters (URL groups, query string, body).

```php
#[Route('app/v1', '/documents/(?P<id>\d+)')]
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
- Other types (`array`, classes…) receive the raw value.
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

## Return value

An action should return a `\WP_REST_Response`. You can also:

- throw a `RouteException` to return an error, see [Error handling](error-handling.md);
- return a `\WP_Error`, it is converted to the same error format.

Next: [Request parameters](parameters.md).
