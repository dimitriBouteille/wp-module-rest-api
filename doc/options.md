# Route Loader Options

The [RouteLoader](../src/RouteLoader.php) takes 2 arguments:

- The path or paths that contain the routes
- A [RouteLoaderOptions](../src/RouteLoaderOptions.php) object that contains options

```php
<?php

use Dbout\WpRestApi\RouteLoader;
use Dbout\WpRestApi\RouteLoaderOptions;

$options =  new RouteLoaderOptions(...);
$loader = new RouteLoader(
    __DIR__ . '/src/Api/Routes',
    $options
);

$loader->register();
```

## Cache

To work, the module will automatically search for all classes that contain routes, this process can take time if there are a lot of files since the files will be read in order to extract the information on the routes.

In order to avoid this processing at each process, it can be interesting to activate the cache especially on the production environment. By default, the module does not use any cache system, so **you can use any cache system** that implements the [PSR-6](https://www.php-fig.org/psr/psr-6/) standard.

Here is an example with [Symfony cache](https://symfony.com/doc/current/components/cache.html#cache-contracts) :

```php
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Dbout\WpRestApi\RouteLoaderOptions;

$cache = new FilesystemAdapter(
    directory: 'your_cache_path'
);

$options = new RouteLoaderOptions(
    cache: $cache,
);
```

By default, the `wp_autoloader_routes` cache key is used, you can change the key by passing the `cacheKey` option.

```php
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Dbout\WpRestApi\RouteLoaderOptions;

$cache = new FilesystemAdapter(
    directory: 'your_cache_path'
);

$options = new RouteLoaderOptions(
    cache: $cache,
    cacheKey: 'my_cache_key'
);
```