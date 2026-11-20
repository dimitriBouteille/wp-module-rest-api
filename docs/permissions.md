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

#[Route('app/v1', '/admin/documents/{id:\d+}', permissionCallback: IsAdministrator::class)]
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

#[Route('app/v1', '/documents/{id:\d+}', permissionCallback: IsAuthor::class)]
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

## Making an action public

An action without `permissionCallback` inherits the route permission: setting `permissionCallback: null` does the same. To make an action public on a protected route, use `IsPublic`:

```php
use Dbout\WpRestApi\Permissions\IsAdministrator;
use Dbout\WpRestApi\Permissions\IsPublic;

#[Route('app/v1', '/admin/status', permissionCallback: IsAdministrator::class)]
class StatusController
{
    #[Action(Method::GET, permissionCallback: IsPublic::class)]
    public function get(): \WP_REST_Response
    {
        // Everyone, logged in or not
    }

    #[Action(Method::POST)]
    public function update(): \WP_REST_Response
    {
        // Administrators only (route permission)
    }
}
```

In short:

| `permissionCallback` on `#[Action]` | Permission used        |
|-------------------------------------|------------------------|
| omitted or `null`                   | the route permission   |
| `IsPublic::class`                   | none, everyone         |
| any other value                     | that value             |

## Built-in permissions

All in the `Dbout\WpRestApi\Permissions` namespace:

| Permission                         | Check                                               |
|------------------------------------|-----------------------------------------------------|
| `IsPublic`                         | none: allows everyone                               |
| `IsLoggedIn`                       | `is_user_logged_in()`                               |
| `new HasCapability('edit_posts')`  | `current_user_can('edit_posts')`                    |
| `new HasRole('editor')`            | the user has the `editor` role on the site          |
| `new AnyOf(…)`                     | at least one of the given permissions               |
| `new AllOf(…)`                     | all the given permissions                           |
| `IsAdministrator`                  | `current_user_can('administrator')`                 |
| `IsEditor`                         | `current_user_can('editor')`                        |
| `IsAuthor`                         | `current_user_can('author')`                        |
| `IsContributor`                    | `current_user_can('contributor')`                   |
| `IsSubscriber`                     | `current_user_can('subscriber')`                    |

Permissions without arguments are given by class name (`IsLoggedIn::class`) or as an object (`new IsLoggedIn()`). Permissions with arguments are given as an object: PHP 8.1 accepts `new` in attribute arguments.

> 💡 For an external client (mobile app, headless front…), the user must be authenticated, for example with [application passwords](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/). Requests from the WordPress admin need the `X-WP-Nonce` header.

## Checking a capability

WordPress [recommends](https://developer.wordpress.org/plugins/users/roles-and-capabilities/) checking capabilities rather than roles: they keep working with custom roles, multisite Super Admins and plugins that change roles. Use `HasCapability`:

```php
use Dbout\WpRestApi\Permissions\HasCapability;

#[Route('app/v1', '/settings', permissionCallback: new HasCapability('manage_options'))]
class SettingsController
{
    // ...
}
```

Extra arguments are passed to `current_user_can()`, for meta capabilities that need an object ID:

```php
new HasCapability('edit_post', 42)
```

The `IsAdministrator`, `IsEditor`… classes pass a role name to `current_user_can()`. They still work, but prefer `HasCapability` for new code, for example `new HasCapability('manage_options')` instead of `IsAdministrator::class`. To check the role itself, use `new HasRole('editor')`: it only looks at the roles of the user, so an administrator does not have the `editor` role.

## Combining permissions

`AnyOf` allows the request when at least one permission allows it, `AllOf` when all of them do. They accept the same values as `permissionCallback` (class names, permission objects, callables) and can be nested:

```php
use Dbout\WpRestApi\Permissions\AllOf;
use Dbout\WpRestApi\Permissions\AnyOf;
use Dbout\WpRestApi\Permissions\HasCapability;
use Dbout\WpRestApi\Permissions\HasRole;
use Dbout\WpRestApi\Permissions\IsLoggedIn;

#[Route('app/v1', '/reports', permissionCallback: new AnyOf(
    new HasRole('editor'),
    new HasCapability('manage_options'),
))]
class ReportController
{
    #[Action(Method::POST, permissionCallback: new AllOf(
        new IsLoggedIn(),
        SignaturePermission::class,
    ))]
    public function import(): \WP_REST_Response
    {
        // Logged-in users with a valid signature
    }
}
```

Permissions run in order and stop as soon as the result is known. When the request is denied:

- `AllOf` returns the result of the first permission that failed (`false` or its `\WP_Error`);
- `AnyOf` returns the first `\WP_Error` returned by a permission, or `false` (the default WordPress error) when none returned one.

Class names inside `AnyOf` and `AllOf` are created through the [container](dependency-injection.md) like any other permission class.

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

The class is instantiated without arguments, so its constructor must not require any, unless a [container](dependency-injection.md) provides it.

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
