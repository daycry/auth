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

namespace Tests\Models;

use CodeIgniter\I18n\Time;
use Daycry\Auth\Entities\AuditLog;
use Daycry\Auth\Models\AuditLogModel;
use Daycry\Auth\Services\AuditLogger;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class AuditLogModelTest extends DatabaseTestCase
{
    private AuditLogModel $audit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->audit = model(AuditLogModel::class);
    }

    public function testRecentForUserReturnsNewestFirst(): void
    {
        $logger = new AuditLogger();

        // Newer entry stamped first so we can verify ordering.
        $logger->record(AuditLogger::EVENT_TOTP_ENABLED, 1);
        $logger->record(AuditLogger::EVENT_PASSWORD_CHANGED, 1);
        $logger->record(AuditLogger::EVENT_TOKEN_REVOKED, 1);

        $rows = $this->audit->recentForUser(1, 50);

        $this->assertCount(3, $rows);
        $this->assertContainsOnlyInstancesOf(AuditLog::class, $rows);
        // Reverse-chronological — last inserted first.
        $this->assertSame(AuditLogger::EVENT_TOKEN_REVOKED, $rows[0]->event_type);
    }

    public function testRecentForUserHonoursLimit(): void
    {
        $logger = new AuditLogger();

        for ($i = 0; $i < 5; $i++) {
            $logger->record('test.event', 9);
        }

        $rows = $this->audit->recentForUser(9, 2);

        $this->assertCount(2, $rows);
    }

    public function testRecentByTypeFiltersAndOrders(): void
    {
        $logger = new AuditLogger();

        $logger->record(AuditLogger::EVENT_PASSWORD_CHANGED, 1);
        $logger->record(AuditLogger::EVENT_TOTP_ENABLED, 1);
        $logger->record(AuditLogger::EVENT_PASSWORD_CHANGED, 2);

        $rows = $this->audit->recentByType(AuditLogger::EVENT_PASSWORD_CHANGED, null, 100);

        $this->assertCount(2, $rows);

        foreach ($rows as $row) {
            $this->assertSame(AuditLogger::EVENT_PASSWORD_CHANGED, $row->event_type);
        }
    }

    public function testRecentByTypeRespectsSinceCutoff(): void
    {
        $logger = new AuditLogger();
        $logger->record(AuditLogger::EVENT_TOTP_ENABLED, 5);

        $rows = $this->audit->recentByType(
            AuditLogger::EVENT_TOTP_ENABLED,
            Time::now()->addDays(1), // future cutoff → nothing matches
            100,
        );

        $this->assertSame([], $rows);
    }

    public function testEntityDecodesMetadataWithGetter(): void
    {
        $logger = new AuditLogger();
        $logger->record(AuditLogger::EVENT_TRUSTED_DEVICE_ADDED, 3, [
            'device_uuid' => 'abc-123',
            'lifetime'    => 2592000,
        ]);

        $row = $this->audit->where('user_id', 3)->first();

        $this->assertInstanceOf(AuditLog::class, $row);
        $decoded = $row->getMetadata();
        $this->assertSame('abc-123', $decoded['device_uuid']);
        $this->assertSame(2592000, $decoded['lifetime']);
    }

    public function testEntityGetMetadataReturnsEmptyArrayWhenColumnIsNull(): void
    {
        $row = new AuditLog([
            'event_type' => AuditLogger::EVENT_TOTP_ENABLED,
        ]);

        $this->assertSame([], $row->getMetadata());
    }

    public function testEntityGetMetadataReturnsEmptyArrayOnInvalidJson(): void
    {
        $row = new AuditLog([
            'event_type' => 'test',
            'metadata'   => '{not-valid-json',
        ]);

        $this->assertSame([], $row->getMetadata());
    }
}
