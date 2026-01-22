<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Daycry\Auth\Services;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Validation\Validation;
use ReflectionMethod;
use ReflectionProperty;
use Throwable;

/**
 * Service for handling exceptions in Auth controllers
 *
 * Extracts exception handling logic from BaseControllerTrait
 */
class ExceptionHandler
{
    protected RequestLogger $requestLogger;

    public function __construct()
    {
        $this->requestLogger = new RequestLogger();
    }

    /**
     * Handle exceptions with proper authorization status and response
     */
    public function handleException(
        Throwable $ex,
        RequestInterface $request,
        ResponseInterface $response,
        ?Validation $validator = null,
        ?object $controller = null,
    ): ResponseInterface {
        // Extract authorization status from exception if available
        if (property_exists($ex, 'authorized')) {
            $authorized = (new ReflectionProperty($ex, 'authorized'))->getValue($ex);
            $this->requestLogger->setRequestAuthorized($authorized);
        } else {
            $this->requestLogger->setRequestAuthorized(false);
        }

        // Get error message
        $message = $validator?->getErrors() ?: $ex->getMessage();
        $code    = $ex->getCode() ?: 400;

        // Generate CSRF token for response
        $token = ['name' => csrf_token(), 'hash' => csrf_hash()];

        // Handle response based on controller capabilities and request type
        if ($controller && method_exists($controller, 'fail')) {
            $reflection = new ReflectionMethod($controller, 'fail');
            if (! $reflection->isPublic()) {
                $reflection->setAccessible(true);
            }

            return $reflection->invoke($controller, $message, $code);
        }

        // Check if request is AJAX (assuming IncomingRequest which has isAJAX method)
        $isAjax = method_exists($request, 'isAJAX') ? $request->isAJAX() : false;

        if ($isAjax) {
            return $response->setStatusCode($code)->setJSON([
                'status' => false,
                'error'  => $message,
                'token'  => $token,
            ]);
        }

        // For non-AJAX requests without fail method, re-throw the exception
        throw $ex;
    }

    /**
     * Get the request logger instance
     */
    public function getRequestLogger(): RequestLogger
    {
        return $this->requestLogger;
    }

    /**
     * Static method to get a new instance
     */
    public static function getInstance(): self
    {
        return new self();
    }
}
