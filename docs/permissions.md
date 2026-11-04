# Permissions

Permissions rely on the WordPress [`permission_callback`](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/#permissions-callback): a check run **before** your action, which decides whether the current user can call the endpoint.

> ⚠️ By default, routes and actions have **no permission check**: every endpoint is public.

## Protecting a route

Set `permissionCallback` on `#[Route]` to protect all its actions:

```php
use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;
use Dbout\WpRestApi\Permissions\IsAdministrator;

#[Route('app/v1', '/admin/documents/(?P<id>\d+)', permissionCallback: IsAdministrator::class)]
class AdminDocumentController
{
    #[Action(Method::GET)]
    public function get(int $id): \WP_REST_Response
    {
        // Only administrators
    }

    #[Action(Method::DELETE)]
    public function delete(int $id): \WP_REST_Response
    {
        // Only administrators
    }
}
```

## Protecting an action

Set `permissionCallback` on `#[Action]`. It **replaces** the route permission for this action:

```php
use Dbout\WpRestApi\Permissions\IsAdministrator;
use Dbout\WpRestApi\Permissions\IsAuthor;

#[Route('app/v1', '/documents/(?P<id>\d+)', permissionCallback: IsAuthor::class)]
class DocumentController
{
    #[Action(Method::GET)]
    public function get(int $id): \WP_REST_Response
    {
        // Authors (route permission)
    }

    #[Action(Method::DELETE, permissionCallback: IsAdministrator::class)]
    public function delete(int $id): \WP_REST_Response
    {
        // Administrators only (action permission)
    }
}
```

## Built-in permissions

All in the `Dbout\WpRestApi\Permissions` namespace. Each one calls `current_user_can()` with the matching role:

| Class             | Check                                  |
|-------------------|----------------------------------------|
| `IsAdministrator` | `current_user_can('administrator')`    |
| `IsEditor`        | `current_user_can('editor')`           |
| `IsAuthor`        | `current_user_can('author')`           |
| `IsContributor`   | `current_user_can('contributor')`      |
| `IsSubscriber`    | `current_user_can('subscriber')`       |

> 💡 For an external client (mobile app, headless front…), the user must be authenticated, for example with [application passwords](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/). Requests from the WordPress admin need the `X-WP-Nonce` header.

## Custom permission

Create a class that implements `Dbout\WpRestApi\Permissions\PermissionInterface`. Its `allow()` method receives the request and must:

- return `true` to let the request through;
- return `false` to block it with the default WordPress error (`rest_forbidden`, `401` or `403`);
- return a `\WP_Error` to block it with your own error;
- or throw a `Dbout\WpRestApi\Exceptions\PermissionException`: its message is returned with the `rest_forbidden` code.

Example: verify the HMAC signature of a webhook.

```php
<?php

namespace App\Permissions;

use Dbout\WpRestApi\Permissions\PermissionInterface;

class SignaturePermission implements PermissionInterface
{
    public function allow(\WP_REST_Request $request): bool|\WP_Error
    {
        $signature = (string) $request->get_header('signature');
        $expected = hash_hmac('sha256', $request->get_body(), WEBHOOK_SECRET);

        return hash_equals($expected, $signature);
    }
}
```

```php
#[Route('app/v1', '/webhooks/payment', permissionCallback: SignaturePermission::class)]
class PaymentWebhookController
{
    #[Action(Method::POST)]
    public function execute(\WP_REST_Request $request): \WP_REST_Response
    {
        // Only called when the signature is valid
    }
}
```

> 💡 Use `hash_equals()` to compare signatures: a plain `===` comparison is vulnerable to timing attacks. Keep the secret out of the code (constant in `wp-config.php`, environment variable…).

The class is instantiated without arguments, so its constructor must not require any.

## Callables

`permissionCallback` also accepts any callable that is a constant expression, such as a function name or a static method:

```php
#[Action(Method::GET, permissionCallback: 'is_user_logged_in')]
```

```php
#[Action(Method::GET, permissionCallback: [DocumentPolicy::class, 'canRead'])]
```

The callable receives the `\WP_REST_Request` and follows the same return rules as `allow()`.

Next: [Error handling](error-handling.md).
