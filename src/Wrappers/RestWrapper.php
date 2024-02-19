<?php
/**
 * Copyright (c) 2024 Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
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
     */
    public function __construct(
        protected RouteAction $action,
    ) {
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function execute(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $classRef = new \ReflectionClass($this->action->className);
            $method = $classRef->getMethod($this->action->methodName);
            $dependencies = $this->collectDependencies($method, $request);
            $response = $method->invoke($classRef->newInstance(), ...$dependencies);
        } catch (\Exception $exception) {
            if (!$exception instanceof RouteException) {
                $exception = new RouteException(
                    message: 'Something went wrong. Please try again.',
                    errorCode: 'fatal-error',
                    httpStatusCode: self::DEFAULT_EXCEPTION_HTTP_CODE
                );
            }

            return $this->parseErrorToRestResponse(
                $this->buildResponseError($exception),
                $exception->getHttpStatusCode()
            );
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
     * @param \ReflectionMethod $method
     * @param \WP_REST_Request $request
     * @return array
     */
    protected function collectDependencies(\ReflectionMethod $method, \WP_REST_Request $request): array
    {
        $dependencies = [];
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type->getName() === get_class($request)) {
                $dependencies[$parameter->getPosition()] = $request;
                continue;
            }

            if (!$request->has_param($parameter->getName())) {
                continue;
            }

            $value = $request->get_param($parameter->getName());
            $value = $this->castRequestArgument($type, $value);
            $dependencies[$parameter->getPosition()] = $value;
        }

        return $dependencies;
    }

    /**
     * @param \ReflectionNamedType $type
     * @param mixed $value
     * @return mixed
     */
    protected function castRequestArgument(\ReflectionNamedType $type, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type->getName()) {
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
        RouteException $exception
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
