# Getting started

## Requirements

- PHP >= 8.1
- [Composer](https://getcomposer.org/)
- The other requirements are the same as for [WordPress](https://wordpress.org/about/requirements/).

> 💡 This module fits well with a Composer-based WordPress setup such as [Bedrock](https://roots.io/bedrock/), [Themosis](https://framework.themosis.com/) or [Wordplate](https://github.com/wordplate/wordplate#readme).

## Installation

```bash
composer require dbout/wp-module-rest-api
```

## 1. Autoload your routes namespace

Routes are discovered from a namespace declared in the PSR-4 `autoload` section of your `composer.json`:

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "src/"
    }
  }
}
```

Run `composer dump-autoload` after changing this section.

## 2. Register the loader

Add this code early in the WordPress boot, for example in a mu-plugin or at the top of your theme's `functions.php`:

```php
require __DIR__ . '/vendor/autoload.php';

use Dbout\WpRestApi\NamespaceRouteLoader;

(new NamespaceRouteLoader('App\\Routes\\'))->register();
```

- Every class in `App\Routes\` and its sub-namespaces is scanned. Abstract classes and classes without a `#[Route]` attribute are ignored.
- You can pass several namespaces: `new NamespaceRouteLoader(['App\\Routes\\', 'App\\Admin\\Routes\\'])`.
- `register()` hooks into `rest_api_init` itself, so you don't need to wrap it in `add_action()`.
- The second argument accepts a `RouteLoaderOptions` object, see [Configuration](configuration.md).

## 3. Create your first route

Create `src/Routes/DocumentController.php`:

```php
<?php

namespace App\Routes;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;

#[Route('app/v1', '/documents/(?P<id>\d+)')]
class DocumentController
{
    #[Action(Method::GET)]
    public function get(int $id): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'id' => $id,
        ]);
    }

    #[Action(Method::DELETE)]
    public function delete(int $id): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'deleted' => $id,
        ]);
    }
}
```

You just created two endpoints 🎉

```bash
curl https://example.com/wp-json/app/v1/documents/18
curl -X DELETE https://example.com/wp-json/app/v1/documents/18
```

> ⚠️ These two endpoints are public. Read [Permissions](permissions.md) before shipping anything that writes or deletes data.

Next: [Routes and actions](routes.md).
