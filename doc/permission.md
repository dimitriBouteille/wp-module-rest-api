# Permission

The module contains a system to add permissions on routes and actions. The operation is close to that of WordPress since it is the [permission_callback](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/#permissions-callback) function that is used.

This is a function that checks if the user can perform the action (reading, updating, etc.) before the real callback is called. This allows the API to tell the client what actions they can perform on a given URL without needing to attempt the request first.

> 💡 By default, actions/routes **HAVE NO CONTROL**, `true` is returned by default during verification.

## Usage

To add a check on a route, simply pass in 3rd argument (`permissionCallback`) a function/class that will check the permission:

```php
<?php

use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Permissions\IsAdministrator;

#[Route(
    namespace: 'app/v2',
    route: 'admin/document/(?P<documentId>\d+)',
    permissionCallback: IsAdministrator::class
)]
class Document 
{

    #[Action(Method::GET)]
    public function get(\WP_REST_Request $request): \WP_REST_Response
    {
        // Add your logic
    }

   #[Action(Method::DELETE)]
    public function delete(\WP_REST_Request $request): \WP_REST_Response
    {
        // Add your logic
    }
}
```

In the example above, the actions `GET:wp-json/app/v2/admin/document/18` and `DELETE:wp-json/app/v2/admin/document/18` must be executed by an admin.

If you want you can also set the `permissionCallback` in the action:

```php
<?php

use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Permissions\IsAdministrator;
use Dbout\WpRestApi\Permissions\IsAuthor;

#[Route(
    namespace: 'app/v2',
    route: 'admin/document/(?P<documentId>\d+)',
)]
class Document 
{

    #[Action(
        method: Method::GET,
        permissionCallback: IsAdministrator::class
    )]
    public function get(\WP_REST_Request $request): \WP_REST_Response
    {
        // Add your logic
    }

   #[Action(
        Method::DELETE,
        permissionCallback: IsAuthor::class
    )]
    public function delete(\WP_REST_Request $request): \WP_REST_Response
    {
        // Add your logic
    }
}
```

## Create custom permission checker

Nothing complicated, just create a class that will implement `Dbout\WpRestApi\Permissions\PermissionInterface`. 

`allow` should return a `boolean`, a `WP_Error` instance or throw `\Dbout\WpRestApi\Exceptions\PermissionException`. If this function returns true, the response will be processed. If it returns false, a default error message will be returned and the request will not proceed with processing. If it returns a WP_Error, that error will be returned to the client.

```php
<?php

use Dbout\Framework\Api\Rest\Permissions\PermissionInterface;

class SignaturePermission implements PermissionInterface
{
    /**
     * @inheritDoc
     */
    public function allow(\WP_REST_Request $request): bool|\WP_Error
    {
        $signatureRequest = $request->get_header('Signature');

        $secretKey = 'XX-YYY';
        $params = $request->get_json_params();
        $payloadJson = json_encode($params);
        $expectedSignature = hash_hmac('sha256', $payloadJson, $secretKey);
        return $signatureRequest !== $expectedSignature;
    }
}
```

Note `allow` also receives the Request object as the first parameter, so you can do checks based on request arguments if you need to.

Now you can use this permission on your route/action:

```php
<?php

use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Permissions\IsAdministrator;

#[Route(
    namespace: 'app/v2',
    route: 'adyen/webhook/json',
    permissionCallback: SignaturePermission::class
)]
class AdyenWebhook 
{

    #[Action([Method::GET, Method::POST])]
    public function execute(\WP_REST_Request $request): \WP_REST_Response
    {
        // Add your logic
    }
}
```