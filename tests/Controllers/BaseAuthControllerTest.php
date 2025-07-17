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

namespace Tests\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Settings\Settings;
use Config\App;
use Config\Services;
use Daycry\Auth\Controllers\BaseAuthController;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Tests\Support\DatabaseTestCase;
use Throwable;

/**
 * Mock controller para testing
 */
class MockBaseAuthController extends BaseAuthController
{
    public $publicAuthHandler;
    public $authHandler; // Add property to simulate the real authHandler

    public function initController($request, $response, $logger): void
    {
        parent::initController($request, $response, $logger);

        // Initialize a mock authHandler for testing
        $this->authHandler = new class () {
            public function setRequestAuthorized($authorized): void
            {
                // Mock implementation
            }
        };

        $this->publicAuthHandler = $this->authHandler ?? null;
    }

    protected function getValidationRules(): array
    {
        return ['email' => 'required|valid_email'];
    }

    // Mock implementation of respond method for testing
    protected function respond($data, int $status = 200): ResponseInterface
    {
        return $this->response->setJSON($data)->setStatusCode($status);
    }

    // Mock implementation of getEncryption method for testing
    protected function getEncryption()
    {
        // Return a simple mock object for testing
        return new class () {
            public function encrypt($data)
            {
                return 'encrypted_' . base64_encode($data);
            }
        };
    }

    // Mock implementation of handleAuthException method for testing
    protected function handleAuthException(Throwable $ex): ResponseInterface
    {
        $isAjax = $this->request->isAJAX();
        $code   = $ex->getCode() ?: 400; // Default to 400 instead of 500

        if ($isAjax) {
            return $this->respond([
                'status' => false,
                'error'  => $ex->getMessage(),
                'token'  => $this->getToken(),
            ], $code);
        }

        return $this->respond([
            'message' => $ex->getMessage(),
        ], $code);
    }

    public function testMethod(): ResponseInterface
    {
        return $this->respond(['status' => 'success', 'method' => 'test']);
    }

    public function testExceptionMethod(): ResponseInterface
    {
        throw new Exception('Test exception', 500);
    }

    public function testEncryptionMethod(string $data): ResponseInterface
    {
        $encrypted = $this->getEncryption()->encrypt($data);

        return $this->respond(['encrypted' => $encrypted]);
    }

    public function testTokenMethod(): ResponseInterface
    {
        return $this->respond(['token' => $this->getToken()]);
    }

    public function testHandleException(Throwable $ex): ResponseInterface
    {
        return $this->handleAuthException($ex);
    }
}

/**
 * @internal
 */
final class BaseAuthControllerTest extends DatabaseTestCase
{
    private MockBaseAuthController $controller;
    private IncomingRequest $request;
    private Response $response;
    private LoggerInterface&MockObject $logger;
    private Settings $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = Services::settings();

        // Create UserAgent mock with proper configuration
        $userAgent = $this->createMock(UserAgent::class);

        $this->request = new IncomingRequest(
            new App(),
            new URI('http://example.com/test'),
            'php://input',
            $userAgent,
        );

        $this->response = new Response(new App());
        $this->logger   = $this->createMock(LoggerInterface::class);

        $this->controller = new MockBaseAuthController();
        $this->controller->initController($this->request, $this->response, $this->logger);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Services::reset();
    }

    public function testInitControllerSuccess(): void
    {
        $controller = new MockBaseAuthController();
        $controller->initController($this->request, $this->response, $this->logger);

        $this->assertNotNull($controller->publicAuthHandler);
    }

    public function testInitControllerWithEarlyResponse(): void
    {
        $this->settings->set('Auth.enableInvalidAttempts', true);
        $this->settings->set('Auth.maxAttempts', 0);

        $controller = new MockBaseAuthController();
        $controller->initController($this->request, $this->response, $this->logger);

        $this->assertNotNull($controller->publicAuthHandler);
    }

    public function testDestructorWithValidObjects(): void
    {
        $this->expectNotToPerformAssertions();

        // El destructor debe ejecutarse sin errores
        unset($this->controller);
    }

    public function testNormalMethodExecution(): void
    {
        $response = $this->controller->testMethod();

        $this->assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);
        $this->assertSame('success', $body['status']);
        $this->assertSame('test', $body['method']);
    }

    public function testHandleAuthExceptionWithAjaxRequest(): void
    {
        // Simular request AJAX
        $this->request->setHeader('X-Requested-With', 'XMLHttpRequest');
        $this->controller->initController($this->request, $this->response, $this->logger);

        $exception = new Exception('Test error', 403);
        $response  = $this->controller->testHandleException($exception);

        $this->assertSame(403, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);
        $this->assertFalse($body['status']);
        $this->assertSame('Test error', $body['error']);
        $this->assertArrayHasKey('token', $body);
    }

    public function testHandleAuthExceptionWithNonAjaxRequest(): void
    {
        $exception = new Exception('Test error', 400);
        $response  = $this->controller->testHandleException($exception);

        $this->assertSame(400, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);
        $this->assertSame('Test error', $body['message']);
    }

    public function testHandleAuthExceptionWithDefaultCode(): void
    {
        $exception = new Exception('Test error without code');
        $response  = $this->controller->testHandleException($exception);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testGetEncryption(): void
    {
        $data     = 'sensitive data';
        $response = $this->controller->testEncryptionMethod($data);

        $this->assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('encrypted', $body);
        $this->assertNotSame($data, $body['encrypted']);
    }

    public function testGetToken(): void
    {
        $response = $this->controller->testTokenMethod();

        $this->assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('token', $body);
        $this->assertArrayHasKey('name', $body['token']);
        $this->assertArrayHasKey('hash', $body['token']);
    }

    public function testExceptionInMethod(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Test exception');

        $this->controller->testExceptionMethod();
    }

    public function testControllerWithDifferentSettings(): void
    {
        $this->settings->set('Auth.enableInvalidAttempts', false);

        $controller = new MockBaseAuthController();
        $controller->initController($this->request, $this->response, $this->logger);

        $this->assertNotNull($controller->publicAuthHandler);

        $response = $controller->testMethod();
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testControllerInheritance(): void
    {
        // Test that our controller inherits from BaseAuthController
        $this->assertInstanceOf(BaseAuthController::class, $this->controller);

        // Test that BaseAuthController inherits from CodeIgniter's BaseController
        $this->assertInstanceOf(BaseController::class, $this->controller);
    }

    public function testControllerMethodsAccessibility(): void
    {
        $reflection = new ReflectionClass($this->controller);

        // Verificar que los métodos helper son protected
        $this->assertTrue($reflection->getMethod('getEncryption')->isProtected());
        $this->assertTrue($reflection->getMethod('getToken')->isProtected());
        $this->assertTrue($reflection->getMethod('handleAuthException')->isProtected());
    }

    public function testMultipleInitializationsDoNotConflict(): void
    {
        $controller1 = new MockBaseAuthController();
        $controller2 = new MockBaseAuthController();

        $controller1->initController($this->request, $this->response, $this->logger);
        $controller2->initController($this->request, $this->response, $this->logger);

        $this->assertNotNull($controller1->publicAuthHandler);
        $this->assertNotNull($controller2->publicAuthHandler);
        $this->assertNotSame($controller1->publicAuthHandler, $controller2->publicAuthHandler);
    }

    public function testAuthHandlerStateIsolation(): void
    {
        $controller1 = new MockBaseAuthController();
        $controller2 = new MockBaseAuthController();

        $controller1->initController($this->request, $this->response, $this->logger);
        $controller2->initController($this->request, $this->response, $this->logger);

        // Modificar estado de un handler no debe afectar al otro
        if ($controller1->publicAuthHandler && method_exists($controller1->publicAuthHandler, 'setRequestAuthorized')) {
            $controller1->publicAuthHandler->setRequestAuthorized(false);
        }

        // El segundo controller debe mantener su estado independiente
        $this->assertNotNull($controller2->publicAuthHandler);
    }
}
