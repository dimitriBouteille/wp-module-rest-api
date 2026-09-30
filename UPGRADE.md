# Upgrade guide

## From 1.x to 2.0

Version 2.0 contains a small number of breaking changes. Most projects only need to check the
[error response format](#wp_error-responses-no-longer-contain-additional_errors) and, if they rely on it, the
[debug output](#stack-traces-now-require-wp_debug). No public API has been removed.

### Breaking changes

#### `WP_Error` responses no longer contain `additional_errors`

When a route handler returns a `\WP_Error`, only the **first** error is now sent in the response.
The `additional_errors` key has been removed.

Before (1.x):

```json
{
  "error": {
    "code": "invalid-email",
    "message": "The email is invalid.",
    "data": null,
    "additional_errors": [
      { "code": "invalid-name", "message": "The name is required.", "data": null }
    ]
  }
}
```

After (2.0):

```json
{
  "error": {
    "code": "invalid-email",
    "message": "The email is invalid.",
    "data": []
  }
}
```

`data` is now always an array: error data that is not an array is replaced by `[]`.

**How to upgrade:** if your clients read `additional_errors`, return a single `\WP_Error` that carries every
message in its data, or throw a `RouteException` with `additionalData`:

```php
use Dbout\WpRestApi\Exceptions\RouteException;

throw new RouteException(
    message: 'The submitted data is invalid.',
    errorCode: 'invalid-data',
    httpStatusCode: 400,
    additionalData: [
        'errors' => [
            ['code' => 'invalid-email', 'message' => 'The email is invalid.'],
            ['code' => 'invalid-name', 'message' => 'The name is required.'],
        ],
    ],
);
```

#### Stack traces now require `WP_DEBUG`

In 1.x, `RouteLoaderOptions::$debug = true` was enough to add the stack trace (`data.exception`) to error
responses. In 2.0, the trace is only added when **both** `RouteLoaderOptions::$debug` and the `WP_DEBUG`
constant are `true`.

With `debug` enabled, the original message of unexpected errors is still returned; only the trace is affected.

**How to upgrade:** if you need traces in a development environment, set `define('WP_DEBUG', true);`.
Never enable `RouteLoaderOptions::$debug` in production.

#### `RestWrapper` protected methods changed

This only affects projects that **extend** `Dbout\WpRestApi\Wrappers\RestWrapper`.

| 1.x                                                                  | 2.0                                                                               |
|----------------------------------------------------------------------|-----------------------------------------------------------------------------------|
| `onError(\Exception $exception)`                                     | `onError(\Throwable $exception)`                                                  |
| `collectDependencies(\ReflectionMethod $method, \WP_REST_Request $request)` | `collectDependencies(string $className, string $methodName, \WP_REST_Request $request)` |
| `castRequestArgument(\ReflectionNamedType $type, mixed $value)`      | `castRequestArgument(string $typeName, mixed $value)`                             |
| `buildResponseError(RouteException $exception)`                      | Removed, see `ErrorFormatterInterface`                                            |
| `parseErrorToRestResponse(\WP_Error $error, int $httpCode)`          | Removed, replaced by `onWpError()` and `buildErrorResponse()`                     |

**How to upgrade:** update your method signatures. If you overrode `buildResponseError()` or
`parseErrorToRestResponse()` to customize the error body, implement an error formatter instead (see
[Custom error format](#custom-error-format)).

### Deprecations

#### Directory-based `RouteLoader`

Discovering routes from a directory (`new RouteLoader($directory)`) is deprecated. It still works in 2.0 and will be
removed in a future major version.

Use `NamespaceRouteLoader`, which reads your Composer PSR-4 mapping instead of parsing PHP files:

```php
// Before
use Dbout\WpRestApi\RouteLoader;

(new RouteLoader(__DIR__ . '/src/Routes'))->register();

// After
use Dbout\WpRestApi\NamespaceRouteLoader;

(new NamespaceRouteLoader('App\\Routes\\'))->register();
```

`NamespaceRouteLoader` accepts one namespace or an array of namespaces, and the same `RouteLoaderOptions` as
`RouteLoader`. The namespace must be covered by a PSR-4 entry of your `composer.json` autoload.

### Other changes

These changes do not require any action, but may change how your application behaves.

- **Route cache format:** routes are now cached as JSON instead of PHP `serialize()`. Existing cache entries are
  ignored and rebuilt automatically on the next request.
- **Uncacheable callbacks:** when a `permissionCallback` (or a `#[Param]` sanitize/validate callback) is a `Closure`,
  the route cache is skipped and an `E_USER_NOTICE` is raised. Use a class name, a function name or a
  `[class, method]` array to keep the cache.
- **PHP errors are handled:** `\Error` (e.g. `TypeError`) thrown by a handler is now caught and returned as a
  `500` JSON error, like exceptions.
- **Function name permission callbacks:** string callbacks that are not classes (e.g. `'is_user_logged_in'`) now
  work as expected.

### New features

#### Request argument schema with `#[Param]`

Handler parameters can declare a WordPress argument schema. WordPress then validates and sanitizes the request
before your handler runs.

```php
use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Param;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;

#[Route('app/v1', '/articles')]
class ArticleRoute
{
    #[Action(Method::GET)]
    public function list(
        #[Param(required: true, description: 'Page number')]
        int $page,
        #[Param(name: 'order_by')]
        ?SortDirection $sort = null,
    ): \WP_REST_Response {
        // ...
    }
}
```

`type`, `enum` (for a `BackedEnum`) and a default `sanitize_callback` are inferred from the PHP type when they are
not set. Handlers without `#[Param]` work as before.

#### Custom error format

The error response body can be changed with `RouteLoaderOptions::$errorFormatter`. Version 2.0 ships an
[RFC 7807](https://www.rfc-editor.org/rfc/rfc7807) formatter (`application/problem+json`):

```php
use Dbout\WpRestApi\ErrorFormat\ProblemJsonFormatter;
use Dbout\WpRestApi\NamespaceRouteLoader;
use Dbout\WpRestApi\RouteLoaderOptions;

(new NamespaceRouteLoader(
    'App\\Routes\\',
    new RouteLoaderOptions(errorFormatter: new ProblemJsonFormatter()),
))->register();
```

The default formatter (`DefaultFormatter`) keeps the `{"error": {"code", "message", "data"}}` shape. You can write
your own by implementing `Dbout\WpRestApi\ErrorFormat\ErrorFormatterInterface`.
