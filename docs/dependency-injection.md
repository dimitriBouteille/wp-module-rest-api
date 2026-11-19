# Dependency injection

By default, the classes you give by name are instantiated without arguments on each request: controllers, permission classes, `#[Resolve]` resolvers and middlewares. To inject a repository, a logger or a mailer in their constructor, plug a [PSR-11](https://www.php-fig.org/psr/psr-11/) container.

## Configuring the container

Pass any PSR-11 container in [`RouteLoaderOptions`](configuration.md):

```php
use Dbout\WpRestApi\NamespaceRouteLoader;
use Dbout\WpRestApi\RouteLoaderOptions;

$options = new RouteLoaderOptions(
    container: $container,
);

(new NamespaceRouteLoader('App\\Routes\\', $options))->register();
```

Your classes can then declare their dependencies:

```php
use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;

#[Route('app/v1', '/documents')]
class DocumentController
{
    public function __construct(
        private readonly DocumentRepository $documents,
    ) {
    }

    #[Action(Method::GET)]
    public function list(): array
    {
        return $this->documents->findAll();
    }
}
```

### Example with PHP-DI

[PHP-DI](https://php-di.org/) (`composer require php-di/php-di`) builds classes from their constructor types, without configuration:

```php
use DI\ContainerBuilder;

$container = (new ContainerBuilder())
    ->addDefinitions([
        DocumentRepository::class => fn () => new DocumentRepository($GLOBALS['wpdb']),
    ])
    ->build();
```

With autowiring, PHP-DI provides every existing class, so all your controllers, permissions, resolvers and middlewares go through it.

### Example with Symfony

With the [Symfony DependencyInjection](https://symfony.com/doc/current/components/dependency_injection.html) component, services are private by default: the container does not provide them by class name. Make the classes used by your routes public:

```yaml
# config/services.yaml
services:
    _defaults:
        autowire: true

    App\Routes\:
        resource: '../src/Routes/'
        public: true
```

## Which classes go through the container

| Class | Declared with |
|---|---|
| Controllers | `#[Route]` |
| Permission classes | `permissionCallback` of `#[Route]` / `#[Action]`, see [Permissions](permissions.md#custom-permission) |
| Resolvers | `#[Resolve]`, see [Routes and actions](routes.md#custom-resolvers-with-resolve) |
| Middlewares | `#[Middleware]` and the class names of `RouteLoaderOptions::$middlewares`, see [Middlewares](middlewares.md) |

For each of them, the module asks the container first:

1. when the container has the class (`has()` returns `true`), the instance it returns is used;
2. otherwise, the class is instantiated without arguments, as without a container.

Internal services of the module (loaders, error formatter, cache) do not go through the container: configure them in `RouteLoaderOptions`.

## Things to know

- **Errors are found when routes are loaded.** A class with a required constructor argument that the container does not provide throws an `ApiException` when the routes are discovered, with the class and the action that uses it. Routes read from the [cache](configuration.md#cache) are not checked again: if the container changes afterwards, the same message is returned as an error response on the request.
- **The container decides of the lifetime.** Most containers return the same instance on each call. This is fine for controllers, but a middleware or a resolver that keeps a state between calls shares it with every request handled by the same PHP process.
- **The container is not stored in the route cache.** Changing it does not require a cache rebuild.
