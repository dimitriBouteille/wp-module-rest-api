<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Wrappers;

use Dbout\WpRestApi\ErrorFormat\DefaultFormatter;
use Dbout\WpRestApi\ErrorFormat\ErrorFormatterInterface;
use Dbout\WpRestApi\Exceptions\ApiException;
use Dbout\WpRestApi\Exceptions\RouteException;
use Dbout\WpRestApi\Middleware\MiddlewareInterface;
use Dbout\WpRestApi\Resolvers\ParameterResolverInterface;
use Dbout\WpRestApi\RouteAction;

class RestWrapper
{
    private const DEFAULT_EXCEPTION_CODE = 'route-exception';
    private const DEFAULT_EXCEPTION_HTTP_CODE = 500;
    private const REDACTED_MESSAGE = 'Something went wrong. Please try again.';

    /**
     * @param RouteAction $action
     * @param bool $debug
     * @param ErrorFormatterInterface $errorFormatter
     * @param array<string|MiddlewareInterface> $globalMiddlewares Class names or
     *        instances, run before the #[Middleware] classes of the action.
     */
    public function __construct(
        protected RouteAction $action,
        protected bool $debug = false,
        protected ErrorFormatterInterface $errorFormatter = new DefaultFormatter(),
        protected array $globalMiddlewares = [],
    ) {
    }

    /**
     * Runs the middlewares then the action. Exceptions thrown by the action
     * go up through the middlewares and are formatted once, here.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function execute(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $pipeline = array_reduce(
                array_reverse($this->buildMiddlewares()),
                static fn (\Closure $next, MiddlewareInterface $middleware): \Closure =>
                    static fn (\WP_REST_Request $request): \WP_REST_Response => $middleware->process($request, $next),
                $this->runAction(...),
            );

            return $pipeline($request);
        } catch (\Throwable $exception) {
            return $this->onError($exception);
        }
    }

    /**
     * Last step of the pipeline: calls the action with the arguments built
     * from the request received from the last middleware.
     *
     * @param \WP_REST_Request $request
     * @throws \Throwable
     * @return \WP_REST_Response
     */
    protected function runAction(\WP_REST_Request $request): \WP_REST_Response
    {
        $className = $this->action->className;
        $methodName = $this->action->methodName;
        $dependencies = $this->collectDependencies($className, $methodName, $request);
        $instance = $this->instantiate($className);
        $result = $instance->{$methodName}(...$dependencies);

        if ($result instanceof \WP_Error) {
            return $this->onWpError($result);
        }

        return $this->toRestResponse($result);
    }

    /**
     * Global middlewares first, then those of the class and of the method.
     *
     * @throws ApiException When a middleware class is invalid.
     * @return list<MiddlewareInterface>
     */
    protected function buildMiddlewares(): array
    {
        $middlewares = [];
        foreach ([...$this->globalMiddlewares, ...$this->action->middlewares] as $middleware) {
            if ($middleware instanceof MiddlewareInterface) {
                $middlewares[] = $middleware;
                continue;
            }

            // Already checked when routes are loaded, unless they come from the cache.
            if (!is_a($middleware, MiddlewareInterface::class, true)) {
                throw new ApiException(sprintf(
                    'The middleware %s of %s::%s() must implement %s.',
                    $middleware,
                    $this->action->className,
                    $this->action->methodName,
                    MiddlewareInterface::class,
                ));
            }

            /** @var MiddlewareInterface $instance */
            $instance = $this->instantiate($middleware);
            $middlewares[] = $instance;
        }

        return $middlewares;
    }

    /**
     * Creates the controller, the resolvers and the middlewares. Single
     * entry point so a container can be plugged in later.
     *
     * @param class-string $className
     * @return object
     */
    protected function instantiate(string $className): object
    {
        return new $className();
    }

    /**
     * Normalizes the value returned by an action, as WordPress does for a
     * plain REST callback: arrays, scalars, null and JsonSerializable objects
     * become the body of a 200 response.
     *
     * @param mixed $result
     * @throws \UnexpectedValueException When the value cannot be sent as JSON.
     * @return \WP_REST_Response
     */
    protected function toRestResponse(mixed $result): \WP_REST_Response
    {
        if (is_resource($result) || $result instanceof \Closure) {
            throw new \UnexpectedValueException(sprintf(
                'The action %s::%s() returned a %s, which cannot be sent as JSON. Return a \WP_REST_Response, an array, a scalar or a JsonSerializable object.',
                $this->action->className,
                $this->action->methodName,
                get_debug_type($result),
            ));
        }

        // Same rules as rest_ensure_response(), WP_Error being handled before.
        if ($result instanceof \WP_REST_Response) {
            return $result;
        }

        if ($result instanceof \WP_HTTP_Response) {
            return new \WP_REST_Response($result->get_data(), $result->get_status(), $result->get_headers());
        }

        return new \WP_REST_Response($result);
    }

    /**
     * @param \Throwable $exception
     * @return \WP_REST_Response
     */
    protected function onError(\Throwable $exception): \WP_REST_Response
    {
        $rootException = $exception;
        if (!$exception instanceof RouteException) {
            $exception = new RouteException(
                message: $this->debug === true ? $rootException->getMessage() : self::REDACTED_MESSAGE,
                errorCode: 'fatal-error',
                httpStatusCode: self::DEFAULT_EXCEPTION_HTTP_CODE,
            );
        }

        $additionalData = $exception->getAdditionalData();
        if ($this->debug === true && $this->isWpDebugEnabled()) {
            $additionalData['exception'] = $rootException->getTraceAsString();
        }

        $exception = new RouteException(
            message: $exception->getMessage(),
            errorCode: $exception->getErrorCode(),
            httpStatusCode: $exception->getHttpStatusCode(),
            additionalData: $additionalData,
            previous: $rootException,
        );

        return $this->buildErrorResponse($exception);
    }

    /**
     * @param \WP_Error $error
     * @return \WP_REST_Response
     */
    protected function onWpError(\WP_Error $error): \WP_REST_Response
    {
        $code = null;
        $message = '';
        $data = [];
        foreach ((array) $error->errors as $errorCode => $messages) {
            $code = (string) $errorCode;
            $message = (string) ($messages[0] ?? '');
            $rawData = $error->get_error_data($errorCode);
            $data = is_array($rawData) ? $rawData : [];
            break;
        }

        // WordPress convention: ['status' => 404] sets the HTTP status code.
        $status = filter_var(
            $data['status'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 400, 'max_range' => 599]],
        );
        unset($data['status']);

        $exception = new RouteException(
            message: $message,
            errorCode: $code ?? self::DEFAULT_EXCEPTION_CODE,
            httpStatusCode: is_int($status) ? $status : self::DEFAULT_EXCEPTION_HTTP_CODE,
            additionalData: $data,
        );

        return $this->buildErrorResponse($exception);
    }

    /**
     * @param class-string $className
     * @param string $methodName
     * @param \WP_REST_Request $request
     * Builds the arguments of the action, keyed by parameter name so they are
     * passed as named arguments: a parameter missing from the request keeps its
     * default value instead of shifting the following arguments.
     *
     * @param class-string $className
     * @param string $methodName
     * @param \WP_REST_Request $request
     * @throws \ReflectionException
     * @throws RouteException When a required parameter is missing or an enum value is invalid.
     * @return array<string, mixed>
     */
    protected function collectDependencies(string $className, string $methodName, \WP_REST_Request $request): array
    {
        $dependencies = [];
        $requestClass = $request::class;

        foreach (ReflectionCache::parameters($className, $methodName) as $descriptor) {
            if ($descriptor->isVariadic) {
                continue;
            }

            if ($descriptor->resolverClass !== null) {
                $dependencies[$descriptor->name] = $this->resolveArgument($descriptor, $className, $methodName, $request);
                continue;
            }

            if ($descriptor->typeName === null) {
                continue;
            }

            if ($descriptor->typeName === $requestClass) {
                $dependencies[$descriptor->name] = $request;
                continue;
            }

            if ($descriptor->typeName === \WP_User::class) {
                $dependencies[$descriptor->name] = $this->injectCurrentUser($descriptor);
                continue;
            }

            if (!$request->has_param($descriptor->requestName)) {
                if ($descriptor->hasDefaultValue) {
                    continue;
                }

                if ($descriptor->allowsNull) {
                    $dependencies[$descriptor->name] = null;
                    continue;
                }

                throw new RouteException(
                    message: sprintf('Missing parameter: %s.', $descriptor->requestName),
                    errorCode: 'missing-parameter',
                    httpStatusCode: 400,
                    additionalData: ['parameter' => $descriptor->requestName],
                );
            }

            $rawValue = $request->get_param($descriptor->requestName);
            $value = $this->castRequestArgument($descriptor->typeName, $rawValue);
            if ($value === null && $rawValue !== null && is_subclass_of($descriptor->typeName, \BackedEnum::class)) {
                throw new RouteException(
                    message: sprintf('Invalid parameter: %s.', $descriptor->requestName),
                    errorCode: 'invalid-parameter',
                    httpStatusCode: 400,
                    additionalData: [
                        'parameter' => $descriptor->requestName,
                        'accepted' => array_map(
                            static fn (\BackedEnum $case): int|string => $case->value,
                            $descriptor->typeName::cases(),
                        ),
                    ],
                );
            }

            $dependencies[$descriptor->name] = $value;
        }

        return $dependencies;
    }

    /**
     * @param ParameterDescriptor $descriptor
     * @param class-string $className
     * @param string $methodName
     * @param \WP_REST_Request $request
     * @throws ApiException When the resolver class is invalid.
     * @throws \ReflectionException
     * @return mixed
     */
    protected function resolveArgument(
        ParameterDescriptor $descriptor,
        string $className,
        string $methodName,
        \WP_REST_Request $request,
    ): mixed {
        $resolverClass = (string) $descriptor->resolverClass;
        // Already checked when routes are loaded, unless they come from the cache.
        if (!ReflectionCache::isParameterResolver($resolverClass)) {
            throw new ApiException(sprintf(
                'The resolver %s of the argument $%s must implement %s.',
                $resolverClass,
                $descriptor->name,
                ParameterResolverInterface::class,
            ));
        }

        /** @var ParameterResolverInterface $resolver */
        $resolver = $this->instantiate($resolverClass);
        return $resolver->resolve(new \ReflectionParameter([$className, $methodName], $descriptor->name), $request);
    }

    /**
     * @param ParameterDescriptor $descriptor
     * @throws RouteException When nobody is logged in and the argument is not nullable.
     * @return \WP_User|null
     */
    protected function injectCurrentUser(ParameterDescriptor $descriptor): ?\WP_User
    {
        $user = $this->currentUser();
        if ($user instanceof \WP_User || $descriptor->allowsNull) {
            return $user;
        }

        throw new RouteException(
            message: 'You must be logged in.',
            errorCode: 'not-logged-in',
            httpStatusCode: 401,
        );
    }

    /**
     * Exposed as a protected method so tests can simulate a logged-in user
     * without loading pluggable.php.
     *
     * @return \WP_User|null Null when nobody is logged in.
     */
    protected function currentUser(): ?\WP_User
    {
        $user = wp_get_current_user();
        return $user->exists() ? $user : null;
    }

    /**
     * @param string $typeName
     * @param mixed $value
     * @return mixed Backed enums return null when the value matches no case.
     */
    protected function castRequestArgument(string $typeName, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_subclass_of($typeName, \BackedEnum::class)) {
            if (!is_int($value) && !is_string($value)) {
                return null;
            }

            try {
                return $typeName::tryFrom($value);
            } catch (\TypeError) {
                // A non-numeric string for an int-backed enum.
                return null;
            }
        }

        return match ($typeName) {
            'int' => (int)$value,
            'string' => (string)$value,
            'float' => (float)$value,
            // Same rule as WordPress for the array type: "1,2" gives ['1', '2'].
            'array' => match (true) {
                is_array($value) => $value,
                is_scalar($value) => wp_parse_list((string)$value),
                default => [],
            },
            // rest_sanitize_boolean() only changes strings ('false', '0' → false).
            'bool' => is_string($value) ? rest_sanitize_boolean($value) : (bool)$value,
            default => $value,
        };
    }

    /**
     * Defense in depth: stack traces are only ever included when both the
     * RouteLoaderOptions::$debug flag and the WP_DEBUG constant are true.
     * Exposed as a protected method so tests can simulate WP_DEBUG=false
     * without having to redefine a PHP constant.
     */
    protected function isWpDebugEnabled(): bool
    {
        return defined('WP_DEBUG') && WP_DEBUG === true;
    }

    /**
     * @param RouteException $exception
     * @return \WP_REST_Response
     */
    protected function buildErrorResponse(RouteException $exception): \WP_REST_Response
    {
        $body = $this->errorFormatter->format($exception, $this->debug);
        $response = new \WP_REST_Response($body, $exception->getHttpStatusCode());

        $contentType = $this->errorFormatter->contentType();
        if ($contentType !== null) {
            $response->header('Content-Type', $contentType);
        }

        return $response;
    }
}
