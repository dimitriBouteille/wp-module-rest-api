# Middlewares

A middleware runs code around an action: rate limiting, logging, response caching, extra headers, request timing… Instead of copying the same lines at the top of each action, or hooking into `rest_pre_dispatch`, which applies to every endpoint including WordPress core ones, write it once and attach it to the routes that need it.

## Writing a middleware

Create a class implementing `Dbout\WpRestApi\Middleware\MiddlewareInterface`:

```php
use Dbout\WpRestApi\Middleware\MiddlewareInterface;

class Timing implements MiddlewareInterface
{
    public function process(\WP_REST_Request $request, callable $next): \WP_REST_Response
    {
        $start = microtime(true);
        $response = $next($request);
        $response->header('Server-Timing', sprintf('app;dur=%.1f', (microtime(true) - $start) * 1000));

        return $response;
    }
}
```

- `$next($request)` runs the next middleware, or the action, and returns its response.
- Code before `$next()` runs before the action; code after it can read or change the response.
- The action receives the request passed to `$next()`, so a middleware can add or change parameters with `$request->set_param()`. These values are not validated nor sanitized, see [Things to know](#things-to-know).

## Attaching a middleware

Add `#[Middleware]` on a `#[Route]` class, for all its actions, or on an action method:

```php
use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Middleware;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;

#[Middleware(RateLimit::class)]
#[Route('app/v1', '/documents')]
class DocumentController
{
    #[Action(Method::GET)]
    #[Middleware(CacheResponse::class, Timing::class)]
    public function list(): \WP_REST_Response
    {
        // ...
    }

    #[Action(Method::POST)]
    public function create(): \WP_REST_Response
    {
        // Only RateLimit runs here.
    }
}
```

`#[Middleware]` accepts several classes and can be repeated: `#[Middleware(A::class, B::class)]` is the same as `#[Middleware(A::class)]` followed by `#[Middleware(B::class)]`.

## Middlewares for every route

To run a middleware around every action of a loader, pass it in [`RouteLoaderOptions`](configuration.md). Class names and instances are both accepted, so a middleware that needs constructor arguments can be configured here, or created by the [container](dependency-injection.md):

```php
use Dbout\WpRestApi\NamespaceRouteLoader;
use Dbout\WpRestApi\RouteLoaderOptions;

$options = new RouteLoaderOptions(
    middlewares: [
        new RequestLogger($logger),
        Timing::class,
    ],
);

(new NamespaceRouteLoader('App\\Routes\\', $options))->register();
```

Only the routes of this loader are affected, WordPress core endpoints are not.

## Order

Middlewares run from the outside in, then the response goes back the other way:

1. the middlewares of `RouteLoaderOptions`, in the order given;
2. the `#[Middleware]` of the class, in declaration order;
3. the `#[Middleware]` of the method, in declaration order;
4. the action.

With `RateLimit` on the class and `CacheResponse, Timing` on the method, a request goes through `RateLimit` → `CacheResponse` → `Timing` → action, and the response through `Timing` → `CacheResponse` → `RateLimit`.

## Returning early

A middleware can return a response without calling `$next()`. The following middlewares and the action do not run:

```php
class CacheResponse implements MiddlewareInterface
{
    public function process(\WP_REST_Request $request, callable $next): \WP_REST_Response
    {
        $key = 'api_' . md5($request->get_route() . serialize($request->get_query_params()));
        $cached = get_transient($key);
        if (is_array($cached)) {
            return new \WP_REST_Response($cached);
        }

        $response = $next($request);
        if ($response->get_status() === 200) {
            set_transient($key, $response->get_data(), MINUTE_IN_SECONDS);
        }

        return $response;
    }
}
```

## Errors

- An exception thrown by a middleware produces the standard error response, as if the action had thrown it: throw a `RouteException` to choose the code and the HTTP status, see [Error handling](error-handling.md).
- An exception thrown by the action goes up through the middlewares before being formatted, so a middleware can catch it, or run code in a `finally` block:

```php
use Dbout\WpRestApi\Exceptions\RouteException;

class RateLimit implements MiddlewareInterface
{
    public function process(\WP_REST_Request $request, callable $next): \WP_REST_Response
    {
        $key = 'rate_' . md5((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        $count = (int) get_transient($key);
        if ($count >= 60) {
            throw new RouteException('Too many requests.', 'rate-limited', 429);
        }

        set_transient($key, $count + 1, MINUTE_IN_SECONDS);

        return $next($request);
    }
}
```

- A `\WP_Error` returned by the action has already been turned into an error response when it reaches the middlewares: check `$response->get_status()`.

## Things to know

- **Middlewares run after the permission check.** WordPress calls the [permission callback](permissions.md) before the action: a middleware can rely on the user being allowed, but a request refused by the permission never reaches the middlewares (a rate limit does not count it).
- **Parameters set by a middleware skip validation and sanitization.** WordPress applies the `args` of the route (`#[Param]`: `validate_callback`, `sanitize_callback`, `type`, `enum`…) before the action is called, so before the middlewares. A value set with `$request->set_param()` reaches the action with only the cast of its PHP type (`(int)`, `(string)`…): validate and sanitize it in the middleware.
- **`OPTIONS` requests do not reach the middlewares.** WordPress answers CORS preflight requests itself, without calling the action, so CORS headers set by a middleware only apply to the actual request.
- **A method cannot remove a class middleware.** Move the action to another class if it must not go through it.
- **Middlewares are checked when routes are registered**, those of `#[Middleware]` as well as those of `RouteLoaderOptions`: a class that does not exist, does not implement `MiddlewareInterface`, is abstract or has a required constructor argument that the container does not provide throws an `ApiException`, as does a value that is neither a class name nor a middleware instance.
- **Middlewares are created on each request**, by the [container](dependency-injection.md) when it has the class, without arguments otherwise. Most containers return the same instance on each call: a middleware that keeps a state shares it between requests.
- The class names are stored in the [route cache](configuration.md#cache); the middlewares of `RouteLoaderOptions` are not, changing them does not require a cache rebuild.
