# Error handling

Every error thrown or returned by an action is turned into a JSON response with a consistent format and the right HTTP status code.

```json
{
  "error": {
    "code": "not-found",
    "message": "Document not found.",
    "data": []
  }
}
```

## Expected errors: `RouteException`

Throw a `Dbout\WpRestApi\Exceptions\RouteException` when the client did something wrong. Its message is sent to the client.

```php
use Dbout\WpRestApi\Exceptions\RouteException;

#[Action(Method::POST)]
public function publish(int $id): \WP_REST_Response
{
    if ($this->isAlreadyPublished($id)) {
        throw new RouteException('This document is already published.');
    }

    // ...
}
```

```json
{
  "error": {
    "code": "route-exception",
    "message": "This document is already published.",
    "data": []
  }
}
```

The status code is `400` by default. All the constructor arguments:

```php
throw new RouteException(
    message: 'This document is already published.',
    errorCode: 'already-published',     // default: route-exception
    httpStatusCode: 409,                // default: 400
    additionalData: ['id' => $id],      // sent in "data"
);
```

```json
{
  "error": {
    "code": "already-published",
    "message": "This document is already published.",
    "data": {
      "id": 18
    }
  }
}
```

## Not found: `NotFoundException`

`Dbout\WpRestApi\Exceptions\NotFoundException` returns a `404` with the `not-found` code. The first argument is the object type used in the message:

```php
use Dbout\WpRestApi\Exceptions\NotFoundException;

$document = get_post($id);
if (!$document instanceof \WP_Post) {
    throw new NotFoundException('Document'); // "Document not found."
}
```

## Unexpected errors

Any other exception or PHP error (`\Exception`, `\TypeError`…) returns a `500`. Its message is **never** sent to the client by default, since it could leak internal details:

```json
{
  "error": {
    "code": "fatal-error",
    "message": "Something went wrong. Please try again.",
    "data": []
  }
}
```

During development, enable [debug mode](configuration.md#debug) to see the real message and the stack trace.

## Returning a `WP_Error`

An action can also return a `\WP_Error`. Only its first error is sent. As in WordPress core, the `status` data sets the status code:

```php
return new \WP_Error('invalid-email', 'The email is invalid.', ['status' => 400]);
```

```json
{
  "error": {
    "code": "invalid-email",
    "message": "The email is invalid.",
    "data": []
  }
}
```

- `status` must be an integer between `400` and `599`, otherwise the status code is `500`.
- `status` is not sent to the client; the other data is sent in `data`.

> 💡 To send several messages, throw a `RouteException` with `additionalData`:

```php
throw new RouteException(
    message: 'The submitted data is invalid.',
    errorCode: 'invalid-data',
    additionalData: [
        'errors' => [
            ['code' => 'invalid-email', 'message' => 'The email is invalid.'],
            ['code' => 'invalid-name', 'message' => 'The name is required.'],
        ],
    ],
);
```

## Errors returned by WordPress

Some errors are produced by WordPress before your action runs, and keep the WordPress format (`code`, `message`, `data.status`):

- a [permission](permissions.md) check that fails (`rest_forbidden`);
- a [`#[Param]`](parameters.md) validation that fails (`rest_missing_callback_param`, `rest_invalid_param`);
- an unknown route (`rest_no_route`).

## RFC 7807 format

To return errors in the standard [RFC 7807](https://www.rfc-editor.org/rfc/rfc7807) format, use `ProblemJsonFormatter`:

```php
use Dbout\WpRestApi\ErrorFormat\ProblemJsonFormatter;
use Dbout\WpRestApi\NamespaceRouteLoader;
use Dbout\WpRestApi\RouteLoaderOptions;

$options = new RouteLoaderOptions(
    errorFormatter: new ProblemJsonFormatter(),
);

(new NamespaceRouteLoader('App\\Routes\\', $options))->register();
```

Responses are sent with the `Content-Type: application/problem+json` header:

```json
{
  "type": "about:blank",
  "title": "not-found",
  "status": 404,
  "detail": "Document not found."
}
```

`data` is added only when the exception has additional data.

## Custom format

Implement `Dbout\WpRestApi\ErrorFormat\ErrorFormatterInterface` to use your own format:

```php
<?php

namespace App\Api;

use Dbout\WpRestApi\ErrorFormat\ErrorFormatterInterface;
use Dbout\WpRestApi\Exceptions\RouteException;

class AppErrorFormatter implements ErrorFormatterInterface
{
    public function format(RouteException $exception, bool $debug): array
    {
        return [
            'success' => false,
            'code' => $exception->getErrorCode() ?? 'error',
            'message' => $exception->getMessage(),
            'details' => $exception->getAdditionalData(),
        ];
    }

    public function contentType(): ?string
    {
        return null; // keep the default application/json
    }
}
```

Unexpected errors are converted to a `RouteException` (`fatal-error`, `500`) before reaching the formatter, so `format()` handles every case. Pass the formatter through the `errorFormatter` option.

Next: [Configuration](configuration.md).
