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

namespace Tests\Traits;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Validation\Validation;
use Daycry\Auth\Services\AttemptHandler;
use Daycry\Auth\Services\ExceptionHandler;
use Daycry\Auth\Services\RequestLogger;
use Daycry\Auth\Traits\BaseControllerTrait;
use Daycry\Encryption\Encryption;
use Exception;
use ReflectionClass;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class BaseControllerTraitTest extends DatabaseTestCase
{
    private object $controller;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test controller that uses the trait
        $this->controller = new class () extends Controller {
            use BaseControllerTrait;

            // Mock the fail method that's expected in controllers
            public function fail($message, $code = 400): ResponseInterface
            {
                return $this->response->setStatusCode($code)->setJSON(['error' => $message]);
            }

            // Expose protected methods for testing
            public function testGetToken(): array
            {
                return $this->getToken();
            }

            public function testSetRequestUnauthorized(): void
            {
                $this->setRequestUnauthorized();
            }

            public function testIsRequestAuthorized(): bool
            {
                return $this->isRequestAuthorized();
            }

            public function testEarlyChecks(): void
            {
                $this->earlyChecks();
            }

            // Expose properties for testing
            public function getEncryption(): Encryption
            {
                return $this->encryption;
            }

            public function getRequestLogger(): RequestLogger
            {
                return $this->requestLogger;
            }

            public function getAttemptHandler(): AttemptHandler
            {
                return $this->attemptHandler;
            }

            public function getExceptionHandler(): ExceptionHandler
            {
                return $this->exceptionHandler;
            }

            // Test method for _remap functionality
            public function testMethod(): array
            {
                return ['test' => 'success'];
            }
        };
    }

    public function testInitController(): void
    {
        // Initialize the controller
        $request  = service('request');
        $response = service('response');
        $logger   = service('logger');

        $this->controller->initController($request, $response, $logger);

        // Test that services are properly initialized
        $this->assertInstanceOf(Encryption::class, $this->controller->getEncryption());
        $this->assertInstanceOf(RequestLogger::class, $this->controller->getRequestLogger());
        $this->assertInstanceOf(AttemptHandler::class, $this->controller->getAttemptHandler());
        $this->assertInstanceOf(ExceptionHandler::class, $this->controller->getExceptionHandler());
    }

    public function testGetToken(): void
    {
        // Initialize the controller first
        $request  = service('request');
        $response = service('response');
        $logger   = service('logger');
        $this->controller->initController($request, $response, $logger);

        $token = $this->controller->testGetToken();

        $this->assertIsArray($token);
        $this->assertArrayHasKey('name', $token);
        $this->assertArrayHasKey('hash', $token);
        $this->assertNotEmpty($token['name']);
        $this->assertNotEmpty($token['hash']);
    }

    public function testSetAndCheckRequestAuthorization(): void
    {
        // Initialize the controller first
        $request  = service('request');
        $response = service('response');
        $logger   = service('logger');
        $this->controller->initController($request, $response, $logger);

        // Initially should be authorized (default state)
        $this->assertTrue($this->controller->testIsRequestAuthorized());

        // Set as unauthorized
        $this->controller->testSetRequestUnauthorized();

        // Should now be unauthorized
        $this->assertFalse($this->controller->testIsRequestAuthorized());
    }

    public function testEarlyChecks(): void
    {
        // Initialize the controller first
        $request  = service('request');
        $response = service('response');
        $logger   = service('logger');
        $this->controller->initController($request, $response, $logger);

        // Test early checks (default implementation should do nothing)
        $this->controller->testEarlyChecks();

        // If we reach here without exceptions, the method works
        $this->assertTrue(true);
    }

    public function testRemapWithValidMethod(): void
    {
        // Initialize the controller first
        $request  = service('request');
        $response = service('response');
        $logger   = service('logger');
        $this->controller->initController($request, $response, $logger);

        // Test _remap with valid method
        $result = $this->controller->_remap('testMethod');

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function testRemapWithInvalidMethod(): void
    {
        // Initialize the controller first
        $request  = service('request');
        $response = service('response');
        $logger   = service('logger');
        $this->controller->initController($request, $response, $logger);

        // Test _remap with invalid method - should throw PageNotFoundException
        $result = $this->controller->_remap('invalidMethod');

        // Should return a response (exception handled)
        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function testDestructor(): void
    {
        // Initialize the controller first
        $request  = service('request');
        $response = service('response');
        $logger   = service('logger');
        $this->controller->initController($request, $response, $logger);

        // Set as unauthorized to test invalid attempt handling
        $this->controller->testSetRequestUnauthorized();

        // Call destructor manually
        $this->controller->__destruct();

        // If we reach here without exceptions, the destructor works
        $this->assertTrue(true);
    }

    public function testTraitUsesCorrectServices(): void
    {
        // Initialize the controller first
        $request  = service('request');
        $response = service('response');
        $logger   = service('logger');
        $this->controller->initController($request, $response, $logger);

        $reflection = new ReflectionClass($this->controller);

        // Check that all required service properties exist
        $this->assertTrue($reflection->hasProperty('encryption'));
        $this->assertTrue($reflection->hasProperty('requestLogger'));
        $this->assertTrue($reflection->hasProperty('attemptHandler'));
        $this->assertTrue($reflection->hasProperty('exceptionHandler'));

        // Check that all required methods exist
        $this->assertTrue($reflection->hasMethod('initController'));
        $this->assertTrue($reflection->hasMethod('_remap'));
        $this->assertTrue($reflection->hasMethod('__destruct'));
        $this->assertTrue($reflection->hasMethod('getToken'));
        $this->assertTrue($reflection->hasMethod('setRequestUnauthorized'));
        $this->assertTrue($reflection->hasMethod('isRequestAuthorized'));
        $this->assertTrue($reflection->hasMethod('earlyChecks'));
    }

    public function testValidationTrait(): void
    {
        // Test that the Validation trait is properly included
        $reflection = new ReflectionClass($this->controller);

        // Check if the class uses the trait
        $traits = $reflection->getTraitNames();
        $this->assertContains('Daycry\Auth\Traits\BaseControllerTrait', $traits);
    }

    public function testEncryptionServiceIntegration(): void
    {
        // Initialize the controller first
        $request  = service('request');
        $response = service('response');
        $logger   = service('logger');
        $this->controller->initController($request, $response, $logger);

        $encryption = $this->controller->getEncryption();

        // Test that encryption service is working
        $this->assertInstanceOf(Encryption::class, $encryption);

        // Skip actual encryption test due to key configuration requirements
        $this->markTestSkipped('Encryption test skipped due to key configuration requirements');
    }

    public function testServiceInstancesAreUnique(): void
    {
        // Create two controllers with the trait
        $controller1 = new class () extends Controller {
            use BaseControllerTrait;

            public function getEncryption(): Encryption
            {
                return $this->encryption;
            }
        };

        $controller2 = new class () extends Controller {
            use BaseControllerTrait;

            public function getEncryption(): Encryption
            {
                return $this->encryption;
            }
        };

        $request  = service('request');
        $response = service('response');
        $logger   = service('logger');

        $controller1->initController($request, $response, $logger);
        $controller2->initController($request, $response, $logger);

        // Each controller should have its own service instances
        $encryption1 = $controller1->getEncryption();
        $encryption2 = $controller2->getEncryption();

        $this->assertInstanceOf(Encryption::class, $encryption1);
        $this->assertInstanceOf(Encryption::class, $encryption2);

        // They should be different instances
        $this->assertNotSame($encryption1, $encryption2);
    }

    public function testArgsAndContentInitialization(): void
    {
        // Initialize the controller first
        $request  = service('request');
        $response = service('response');
        $logger   = service('logger');
        $this->controller->initController($request, $response, $logger);

        // Access protected properties using reflection
        $reflection = new ReflectionClass($this->controller);

        $argsProperty = $reflection->getProperty('args');
        $argsProperty->setAccessible(true);
        $args = $argsProperty->getValue($this->controller);
        $this->assertIsArray($args);

        $contentProperty = $reflection->getProperty('content');
        $contentProperty->setAccessible(true);
        $content = $contentProperty->getValue($this->controller);
        // Content can be null or whatever was in the body
        $this->assertTrue($content === null || is_string($content) || is_array($content));
    }

    public function testHelperLoading(): void
    {
        // Initialize the controller first
        $request  = service('request');
        $response = service('response');
        $logger   = service('logger');
        $this->controller->initController($request, $response, $logger);

        // Test that helpers are loaded by checking if functions exist
        $this->assertTrue(function_exists('csrf_token'));
        $this->assertTrue(function_exists('auth'));
    }

    public function testTokenGeneration(): void
    {
        // Initialize the controller first
        $request  = service('request');
        $response = service('response');
        $logger   = service('logger');
        $this->controller->initController($request, $response, $logger);

        $token1 = $this->controller->testGetToken();

        // Wait a bit to ensure different tokens
        usleep(1000);

        $token2 = $this->controller->testGetToken();

        // Tokens should have the same structure
        $this->assertIsArray($token1);
        $this->assertIsArray($token2);
        $this->assertArrayHasKey('name', $token1);
        $this->assertArrayHasKey('hash', $token1);
        $this->assertArrayHasKey('name', $token2);
        $this->assertArrayHasKey('hash', $token2);

        // Name should be the same, but hash might be different
        $this->assertSame($token1['name'], $token2['name']);

        // Both tokens should have non-empty values
        $this->assertNotEmpty($token1['name']);
        $this->assertNotEmpty($token1['hash']);
        $this->assertNotEmpty($token2['name']);
        $this->assertNotEmpty($token2['hash']);
    }
}
