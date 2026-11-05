# Request parameters

The `#[Param]` attribute describes an action argument. It is turned into the [`args` schema](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/#arguments) of the WordPress route, so WordPress validates and sanitizes the value **before** your action is called.

```php
use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Param;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;

#[Route('app/v1', '/articles')]
class ArticleController
{
    #[Action(Method::GET)]
    public function list(
        #[Param(required: true, description: 'Category identifier')]
        int $category,
        #[Param(enum: ['date', 'title'])]
        string $orderBy = 'date',
    ): \WP_REST_Response {
        // ...
    }
}
```

A request without `category` gets a `400` response from WordPress (`rest_missing_callback_param`), and `orderBy=foo` is rejected (`rest_invalid_param`).

> `#[Param]` is optional: arguments are [injected](routes.md#argument-injection) with or without it. Use it when you want WordPress to validate the input.

## Options

| Option             | Description                                                                                         | Default                              |
|--------------------|-----------------------------------------------------------------------------------------------------|--------------------------------------|
| `name`             | Name of the parameter in the request.                                                               | The PHP argument name                |
| `type`             | JSON schema type (`integer`, `number`, `string`, `boolean`, `array`…).                              | Inferred from the PHP type           |
| `required`         | The request fails with `400` if the parameter is missing.                                           | `false`                              |
| `default`          | Value used when the parameter is missing.                                                           | The PHP default value, if any        |
| `enum`             | List of accepted values.                                                                            | The cases of a backed enum type      |
| `description`      | Shown in the REST API index (`/wp-json/`).                                                          | none                                 |
| `sanitizeCallback` | Callable applied to the value.                                                                      | Inferred from the PHP type, see below |
| `validateCallback` | Callable returning `true`, `false` or a `\WP_Error`.                                               | none                                 |

### Type inference

When `type` or `sanitizeCallback` are not set, they are inferred from the PHP type:

| PHP type     | `type`                | `sanitizeCallback`      |
|--------------|-----------------------|-------------------------|
| `int`        | `integer`             | none                    |
| `float`      | `number`              | none                    |
| `bool`       | `boolean`             | `rest_sanitize_boolean` |
| `string`     | `string`              | `sanitize_text_field`   |
| `array`      | `array`               | none                    |
| Backed enum  | `integer` or `string` | none, `enum` is set with the cases |

A nullable type (`?int`) also accepts `null`.

Without `sanitizeCallback`, WordPress validates and casts the value from `type`: `?offset=-5` gives `-5`, and `?offset=abc` is rejected with a `400` error and the `rest_invalid_param` code.

To accept only positive integers, set it explicitly: `#[Param(sanitizeCallback: 'absint')]`. Note that `absint` turns `-5` into `5` and `abc` into `0`, it does not reject them.

## Backed enums

```php
enum Status: string
{
    case Draft = 'draft';
    case Published = 'published';
}

#[Action(Method::GET)]
public function list(
    #[Param]
    Status $status = Status::Published,
): \WP_REST_Response {
    // $status is a Status instance
}
```

WordPress rejects any value that is not `draft` or `published`, and the action receives a `Status` instance.

## Renaming a parameter

```php
#[Action(Method::GET)]
public function list(
    #[Param(name: 'per_page')]
    int $perPage = 10,
): \WP_REST_Response {
    // GET /wp-json/app/v1/articles?per_page=20
}
```

## Custom validation

```php
#[Param(required: true, validateCallback: 'is_email')]
string $email,
```

Attribute arguments must be constant expressions, so closures are not allowed. Use a function name (`'is_email'`) or a static method (`[EmailValidator::class, 'validate']`).

Next: [Permissions](permissions.md).
