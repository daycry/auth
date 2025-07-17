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

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Router\Router;
use Config\Services;
use Daycry\Auth\Interfaces\AuthController;
use Daycry\Auth\Libraries\Logger;
use ReflectionClass;

/**
 * Service for handling request logging in Auth controllers
 *
 * Extracts logging logic from BaseControllerTrait to follow SRP
 */
class RequestLogger
{
    protected Logger $logger;
    protected Router $router;
    protected bool $isRequestAuthorized = true;

    public function __construct()
    {
        $this->logger = Services::log();
        $this->router = Services::router();
    }

    /**
     * Set whether the request is authorized
     */
    public function setRequestAuthorized(bool $authorized): self
    {
        $this->isRequestAuthorized = $authorized;

        return $this;
    }

    /**
     * Get current authorization status
     */
    public function isRequestAuthorized(): bool
    {
        return $this->isRequestAuthorized;
    }

    /**
     * Log the request with proper authorization status
     */
    public function logRequest(ResponseInterface $response): void
    {
        $controllerName = $this->router->controllerName();

        // Only check if controller class exists to avoid errors in tests
        if ($controllerName && class_exists($controllerName)) {
            $reflectionClass = new ReflectionClass($controllerName);

            if ($reflectionClass->implementsInterface(AuthController::class)) {
                $this->logger->setLogAuthorized(false);
            }
        }

        $this->logger
            ->setAuthorized($this->isRequestAuthorized)
            ->setResponseCode($response->getStatusCode())
            ->save();
    }

    /**
     * Static method to get a new instance
     */
    public static function getInstance(): self
    {
        return new self();
    }
}
