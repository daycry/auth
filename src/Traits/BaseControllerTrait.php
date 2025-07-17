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

namespace Daycry\Auth\Traits;

use CodeIgniter\Exceptions\ExceptionInterface;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Mimes;
use Config\Services;
use Daycry\Auth\Libraries\Utils;
use Daycry\Auth\Services\AttemptHandler;
use Daycry\Auth\Services\ExceptionHandler;
use Daycry\Auth\Services\RequestLogger;
use Daycry\Encryption\Encryption;
use Psr\Log\LoggerInterface;

/**
 * BaseControllerTrait that delegates to specialized services
 *
 * This version follows Single Responsibility Principle by using dedicated services
 * for logging, attempt handling, and exception management.
 */
trait BaseControllerTrait
{
    use Validation;

    protected Encryption $encryption;
    protected RequestLogger $requestLogger;
    protected AttemptHandler $attemptHandler;
    protected ExceptionHandler $exceptionHandler;
    protected array $args;
    protected mixed $content = null;

    /**
     * Hook for early checks - can be overridden by implementing classes
     */
    protected function earlyChecks(): void
    {
        // Override in child classes if needed
    }

    /**
     * Initialize controller with services
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger): void
    {
        // Load required helpers
        helper(['security', 'auth']);

        // Initialize services
        $this->encryption       = new Encryption();
        $this->requestLogger    = new RequestLogger();
        $this->attemptHandler   = new AttemptHandler();
        $this->exceptionHandler = new ExceptionHandler();

        // Call parent initialization
        parent::initController($request, $response, $logger);

        // Set response format if method exists (for API controllers)
        if (method_exists($this, 'setFormat')) {
            $output = $this->request->negotiate('media', setting('Format.supportedResponseFormats'));
            $output = Mimes::guessExtensionFromType($output);
            $this->setFormat($output);
        }

        // Extract request parameters
        $this->args    = Utils::getAllParams();
        $this->content = $this->args['body'] ?? null;

        // Run early checks
        $this->earlyChecks();
    }

    /**
     * Cleanup and logging on destruction
     */
    public function __destruct()
    {
        if (isset($this->request) && $this->request) {
            $this->requestLogger->logRequest($this->response);

            if (! $this->requestLogger->isRequestAuthorized()) {
                $this->attemptHandler->handleInvalidAttempt($this->request);
            }
        }

        if (isset($this->validator) && $this->validator) {
            $this->validator->reset();
        }
    }

    /**
     * Get CSRF token for forms
     */
    protected function getToken(): array
    {
        return ['name' => csrf_token(), 'hash' => csrf_hash()];
    }

    /**
     * Main request handler with exception management
     */
    public function _remap(string $method, ...$params)
    {
        try {
            if (! method_exists($this, $method)) {
                throw PageNotFoundException::forPageNotFound();
            }

            // Validate attempts if enabled
            $this->attemptHandler->validateAttempts($this->response);

            // Execute the method
            $data = $this->{$method}(...$params);

            // Handle different response types
            if ($data instanceof ResponseInterface) {
                return $data;
            }

            // Return JSON for AJAX requests
            if (method_exists($this->request, 'isAJAX') && $this->request->isAJAX() && (is_array($data) || is_object($data))) {
                return $this->response->setJSON($data);
            }

            // Return regular response
            return $this->response->setBody($data);
        } catch (ExceptionInterface $ex) {
            return $this->exceptionHandler->handleException(
                $ex,
                $this->request,
                $this->response,
                $this->validator ?? null,
                $this,
            );
        }
    }

    /**
     * Mark request as unauthorized
     */
    protected function setRequestUnauthorized(): void
    {
        $this->requestLogger->setRequestAuthorized(false);
    }

    /**
     * Check if request is authorized
     */
    protected function isRequestAuthorized(): bool
    {
        return $this->requestLogger->isRequestAuthorized();
    }
}
