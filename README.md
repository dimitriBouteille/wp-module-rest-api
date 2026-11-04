# WordPress Rest API

[![GitHub Release](https://img.shields.io/github/v/release/dimitriBouteille/wp-module-rest-api)](https://github.com/dimitriBouteille/wp-module-rest-api/releases) [![tests](https://img.shields.io/github/actions/workflow/status/dimitriBouteille/wp-module-rest-api/tests.yml?label=tests)](https://github.com/dimitriBouteille/wp-module-rest-api/actions/workflows/tests.yml) [![Packagist Downloads](https://img.shields.io/packagist/dt/dbout/wp-module-rest-api?color=yellow)](https://packagist.org/packages/dbout/wp-module-rest-api)

Declare your [WordPress REST API](https://developer.wordpress.org/rest-api/) routes with **PHP 8 attributes**, in plain classes, instead of piling up `register_rest_route()` calls.

```php
#[Route('app/v1', '/documents/(?P<id>\d+)')]
class DocumentController
{
    #[Action(Method::GET)]
    public function get(int $id): \WP_REST_Response
    {
        // $id comes straight from the URL, already cast to int
    }
}
```

## Why?

With native WordPress, every endpoint means a `register_rest_route()` call, a callback that reads `$request->get_param()` by hand, and error handling to write yourself. Once you have more than a few routes, it gets hard to keep organized.

**Before**, with native WordPress:

```php
add_action('rest_api_init', function () {
    register_rest_route('app/v1', '/documents/(?P<id>\d+)', [
        [
            'methods' => 'GET',
            'callback' => function (\WP_REST_Request $request) {
                $document = get_post((int) $request->get_param('id'));
                if (!$document instanceof \WP_Post) {
                    return new \WP_Error('not-found', 'Document not found.', ['status' => 404]);
                }

                return new \WP_REST_Response(['id' => $document->ID, 'title' => $document->post_title]);
            },
            'permission_callback' => '__return_true',
        ],
        [
            'methods' => 'DELETE',
            'callback' => function (\WP_REST_Request $request) {
                // ...
            },
            'permission_callback' => fn () => current_user_can('administrator'),
        ],
    ]);
});
```

**After**, with this module:

```php
namespace App\Routes;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;
use Dbout\WpRestApi\Exceptions\NotFoundException;
use Dbout\WpRestApi\Permissions\IsAdministrator;

#[Route('app/v1', '/documents/(?P<id>\d+)')]
class DocumentController
{
    #[Action(Method::GET)]
    public function get(int $id): \WP_REST_Response
    {
        $document = get_post($id);
        if (!$document instanceof \WP_Post) {
            throw new NotFoundException('Document');
        }

        return new \WP_REST_Response(['id' => $document->ID, 'title' => $document->post_title]);
    }

    #[Action(Method::DELETE, permissionCallback: IsAdministrator::class)]
    public function delete(int $id): \WP_REST_Response
    {
        // ...
    }
}
```

## Features

- **Attribute-based routing**: `#[Route]` on the class, `#[Action]` on each method, one or several HTTP methods per action.
- **Argument injection**: URL and request parameters are passed as method arguments and cast to the declared type (`int`, `string`, `float`, `bool`, backed enums). Type a parameter as `\WP_REST_Request` to get the full request.
- **Parameter schema**: describe arguments with `#[Param]` (required, default, enum, description, sanitize and validate callbacks). They are registered as WordPress route `args`.
- **Permissions**: built-in role checks (`IsAdministrator`, `IsEditor`, `IsAuthor`, `IsContributor`, `IsSubscriber`), your own `PermissionInterface` classes, or any callable. They can be set on the route or on each action.
- **Error handling**: throw a `RouteException` (or `NotFoundException`) and it becomes a clean JSON error response with the right HTTP status. The [RFC 7807](https://www.rfc-editor.org/rfc/rfc7807) `application/problem+json` format is available through `ProblemJsonFormatter`.
- **Route discovery**: routes are found from your Composer PSR-4 namespaces. No manual registration.
- **Cache**: plug any PSR-6 cache pool to skip route discovery in production.

## Installation

Requirements: PHP >= 8.1 and [Composer](https://getcomposer.org/). The other requirements are the same as for [WordPress](https://wordpress.org/about/requirements/).

```bash
composer require dbout/wp-module-rest-api
```

> 💡 This module fits well with a Composer-based WordPress setup such as [Bedrock](https://roots.io/bedrock/), [Themosis](https://framework.themosis.com/) or [Wordplate](https://github.com/wordplate/wordplate#readme).

## Quick start

**1. Create a route class** in a namespace autoloaded by Composer (PSR-4), for example `App\Routes\DocumentController` (see above).

**2. Register your routes**, for example in a mu-plugin or in your theme's `functions.php`:

```php
require __DIR__ . '/vendor/autoload.php';

use Dbout\WpRestApi\NamespaceRouteLoader;

(new NamespaceRouteLoader('App\\Routes\\'))->register();
```

`register()` hooks into `rest_api_init` for you. You can pass an array of namespaces.

**3. Call your API**:

```bash
curl https://example.com/wp-json/app/v1/documents/42
```

### Options

```php
use Dbout\WpRestApi\ErrorFormat\ProblemJsonFormatter;
use Dbout\WpRestApi\NamespaceRouteLoader;
use Dbout\WpRestApi\RouteLoaderOptions;

$options = new RouteLoaderOptions(
    cache: $psr6CachePool,                       // Any Psr\Cache\CacheItemPoolInterface
    debug: false,                                // Never enable it in production
    errorFormatter: new ProblemJsonFormatter(),  // RFC 7807 error responses
);

(new NamespaceRouteLoader('App\\Routes\\', $options))->register();
```

Stack traces are added to error responses only when both `debug` and the `WP_DEBUG` constant are `true`.

## Documentation

- The full documentation is in the [wiki](https://github.com/dimitriBouteille/wp-module-rest-api/wiki).
- Upgrading from 1.x? Read the [upgrade guide](UPGRADE.md).
- To learn how the WordPress REST API itself works, read the [official documentation](https://developer.wordpress.org/rest-api/).

## Contributing

We encourage you to contribute to this repository, so everyone can benefit from new features, bug fixes, and any other improvements. Have a look at our [contributing guidelines](CONTRIBUTING.md) to find out how to raise a pull request.

## License

This package is released under the [MIT license](LICENSE).
