# WordPress Rest API

![GitHub Release](https://img.shields.io/github/v/release/dimitriBouteille/wp-module-rest-api) [![tests](https://img.shields.io/github/actions/workflow/status/dimitriBouteille/wp-orm/tests.yml?label=tests)](https://github.com/dimitriBouteille/wp-module-rest-api/actions/workflows/tests.yml)

WordPress module designed for developers that want to add routes to the [WordPress Rest API](https://developer.wordpress.org/rest-api/) in a few moments.

> 💡 To simplify the integration of this library, we recommend using WordPress with one of the following tools: [Bedrock](https://roots.io/bedrock/), [Themosis](https://framework.themosis.com/) or [Wordplate](https://github.com/wordplate/wordplate#readme).

## Documentation

If you want to know more about how the WordPress API works, you can [read the WordPress documentation](https://developer.wordpress.org/rest-api/) :)

- [Installation](#installation)
- [Basic usage](#usage)
- [Error handling](/doc/error-handling.md)
- [Permission](/doc/permission.md)
- [Route loader options](doc/options.md)

## Installation

### Requirements

The server requirements are basically the same as for [WordPress](https://wordpress.org/about/requirements/) with the addition of a few ones :

- PHP >= 8.1
- [Composer](https://getcomposer.org/)

### Installation with composer

You can use [Composer](https://getcomposer.org/). Follow the [installation instructions](https://getcomposer.org/doc/00-intro.md) if you do not already have composer installed.

~~~bash
composer require dbout/wp-module-rest-api
~~~

In your PHP script, make sure you include the autoloader:

~~~php
require __DIR__ . '/vendor/autoload.php';
~~~

## Usage

Before creating your first route, you must initialize the module. It is advisable to add this code at the beginning of the `functions.php` file of your theme or in a `mu-plugin`.

```php
use Dbout\WpRestApi\RouteLoader;

// One folder
$loader = new RouteLoader(__DIR__ . '/src/Api/Routes');

// Multiple folders
$loader = new RouteLoader([
    __DIR__ . '/themes/my-theme/api'
    __DIR__ . '/src/Api/Routes',
]);

// You can also use pattern
$loader = new RouteLoader(__DIR__ . '/src/Modules/*/Api/Routes');

$loader->register();
```

> 💡 The module will automatically search for all classes that are in the folder and sub folder.

> 💡 You can pass as the second argument of RouteLoader an option object: [read the documentation](doc/options.md).

Now you have initialized the module, you just need to create your first route in the routes folder.

```php
<?php

use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Attributes\Action;

#[Route(
    namespace: 'app/v2',
    route: 'document/(?P<documentId>\d+)'
)]
class Document 
{

    #[Action(Method::GET)]
    public function get(\WP_REST_Request $request): \WP_REST_Response
    {
        // Add your logic 
        $id = $request->get_param('documentId');
       
        return new \WP_REST_Response([
            'success' => true,
        ]);
    }

   #[Action(Method::DELETE)]
    public function delete(\WP_REST_Request $request): \WP_REST_Response
    {
        // Add your logic 
        $id = $request->get_param('documentId');
        
        return new \WP_REST_Response([
            'success' => true,
        ]);
    }
}
```

You just created 2 routes 🎉

- `GET:wp-json/app/v2/document/18`
- `DELETE:wp-json/app/v2/document/18`

The logic is extremely simple, you can use the following methods: `GET`, `POST`, `PUT`, `PATCH` and `DELETE`

If you need, you can define multiple methods for an action by passing a method array :

```php
#[Action([Method::GET, Method::POST, Method::PUT])]
public function execute(\WP_REST_Request $request): \WP_REST_Response
{
    // Add your logic 

}
```

### Callback arguments

If your route contains parameters, you can retrieve them as an argument for your function :

```php
<?php

use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Attributes\Action;

#[Route(
    'app/v2',
    'document/(?P<documentId>\d+)'
)]
class Document
{

    #[Action(Method::GET)]
    public function get(int $documentId): \WP_REST_Response
    {
        // Add your logic

        return new \WP_REST_Response([
            'success' => true,
        ]);
    }
}
```

> 💡If your function contains a `WP_REST_Request` argument, the [WP_REST_Request](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/#arguments) object will be passed as an argument.

## Contributing

We encourage you to contribute to this repository, so everyone can benefit from new features, bug fixes, and any other improvements. Have a look at our [contributing guidelines](CONTRIBUTING.md) to find out how to raise a pull request.