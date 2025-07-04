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
use CodeIgniter\Router\Router;
use Config\Mimes;
use Config\Services;
use Daycry\Auth\Interfaces\AuthController;
use Daycry\Auth\Libraries\Logger;
use Daycry\Auth\Libraries\Utils;
use Daycry\Auth\Models\AttemptModel;
use Daycry\Auth\Validators\AttemptValidator;
use Daycry\Encryption\Encryption;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionProperty;

trait BaseControllerTrait
{
    use Validation;

    protected Router $router;
    protected ?Logger $_logger = null;
    protected Encryption $encryption;
    private bool $_isRequestAuthorized = true;
    protected array $args;
    protected mixed $content = null;

    protected function earlyChecks(): void
    {
    }

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger): void
    {
        helper(['security', 'auth']);

        $this->_logger    = Services::log();
        $this->router     = Services::router();
        $this->encryption = new Encryption();

        parent::initController($request, $response, $logger);

        if (method_exists($this, 'setFormat')) {
            $output = $this->request->negotiate('media', setting('Format.supportedResponseFormats'));
            $output = Mimes::guessExtensionFromType($output);
            $this->setFormat($output);
        }

        $this->args    = Utils::getAllParams();
        $this->content = $this->args['body'] ?? null;

        $this->earlyChecks();
    }

    public function __destruct()
    {
        if ($this->request) {
            $this->_logRequest();

            if (service('settings')->get('Auth.enableInvalidAttempts') === true) {
                $this->handleInvalidAttempts();
            }
        }

        if ($this->validator) {
            $this->validator->reset();
        }
    }

    protected function _logRequest(): void
    {
        $reflectionClass = new ReflectionClass($this->router->controllerName());

        if ($reflectionClass->implementsInterface(AuthController::class)) {
            $this->_logger->setLogAuthorized(false);
        }

        $this->_logger
            ->setAuthorized($this->_isRequestAuthorized)
            ->setResponseCode($this->response->getStatusCode())
            ->save();
    }

    protected function getToken(): array
    {
        return ['name' => csrf_token(), 'hash' => csrf_hash()];
    }

    public function _remap(string $method, ...$params)
    {
        try {
            if (! method_exists($this, $method)) {
                throw PageNotFoundException::forPageNotFound();
            }

            if (service('settings')->get('Auth.enableInvalidAttempts') === true) {
                AttemptValidator::check($this->response);
            }

            $data = $this->{$method}(...$params);

            if ($data instanceof ResponseInterface) {
                return $data;
            }

            if ($this->request->isAJAX() && (is_array($data) || is_object($data))) {
                return $this->response->setJSON($data);
            }

            return $this->response->setBody($data);
        } catch (ExceptionInterface $ex) {
            $this->handleException($ex);
        }
    }

    private function handleInvalidAttempts(): void
    {
        $attemptModel = new AttemptModel();
        $attempt      = $attemptModel->where('ip_address', $this->request->getIPAddress())->first();

        if ($this->_isRequestAuthorized === false) {
            if ($attempt === null) {
                $attempt = [
                    'user_id'      => auth()->user()?->id,
                    'ip_address'   => $this->request->getIPAddress(),
                    'attempts'     => 1,
                    'hour_started' => time(),
                ];
                $attemptModel->save($attempt);
            } elseif ($attempt->attempts < service('settings')->get('Auth.maxAttempts')) {
                $attempt->attempts++;
                $attemptModel->save($attempt);
            }
        }
    }

    private function handleException(ExceptionInterface $ex)
    {
        if (property_exists($ex, 'authorized')) {
            $this->_isRequestAuthorized = (new ReflectionProperty($ex, 'authorized'))->getValue();
        }

        $message = $this->validator?->getErrors() ?: $ex->getMessage();
        $code    = $ex->getCode() ?: 400;

        if (method_exists($this, 'fail')) {
            return $this->fail($message, $code);
        }

        if ($this->request->isAJAX()) {
            return $this->response->setStatusCode($code)->setJSON(
                ['status' => false, 'error' => $message, 'token' => $this->getToken()],
            );
        }

        throw $ex;
    }
}
