# Configuration

The loader accepts a `Dbout\WpRestApi\RouteLoaderOptions` object as its second argument:

```php
use Dbout\WpRestApi\NamespaceRouteLoader;
use Dbout\WpRestApi\RouteLoaderOptions;

$options = new RouteLoaderOptions(
    cache: null,
    cacheKey: 'wp_autoloader_routes',
    debug: false,
    errorFormatter: null,
);

(new NamespaceRouteLoader('App\\Routes\\', $options))->register();
```

| Option           | Type                                  | Default                  | Description                                         |
|------------------|---------------------------------------|--------------------------|-----------------------------------------------------|
| `cache`          | `?Psr\Cache\CacheItemPoolInterface`   | `null`                   | Caches discovered routes, see [Cache](#cache).      |
| `cacheKey`       | `string`                              | `'wp_autoloader_routes'` | Key used to store the routes in the cache.          |
| `debug`          | `bool`                                | `false`                  | Shows unexpected errors, see [Debug](#debug).       |
| `errorFormatter` | `?ErrorFormatterInterface`            | `DefaultFormatter`       | Format of error responses, see [Error handling](error-handling.md#rfc-7807-format). |

## Cache

On every request, the loader scans your route classes and reads their attributes. With many routes, this takes time. In production, cache the result with any [PSR-6](https://www.php-fig.org/psr/psr-6/) cache pool.

Example with [Symfony Cache](https://symfony.com/doc/current/components/cache.html) (`composer require symfony/cache`):

```php
use Dbout\WpRestApi\NamespaceRouteLoader;
use Dbout\WpRestApi\RouteLoaderOptions;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

$options = new RouteLoaderOptions(
    cache: new FilesystemAdapter(directory: WP_CONTENT_DIR . '/cache/rest-api'),
);

(new NamespaceRouteLoader('App\\Routes\\', $options))->register();
```

Things to know:

- The module sets no expiration on the cache item: it lives as long as your pool's default lifetime allows. Clear the cache on each deployment, otherwise new or changed routes are ignored.
- If you register several loaders with the same cache pool, give each one its own `cacheKey`.
- A route whose `permissionCallback` or `#[Param]` callbacks cannot be stored (for example an object) is not cached: the loader triggers a warning and scans the routes on every request.

## Debug

By default, unexpected errors (`500`) return a generic message. In development, enable debug mode to see what went wrong:

```php
$options = new RouteLoaderOptions(
    debug: true,
);
```

- With `debug: true`, the real exception message is returned.
- With `debug: true` **and** `define('WP_DEBUG', true);`, the stack trace is also added in `data.exception`:

```json
{
  "error": {
    "code": "fatal-error",
    "message": "Call to undefined method App\\Repository::find()",
    "data": {
      "exception": "#0 /var/www/src/Routes/DocumentController.php(24): ..."
    }
  }
}
```

> ⚠️ Never enable `debug` in production: messages and stack traces expose internal details of your application. A common pattern is `debug: WP_DEBUG`, or `debug: wp_get_environment_type() === 'development'`.

## Loaders

### `NamespaceRouteLoader` (recommended)

Discovers routes from Composer PSR-4 namespaces. See [Getting started](getting-started.md#2-register-the-loader).

```php
new NamespaceRouteLoader('App\\Routes\\', $options);
new NamespaceRouteLoader(['App\\Routes\\', 'Vendor\\Plugin\\Routes\\'], $options);
```

The Composer autoloader must be loaded before the loader is created.

### `RouteLoader` (deprecated)

Discovers routes by parsing the PHP files of one or several directories. It still works in 2.x but will be removed in a future major version. Migrate to `NamespaceRouteLoader`, see the [upgrade guide](../UPGRADE.md#directory-based-routeloader).

```php
use Dbout\WpRestApi\RouteLoader;

new RouteLoader(__DIR__ . '/src/Routes', $options);
new RouteLoader([__DIR__ . '/src/Routes', __DIR__ . '/theme/api'], $options);
new RouteLoader(__DIR__ . '/src/Modules/*/Api/Routes', $options); // glob patterns
```
