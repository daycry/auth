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

namespace Tests\Extensibility;

use Config\Services;
use Daycry\Auth\Entities\AccessToken;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Interfaces\AccessTokenRepositoryInterface;
use Daycry\Auth\Interfaces\AuditLoggerInterface;
use Daycry\Auth\Models\AccessTokenRepository;
use Daycry\Auth\Services\AuditLogger;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class CoreContractsTest extends TestCase
{
    public function testUserAcceptsARepositoryThatDoesNotInheritTheDefaultImplementation(): void
    {
        $user       = new User(['id' => 42]);
        $token      = new AccessToken(['name' => 'integration']);
        $repository = $this->createMock(AccessTokenRepositoryInterface::class);
        $repository->expects($this->once())->method('generateAccessToken')->with($user, 'integration', ['read'])->willReturn($token);
        Services::injectMock('accessTokenRepository', $repository);

        $this->assertNotInstanceOf(AccessTokenRepository::class, $repository);
        $this->assertSame($token, $user->generateAccessToken('integration', ['read']));
    }

    public function testAuditServiceAcceptsAnIndependentImplementation(): void
    {
        $logger = $this->createMock(AuditLoggerInterface::class);
        $logger->expects($this->once())->method('record')->with('custom_event', 42, ['source' => 'application'], null);
        Services::injectMock('auditLogger', $logger);

        $this->assertNotInstanceOf(AuditLogger::class, $logger);
        service('auditLogger')->record('custom_event', 42, ['source' => 'application']);
    }
}
