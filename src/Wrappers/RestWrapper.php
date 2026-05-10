<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

namespace Dbout\WpRestApi\Wrappers;

use Dbout\WpRestApi\Exceptions\RouteException;
use Dbout\WpRestApi\RouteAction;

class RestWrapper
{
    private const DEFAULT_EXCEPTION_CODE = 'route-exception';
    private const DEFAULT_EXCEPTION_HTTP_CODE = 500;

    /**
     * @param RouteAction $action
     * @param bool $debug
     */
    public function __construct(
        protected RouteAction $action,
        protected bool $debug = false,
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
            return $this->parseErrorToRestResponse(
                $response,
                self::DEFAULT_EXCEPTION_HTTP_CODE
            );
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
                message: 'Something went wrong. Please try again.',
                errorCode: 'fatal-error',
                httpStatusCode: self::DEFAULT_EXCEPTION_HTTP_CODE,
            );
        }

        if ($this->debug === true) {
            $exception = new RouteException(
                message: $rootException->getMessage(),
                errorCode: $exception->getErrorCode(),
                httpStatusCode: $exception->getHttpStatusCode(),
                additionalData: array_merge($exception->getAdditionalData(), [
                    'exception' => $rootException->getTraceAsString(),
                ]),
                previous: $rootException,
            );
        }

        return $this->parseErrorToRestResponse(
            $this->buildResponseError($exception),
            $exception->getHttpStatusCode()
        );
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
     * @param RouteException $exception
     * @return \WP_Error
     */
    protected function buildResponseError(
        RouteException $exception,
    ): \WP_Error {
        return new \WP_Error(
            $exception->getErrorCode() ?? self::DEFAULT_EXCEPTION_CODE,
            $exception->getMessage(),
            $exception->getAdditionalData()
        );
    }

    /**
     * @param \WP_Error $error
     * @param int $httpCode
     * @return \WP_REST_Response
     */
    protected function parseErrorToRestResponse(\WP_Error $error, int $httpCode): \WP_REST_Response
    {
        $errors = [];
        foreach ((array) $error->errors as $code => $messages) {
            foreach ((array) $messages as $message) {
                $errors[] = [
                    'code' => $code,
                    'message' => $message,
                    'data' => $error->get_error_data($code),
                ];
            }
        }

        $data = array_shift($errors);
        if ($errors !== []) {
            $data['additional_errors'] = $errors;
        }

        return new \WP_REST_Response([
            'error' => $data,
        ], $httpCode);
    }
}
