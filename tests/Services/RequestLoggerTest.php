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

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Router\Router;
use Config\Services;
use Daycry\Auth\Libraries\Logger;
use Daycry\Auth\Services\RequestLogger;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class RequestLoggerTest extends DatabaseTestCase
{
    private RequestLogger $requestLogger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requestLogger = new RequestLogger();
    }

    public function testConstructorInitializesProperties(): void
    {
        // Test that constructor properly initializes the request logger
        $this->assertInstanceOf(RequestLogger::class, $this->requestLogger);

        // Test default authorization status
        $this->assertTrue($this->requestLogger->isRequestAuthorized());
    }

    public function testSetAndGetRequestAuthorized(): void
    {
        // Test setting request as unauthorized
        $result = $this->requestLogger->setRequestAuthorized(false);

        // Method should return self for chaining
        $this->assertInstanceOf(RequestLogger::class, $result);
        $this->assertSame($this->requestLogger, $result);

        // Test getting authorization status
        $this->assertFalse($this->requestLogger->isRequestAuthorized());

        // Test setting back to authorized
        $this->requestLogger->setRequestAuthorized(true);
        $this->assertTrue($this->requestLogger->isRequestAuthorized());
    }

    public function testGetInstance(): void
    {
        $instance = RequestLogger::getInstance();
        $this->assertInstanceOf(RequestLogger::class, $instance);

        // Each call should return a new instance
        $instance2 = RequestLogger::getInstance();
        $this->assertInstanceOf(RequestLogger::class, $instance2);
        $this->assertNotSame($instance, $instance2);
    }

    public function testLogRequestWithValidController(): void
    {
        // Mock response
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        // Set authorization status
        $this->requestLogger->setRequestAuthorized(false);

        // Test logging (this will use the actual router and logger services)
        $this->requestLogger->logRequest($response);

        // If we reach here without exceptions, the method works
        $this->assertTrue(true);
    }

    public function testLogRequestWithDifferentStatusCodes(): void
    {
        // Test with different HTTP status codes
        $statusCodes = [200, 401, 403, 404, 500];

        foreach ($statusCodes as $statusCode) {
            $response = $this->createMock(ResponseInterface::class);
            $response->method('getStatusCode')->willReturn($statusCode);

            $this->requestLogger->logRequest($response);

            // If we reach here without exceptions, the method works for all codes
            $this->assertTrue(true);
        }
    }

    public function testLogRequestWithAuthorizedRequest(): void
    {
        // Mock response
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        // Set as authorized (default state)
        $this->requestLogger->setRequestAuthorized(true);

        // Test logging authorized request
        $this->requestLogger->logRequest($response);

        // Verify state is still authorized
        $this->assertTrue($this->requestLogger->isRequestAuthorized());
    }

    public function testLogRequestWithUnauthorizedRequest(): void
    {
        // Mock response
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(401);

        // Set as unauthorized
        $this->requestLogger->setRequestAuthorized(false);

        // Test logging unauthorized request
        $this->requestLogger->logRequest($response);

        // Verify state is still unauthorized
        $this->assertFalse($this->requestLogger->isRequestAuthorized());
    }

    public function testChainedMethodCalls(): void
    {
        // Test method chaining
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $result = $this->requestLogger
            ->setRequestAuthorized(false)
            ->setRequestAuthorized(true);

        $this->assertInstanceOf(RequestLogger::class, $result);
        $this->assertTrue($this->requestLogger->isRequestAuthorized());
    }

    public function testMultipleLogRequests(): void
    {
        // Test multiple consecutive log requests
        $response1 = $this->createMock(ResponseInterface::class);
        $response1->method('getStatusCode')->willReturn(200);

        $response2 = $this->createMock(ResponseInterface::class);
        $response2->method('getStatusCode')->willReturn(401);

        // Log multiple requests with different states
        $this->requestLogger->setRequestAuthorized(true);
        $this->requestLogger->logRequest($response1);

        $this->requestLogger->setRequestAuthorized(false);
        $this->requestLogger->logRequest($response2);

        // Verify final state
        $this->assertFalse($this->requestLogger->isRequestAuthorized());
    }

    public function testAuthorizationStatesPersistAcrossLogCalls(): void
    {
        // Set initial state
        $this->requestLogger->setRequestAuthorized(false);

        // Create multiple responses
        $responses = [];

        for ($i = 0; $i < 3; $i++) {
            $response = $this->createMock(ResponseInterface::class);
            $response->method('getStatusCode')->willReturn(200);
            $responses[] = $response;
        }

        // Log multiple requests without changing authorization
        foreach ($responses as $response) {
            $this->requestLogger->logRequest($response);
            // State should remain the same
            $this->assertFalse($this->requestLogger->isRequestAuthorized());
        }
    }

    public function testDefaultState(): void
    {
        // Test that a new RequestLogger starts with authorized state
        $newLogger = new RequestLogger();
        $this->assertTrue($newLogger->isRequestAuthorized());
    }
}
