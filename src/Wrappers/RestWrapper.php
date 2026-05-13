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
use Dbout\WpRestApi\Exceptions\RouteException;
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
     */
    public function __construct(
        protected RouteAction $action,
        protected bool $debug = false,
        protected ErrorFormatterInterface $errorFormatter = new DefaultFormatter(),
    ) {
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function execute(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $className = $this->action->className;
            $methodName = $this->action->methodName;
            $dependencies = $this->collectDependencies($className, $methodName, $request);
            $instance = new $className();
            $response = $instance->{$methodName}(...$dependencies);
        } catch (\Throwable $exception) {
            return $this->onError($exception);
        }

        if (is_wp_error($response)) {
            return $this->onWpError($response);
        }

        return $response;
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

        $exception = new RouteException(
            message: $message,
            errorCode: $code ?? self::DEFAULT_EXCEPTION_CODE,
            httpStatusCode: self::DEFAULT_EXCEPTION_HTTP_CODE,
            additionalData: $data,
        );

        return $this->buildErrorResponse($exception);
    }

    /**
     * @param class-string $className
     * @param string $methodName
     * @param \WP_REST_Request $request
     * @throws \ReflectionException
     * @return array<int, mixed>
     */
    protected function collectDependencies(string $className, string $methodName, \WP_REST_Request $request): array
    {
        $dependencies = [];
        $requestClass = get_class($request);

        foreach (ReflectionCache::parameters($className, $methodName) as $descriptor) {
            if ($descriptor->typeName === null) {
                continue;
            }

            if ($descriptor->typeName === $requestClass) {
                $dependencies[$descriptor->position] = $request;
                continue;
            }

            if (!$request->has_param($descriptor->name)) {
                continue;
            }

            $value = $request->get_param($descriptor->name);
            $dependencies[$descriptor->position] = $this->castRequestArgument($descriptor->typeName, $value);
        }

        return $dependencies;
    }

    /**
     * @param string $typeName
     * @param mixed $value
     * @return mixed
     */
    protected function castRequestArgument(string $typeName, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($typeName) {
            'int' => (int)$value,
            'string' => (string)$value,
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
