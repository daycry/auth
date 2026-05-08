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
use CodeIgniter\I18n\Time;
use Daycry\Auth\Entities\Attempt;
use Daycry\Auth\Models\AttemptModel;
use Daycry\Auth\Services\AttemptHandler;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class AttemptHandlerTest extends DatabaseTestCase
{
    private function makeRequest(string $ip = '203.0.113.7'): IncomingRequest
    {
        /** @var IncomingRequest&MockObject $request */
        $request = $this->createMock(IncomingRequest::class);
        $request->method('getIPAddress')->willReturn($ip);

        return $request;
    }

    private function enableAttempts(int $maxAttempts = 5): void
    {
        $this->injectMockAttributesSecurity([
            'enableInvalidAttempts' => true,
            'maxAttempts'           => $maxAttempts,
        ]);
    }

    public function testIsEnabledReflectsSecuritySetting(): void
    {
        $this->enableAttempts();

        $handler = new AttemptHandler();
        $this->assertTrue($handler->isEnabled());
    }

    public function testIsDisabledByDefault(): void
    {
        $handler = new AttemptHandler();
        $this->assertFalse($handler->isEnabled());
    }

    public function testValidateAttemptsIsNoOpWhenDisabled(): void
    {
        $handler = new AttemptHandler();
        $handler->validateAttempts($this->createStub(ResponseInterface::class));
        $this->expectNotToPerformAssertions();
    }

    public function testHandleInvalidAttemptIsNoOpWhenDisabled(): void
    {
        $handler = new AttemptHandler();
        $handler->handleInvalidAttempt($this->makeRequest());

        $this->assertSame(0, model(AttemptModel::class)->countAll());
    }

    public function testHandleInvalidAttemptCreatesRowOnFirstFailure(): void
    {
        $this->enableAttempts();

        $handler = new AttemptHandler();
        $handler->handleInvalidAttempt($this->makeRequest('203.0.113.7'));

        $rows = model(AttemptModel::class)->where('ip_address', '203.0.113.7')->findAll();
        $this->assertCount(1, $rows);
        $this->assertSame(1, (int) $rows[0]->attempts);
    }

    public function testHandleInvalidAttemptIncrementsExistingRow(): void
    {
        $this->enableAttempts();

        // Seed an existing attempt row directly so the handler hits the
        // increment branch (which requires the row's `attempts` column to be
        // below the configured max).
        model(AttemptModel::class)->insert([
            'ip_address'      => '203.0.113.8',
            'attempts'        => 1,
            'hour_started_at' => Time::now()->toDateTimeString(),
        ]);

        $handler = new AttemptHandler();
        $handler->handleInvalidAttempt($this->makeRequest('203.0.113.8'));
        $handler->handleInvalidAttempt($this->makeRequest('203.0.113.8'));

        $row = model(AttemptModel::class)->where('ip_address', '203.0.113.8')->first();
        $this->assertInstanceOf(Attempt::class, $row);
        $this->assertSame(3, (int) $row->attempts, 'each call after the first should increment');
    }

    public function testHandleInvalidAttemptStopsIncrementingAtMax(): void
    {
        $this->enableAttempts(2);

        // Seed an existing attempt row already at the cap.
        model(AttemptModel::class)->insert([
            'ip_address'      => '203.0.113.9',
            'attempts'        => 2,
            'hour_started_at' => Time::now()->toDateTimeString(),
        ]);

        $handler = new AttemptHandler();
        $handler->handleInvalidAttempt($this->makeRequest('203.0.113.9')); // already at max → no-op

        $row = model(AttemptModel::class)->where('ip_address', '203.0.113.9')->first();
        $this->assertSame(2, (int) $row->attempts);
    }

    public function testGetInstanceReturnsFreshInstance(): void
    {
        $a = AttemptHandler::getInstance();
        $b = AttemptHandler::getInstance();

        $this->assertInstanceOf(AttemptHandler::class, $a);
        $this->assertNotSame($a, $b, 'getInstance() returns a new instance each time');
    }
}
