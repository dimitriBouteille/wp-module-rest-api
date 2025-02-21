# WordPress Rest API

[![GitHub Release](https://img.shields.io/github/v/release/dimitriBouteille/wp-module-rest-api)](https://github.com/dimitriBouteille/wp-module-rest-api/releases) [![tests](https://img.shields.io/github/actions/workflow/status/dimitriBouteille/wp-orm/tests.yml?label=tests)](https://github.com/dimitriBouteille/wp-module-rest-api/actions/workflows/tests.yml) [![Packagist Downloads](https://img.shields.io/packagist/dt/dbout/wp-module-rest-api?color=yellow)](https://packagist.org/packages/dbout/wp-module-rest-api)

WordPress module designed for developers that want to add routes to the [WordPress Rest API](https://developer.wordpress.org/rest-api/) in a few moments.

> 💡 To simplify the integration of this library, we recommend using WordPress with one of the following tools: [Bedrock](https://roots.io/bedrock/), [Themosis](https://framework.themosis.com/) or [Wordplate](https://github.com/wordplate/wordplate#readme).

## Documentation

If you want to know more about how the WordPress API works, you can [read the WordPress documentation](https://developer.wordpress.org/rest-api/) :)

You can find all the documentation in the [wiki](https://github.com/dimitriBouteille/wp-module-rest-api/wiki).

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

## Contributing

We encourage you to contribute to this repository, so everyone can benefit from new features, bug fixes, and any other improvements. Have a look at our [contributing guidelines](CONTRIBUTING.md) to find out how to raise a pull request.