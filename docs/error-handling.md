# Errors Handling

The module comes with an error management system that aims to standardize errors. The module automatically sends the appropriate HTTP status code to the client: 400 for expected errors, 500 for unexpected.

Here is the structure of the JSON response returned in case of error:

```json
{
  "error": {
    "code": "not-found",
    "message": "Document not found.",
    "data": null
  }
}
```

### 400 error

You can return a 400 error with the exception `\Dbout\WpRestApi\Exceptions\RouteException`. The error message will be returned in the response.

```php
#[Action(Method::GET)]
public function get(int $documentId): \WP_REST_Response
{
    // Add your logic
    $document = $this->findDocument($documentId);
    if (!$document) {
        throw new \Dbout\WpRestApi\Exceptions\RouteException('Deprecated function.');
    }

    return new \WP_REST_Response([
        'success' => true,
    ]);
}
```

```json
{
    "error": {
        "code": "route-exception",
        "message": "Deprecated function.",
        "data": null
    }
}
```

### 404 error

You can return a 404 error with the exception `\Dbout\WpRestApi\Exceptions\NotFoundException` :

```php
#[Action(Method::GET)]
public function get(int $documentId): \WP_REST_Response
{
    // Add your logic
    $document = $this->findDocument($documentId);
    if (!$document) {
        throw new \Dbout\WpRestApi\Exceptions\NotFoundException('Document')
    }

    return new \WP_REST_Response([
        'success' => true,
    ]);
}
```

### 500 error

If you want to return a 500 error, just use `\Exception`. By default, 500 error messages are never returned, a generic message is returned instead:

```json
{
  "error": {
    "code": "fatal-error",
    "message": "Something went wrong. Please try again.",
    "data": null
  }
}
```

### Custom error

You can return a custom error using the exception `\Dbout\WpRestApi\Exceptions\RouteException` :

```php
#[Action(Method::GET)]
public function get(int $documentId): \WP_REST_Response
{
    // Add your logic
    $document = $this->findDocument($documentId);
    if (!$document) {
        throw new \Dbout\WpRestApi\Exceptions\RouteException(
            message: 'Deprecated function.'
            errorCode: 'deprecated-function',
            httpStatusCode: 200
        );
    }

    return new \WP_REST_Response([
        'success' => true,
    ]);
}
```

```json
{
  "error": {
    "code": "deprecated-function",
    "message": "Deprecated function.",
    "data": null
  }
}
```