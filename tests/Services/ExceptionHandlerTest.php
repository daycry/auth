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

namespace Tests\Services;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Validation\Validation;
use Daycry\Auth\Services\ExceptionHandler;
use Daycry\Auth\Services\RequestLogger;
use Exception;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class ExceptionHandlerTest extends DatabaseTestCase
{
    private ExceptionHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new ExceptionHandler();
    }

    public function testConstructorInitializesRequestLogger(): void
    {
        $requestLogger = $this->handler->getRequestLogger();
        $this->assertInstanceOf(RequestLogger::class, $requestLogger);
    }

    public function testGetInstance(): void
    {
        $instance = ExceptionHandler::getInstance();
        $this->assertInstanceOf(ExceptionHandler::class, $instance);
    }

    public function testHandleExceptionWithAuthorizedProperty(): void
    {
        // Create an exception with authorized property
        $exception = new class ('Test exception') extends Exception {
            public bool $authorized = true;
        };

        $request = $this->createMock(IncomingRequest::class);
        $request->method('isAJAX')->willReturn(false);

        $response = $this->createMock(ResponseInterface::class);

        // Create a controller mock with fail method
        $controller = new class () {
            public function fail($message, $code): ResponseInterface
            {
                $response = service('response');

                return $response->setStatusCode($code)->setJSON(['error' => $message]);
            }
        };

        $result = $this->handler->handleException(
            $exception,
            $request,
            $response,
            null,
            $controller,
        );

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function testHandleExceptionWithoutAuthorizedProperty(): void
    {
        $exception = new Exception('Test exception without authorized property');

        $request = $this->createMock(IncomingRequest::class);
        $request->method('isAJAX')->willReturn(false);

        $response = $this->createMock(ResponseInterface::class);

        // Create a controller mock with fail method
        $controller = new class () {
            public function fail($message, $code): ResponseInterface
            {
                $response = service('response');

                return $response->setStatusCode($code)->setJSON(['error' => $message]);
            }
        };

        $result = $this->handler->handleException(
            $exception,
            $request,
            $response,
            null,
            $controller,
        );

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function testHandleExceptionWithValidationErrors(): void
    {
        $exception = new Exception('Validation failed');

        $validator = $this->createMock(Validation::class);
        $validator->method('getErrors')->willReturn(['field' => 'Error message']);

        $request = $this->createMock(IncomingRequest::class);
        $request->method('isAJAX')->willReturn(false);

        $response = $this->createMock(ResponseInterface::class);

        // Create a controller mock with fail method
        $controller = new class () {
            public function fail($message, $code): ResponseInterface
            {
                $response = service('response');

                return $response->setStatusCode($code)->setJSON(['error' => $message]);
            }
        };

        $result = $this->handler->handleException(
            $exception,
            $request,
            $response,
            $validator,
            $controller,
        );

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function testHandleExceptionAjaxRequest(): void
    {
        $exception = new Exception('AJAX exception');

        $request = $this->createMock(IncomingRequest::class);
        $request->method('isAJAX')->willReturn(true);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('setStatusCode')->willReturnSelf();
        $response->method('setJSON')->willReturnSelf();

        $result = $this->handler->handleException(
            $exception,
            $request,
            $response,
        );

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function testHandleExceptionWithoutControllerThrowsException(): void
    {
        $exception = new Exception('No controller exception');

        $request = $this->createMock(IncomingRequest::class);
        $request->method('isAJAX')->willReturn(false);

        $response = $this->createMock(ResponseInterface::class);

        // Expect the original exception to be re-thrown
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No controller exception');

        $this->handler->handleException(
            $exception,
            $request,
            $response,
        );
    }

    public function testHandleExceptionWithControllerWithoutFailMethod(): void
    {
        $exception = new Exception('Controller without fail');

        $request = $this->createMock(IncomingRequest::class);
        $request->method('isAJAX')->willReturn(false);

        $response = $this->createMock(ResponseInterface::class);

        // Controller without fail method
        $controller = new class () {
            public function someOtherMethod(): void
            {
            }
        };

        // Expect the original exception to be re-thrown
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Controller without fail');

        $this->handler->handleException(
            $exception,
            $request,
            $response,
            null,
            $controller,
        );
    }

    public function testHandleExceptionWithCustomExceptionCode(): void
    {
        $exception = new Exception('Custom code exception', 422);

        $request = $this->createMock(IncomingRequest::class);
        $request->method('isAJAX')->willReturn(false);

        $response = $this->createMock(ResponseInterface::class);

        // Create a controller mock with fail method
        $controller = new class () {
            public function fail($message, $code): ResponseInterface
            {
                $response = service('response');

                return $response->setStatusCode($code)->setJSON(['error' => $message, 'code' => $code]);
            }
        };

        $result = $this->handler->handleException(
            $exception,
            $request,
            $response,
            null,
            $controller,
        );

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function testRequestLoggerIntegration(): void
    {
        // Test that the exception handler properly integrates with request logger
        $requestLogger = $this->handler->getRequestLogger();

        // Test that request logger is properly initialized
        $this->assertInstanceOf(RequestLogger::class, $requestLogger);

        // Multiple calls should return the same instance
        $this->assertSame($requestLogger, $this->handler->getRequestLogger());
    }
}
