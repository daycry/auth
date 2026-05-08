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

use Daycry\Auth\Entities\AuditLog;
use Daycry\Auth\Models\AuditLogModel;
use Daycry\Auth\Services\AuditLogger;
use ReflectionClass;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class AuditLoggerTest extends DatabaseTestCase
{
    private AuditLogger $logger;
    private AuditLogModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = new AuditLogger();
        $this->model  = model(AuditLogModel::class);
    }

    public function testRecordPersistsRow(): void
    {
        $this->logger->record(AuditLogger::EVENT_TOTP_ENABLED, 7);

        $row = $this->model->where('user_id', 7)->first();

        $this->assertInstanceOf(AuditLog::class, $row);
        $this->assertSame('totp.enabled', $row->event_type);
        $this->assertSame(7, (int) $row->user_id);
        // Without an actorId argument, actor defaults to user.
        $this->assertSame(7, (int) $row->actor_id);
    }

    public function testRecordEncodesMetadataAsJson(): void
    {
        $this->logger->record(
            AuditLogger::EVENT_PASSWORD_CHANGED,
            42,
            ['source' => 'profile_form', 'ip' => '203.0.113.1'],
        );

        $row = $this->model->where('user_id', 42)->first();

        $this->assertInstanceOf(AuditLog::class, $row);
        $decoded = $row->getMetadata();
        $this->assertSame('profile_form', $decoded['source']);
        $this->assertSame('203.0.113.1', $decoded['ip']);
    }

    public function testRecordWithoutMetadataLeavesNull(): void
    {
        $this->logger->record(AuditLogger::EVENT_TOTP_DISABLED, 99);

        $row = $this->model->where('user_id', 99)->first();

        $this->assertInstanceOf(AuditLog::class, $row);
        // Entity exposes getMetadata() which decodes null → empty array.
        $this->assertSame([], $row->getMetadata());
    }

    public function testRecordHonoursExplicitActorId(): void
    {
        // Admin (actor 1) resets TOTP for user 7.
        $this->logger->record(
            AuditLogger::EVENT_TOTP_ADMIN_RESET,
            7,
            ['initiator' => 'cli'],
            actorId: 1,
        );

        $row = $this->model->where('user_id', 7)->first();

        $this->assertInstanceOf(AuditLog::class, $row);
        $this->assertSame(7, (int) $row->user_id);
        $this->assertSame(1, (int) $row->actor_id);
    }

    public function testRecordWithNullUserIdAllowed(): void
    {
        // System-level events (no user) — actor defaults to user (also null).
        $this->logger->record('system.startup', null, ['version' => '5.1.0']);

        $row = $this->model->where('event_type', 'system.startup')->first();

        $this->assertInstanceOf(AuditLog::class, $row);
        $this->assertNull($row->user_id);
        $this->assertNull($row->actor_id);
    }

    public function testEventConstantsExposeCanonicalIdentifiers(): void
    {
        // Sanity-check the constant set — we want the strings to be stable
        // across releases (they end up as event_type filter keys). Looking
        // them up via reflection avoids "always-true" string-vs-string
        // assertions that PHPStan rightly flags.
        $reflection = new ReflectionClass(AuditLogger::class);
        $constants  = $reflection->getConstants();

        $expected = [
            'EVENT_TOTP_ENABLED'          => 'totp.enabled',
            'EVENT_TOTP_DISABLED'         => 'totp.disabled',
            'EVENT_TOTP_ADMIN_RESET'      => 'totp.admin_reset',
            'EVENT_PASSWORD_CHANGED'      => 'password.changed',
            'EVENT_PASSWORD_RESET'        => 'password.reset',
            'EVENT_PASSWORD_CONFIRMED'    => 'password.confirmed',
            'EVENT_USER_LOCKED'           => 'user.locked',
            'EVENT_USER_UNLOCKED'         => 'user.unlocked',
            'EVENT_GROUP_ASSIGNED'        => 'group.assigned',
            'EVENT_GROUP_REVOKED'         => 'group.revoked',
            'EVENT_PERMISSION_GRANTED'    => 'permission.granted',
            'EVENT_PERMISSION_REVOKED'    => 'permission.revoked',
            'EVENT_TOKEN_REVOKED'         => 'token.revoked',
            'EVENT_REFRESH_TOKEN_REVOKED' => 'token.refresh_revoked',
            'EVENT_SUSPICIOUS_LOGIN'      => 'login.suspicious',
            'EVENT_USER_ANONYMIZED'       => 'user.anonymized',
        ];

        foreach ($expected as $name => $value) {
            $this->assertArrayHasKey($name, $constants);
            $this->assertSame($value, $constants[$name]);
        }
    }
}
