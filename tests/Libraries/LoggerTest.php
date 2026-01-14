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

namespace Tests\Libraries;

use Daycry\Auth\Entities\Endpoint;
use Daycry\Auth\Entities\Log;
use Daycry\Auth\Libraries\Logger;
use Daycry\Auth\Models\LogModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class LoggerTest extends DatabaseTestCase
{
    private Logger $logger;
    private Endpoint $endpoint;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test endpoint
        $this->endpoint = new Endpoint([
            'id'     => 1,
            'name'   => 'test_endpoint',
            'path'   => '/test',
            'method' => 'GET',
        ]);

        $this->logger = new Logger($this->endpoint);
    }

    public function testConstructorWithEndpoint(): void
    {
        $endpoint = new Endpoint([
            'id'     => 1,
            'name'   => 'test',
            'path'   => '/test',
            'method' => 'GET',
        ]);

        $logger = new Logger($endpoint);

        $this->assertInstanceOf(Logger::class, $logger);
    }

    public function testConstructorWithNullEndpoint(): void
    {
        $logger = new Logger(null);

        $this->assertInstanceOf(Logger::class, $logger);
    }

    public function testSetLogAuthorized(): void
    {
        $result = $this->logger->setLogAuthorized(true);

        // Should return self for chaining
        $this->assertInstanceOf(Logger::class, $result);
        $this->assertSame($this->logger, $result);

        // Test with false
        $result = $this->logger->setLogAuthorized(false);
        $this->assertInstanceOf(Logger::class, $result);
    }

    public function testSetAuthorized(): void
    {
        $result = $this->logger->setAuthorized(true);

        // Should return self for chaining
        $this->assertInstanceOf(Logger::class, $result);
        $this->assertSame($this->logger, $result);

        // Test with false
        $result = $this->logger->setAuthorized(false);
        $this->assertInstanceOf(Logger::class, $result);
    }

    public function testSetResponseCode(): void
    {
        $result = $this->logger->setResponseCode(200);

        // Should return self for chaining
        $this->assertInstanceOf(Logger::class, $result);
        $this->assertSame($this->logger, $result);

        // Test with different codes
        $result = $this->logger->setResponseCode(404);
        $this->assertInstanceOf(Logger::class, $result);

        $result = $this->logger->setResponseCode(500);
        $this->assertInstanceOf(Logger::class, $result);
    }

    public function testSave(): void
    {
        // Test saving a log entry
        $insertId = $this->logger
            ->setLogAuthorized(true)
            ->setAuthorized(true)
            ->setResponseCode(200)
            ->save();

        $this->assertGreaterThan(0, $insertId);
    }

    public function testSaveWithUnauthorized(): void
    {
        // Test saving unauthorized request
        $insertId = $this->logger
            ->setLogAuthorized(true)
            ->setAuthorized(false)
            ->setResponseCode(401)
            ->save();

        $this->assertGreaterThan(0, $insertId);
    }

    public function testSaveWithDifferentResponseCodes(): void
    {
        // Test saving with different response codes
        $codes = [200, 201, 400, 401, 403, 404, 500];

        foreach ($codes as $code) {
            $logger   = new Logger($this->endpoint);
            $insertId = $logger
                ->setLogAuthorized(true)
                ->setAuthorized($code < 400)
                ->setResponseCode($code)
                ->save();

            $this->assertGreaterThan(0, $insertId);
        }
    }

    public function testMethodChaining(): void
    {
        // Test that methods can be chained
        $insertId = $this->logger
            ->setLogAuthorized(true)
            ->setAuthorized(true)
            ->setResponseCode(200)
            ->save();

        $this->assertGreaterThan(0, $insertId);
    }

    public function testSaveWithLogAuthorizedFalse(): void
    {
        // Test saving with logAuthorized set to false
        $insertId = $this->logger
            ->setLogAuthorized(false)
            ->setAuthorized(true)
            ->setResponseCode(200)
            ->save();

        // Should return 0 when logging is disabled
        $this->assertSame(0, $insertId);
    }

    public function testMultipleSaves(): void
    {
        // Test multiple saves with same logger
        $insertId1 = $this->logger
            ->setLogAuthorized(true)
            ->setAuthorized(true)
            ->setResponseCode(200)
            ->save();

        $this->assertGreaterThan(0, $insertId1);

        // Save again with different values
        $insertId2 = $this->logger
            ->setLogAuthorized(true)
            ->setAuthorized(false)
            ->setResponseCode(401)
            ->save();

        $this->assertGreaterThan(0, $insertId2);

        // Should have different insert IDs
        $this->assertNotSame($insertId1, $insertId2);
    }

    public function testLogModelIntegration(): void
    {
        // Test that logger properly integrates with LogModel
        $insertId = $this->logger
            ->setLogAuthorized(true)
            ->setAuthorized(true)
            ->setResponseCode(200)
            ->save();

        $this->assertGreaterThan(0, $insertId);

        // Verify the log was actually saved in the database
        $logModel = new LogModel();
        $logEntry = $logModel->find($insertId);

        $this->assertInstanceOf(Log::class, $logEntry);
        $this->assertSame(200, $logEntry->response_code);
    }

    public function testBenchmarkIntegration(): void
    {
        // Test that benchmark/timer is properly integrated
        $this->logger
            ->setLogAuthorized(true)
            ->setAuthorized(true)
            ->setResponseCode(200);

        // Add a small delay to ensure timing
        usleep(1000); // 1ms

        $insertId = $this->logger->save();

        $this->assertGreaterThan(0, $insertId);
    }

    public function testSaveWithoutSettingValues(): void
    {
        // Test saving without explicitly setting values (should use defaults)
        $insertId = $this->logger->save();

        // Should return 0 because logAuthorized defaults to false
        $this->assertSame(0, $insertId);
    }

    public function testConstructorWithDifferentEndpoints(): void
    {
        $endpoints = [
            new Endpoint(['id' => 1, 'name' => 'endpoint1', 'path' => '/path1', 'method' => 'GET']),
            new Endpoint(['id' => 2, 'name' => 'endpoint2', 'path' => '/path2', 'method' => 'POST']),
            null,
        ];

        foreach ($endpoints as $endpoint) {
            $logger = new Logger($endpoint);
            $this->assertInstanceOf(Logger::class, $logger);

            // Test that each logger can save
            $insertId = $logger->setLogAuthorized(true)->setAuthorized(true)->setResponseCode(200)->save();
            $this->assertGreaterThan(0, $insertId);
        }
    }
}
