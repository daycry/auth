<?php

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Daycry\Auth\Traits;

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
use Daycry\Exceptions\Interfaces\BaseExceptionInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionProperty;

trait BaseControllerTrait
{
    use Validation;

    protected Router $router;
    protected ?Logger $_logger = null;
    protected Encryption $encryption;

    /**
     * The authorization Request
     */
    private bool $_isRequestAuthorized = true;

    protected array $args;
    protected mixed $content = null;

    /**
     * Extend this function to apply additional checking early on in the process.
     */
    protected function earlyChecks(): void
    {
    }

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
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
        $this->content = (! empty($this->args['body'])) ? $this->args['body'] : null;

        // Extend this function to apply additional checking early on in the process
        $this->earlyChecks();
    }

    /**
     * De-constructor.
     *
     * @return void
     */
    public function __destruct()
    {
        if ($this->request) {
            $this->_logRequest();

            if (service('settings')->get('Auth.enableInvalidAttempts') === true) {
                $attemptModel = new AttemptModel();
                $attempt      = $attemptModel->where('ip_address', $this->request->getIPAddress())->first();

                if ($this->_isRequestAuthorized === false) {
                    if ($attempt === null) {
                        $attempt = [
                            'user_id'      => (auth()->user()) ? auth()->user()->id : null,
                            'ip_address'   => $this->request->getIPAddress(),
                            'attempts'     => 1,
                            'hour_started' => time(),
                        ];

                        $attemptModel->save($attempt);
                    } else {
                        if ($attempt->attempts < service('settings')->get('Auth.maxAttempts')) {
                            $attempt->attempts++;
                            $attemptModel->save($attempt);
                        }
                    }
                }
            }
        }

        // reset previous validation at end
        if ($this->validator) {
            $this->validator->reset();
        }
    }

    /**
     * Add the request to the log table.
     */
    protected function _logRequest()
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

    /**
     * Requests are not made to methods directly, the request will be for
     * an "object". This simply maps the object and method to the correct
     * Controller method.
     *
     * @param array $params The params passed to the controller method
     *
     * @throws BaseExceptionInterface
     */
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
        } catch (BaseExceptionInterface $ex) {
            if (property_exists($ex, 'authorized')) {
                $this->_isRequestAuthorized = (new ReflectionProperty($ex, 'authorized'))->getValue();
            }

            $message = ($this->validator && $this->validator->getErrors()) ? $this->validator->getErrors() : $ex->getMessage();
            $code    = ($ex->getCode()) ?: 400;

            if (method_exists($this, 'fail')) {
                return $this->fail($message, $code);
            }

            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode($code)->setJSON(
                    ['status' => false, 'error' => $message, 'token' => $this->_getToken()]
                );
            }

            throw $ex;
        }
    }
}
