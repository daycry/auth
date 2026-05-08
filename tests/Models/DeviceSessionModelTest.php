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

use Daycry\Auth\Entities\DeviceSession;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Models\DeviceSessionModel;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class DeviceSessionModelTest extends DatabaseTestCase
{
    private DeviceSessionModel $devices;

    protected function setUp(): void
    {
        parent::setUp();

        $this->devices = model(DeviceSessionModel::class);
    }

    private function makeUser(): User
    {
        /** @var User $user */
        $user = fake(UserModel::class);

        return $user;
    }

    public function testCreateSessionPersistsRowAndAssignsUuid(): void
    {
        $user = $this->makeUser();

        $session = $this->devices->createSession($user, 'sid-1', '203.0.113.1', 'Mozilla/5.0 (Windows NT 10.0)');

        $this->assertSame((int) $user->id, (int) $session->user_id);
        $this->assertSame('sid-1', $session->session_id);
        $this->assertSame('203.0.113.1', $session->ip_address);
        $this->assertNotEmpty($session->uuid);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/i', (string) $session->uuid);
    }

    public function testGetActiveForUserExcludesTerminated(): void
    {
        $user = $this->makeUser();

        $this->devices->createSession($user, 'sid-A', '203.0.113.1', 'A');
        $this->devices->createSession($user, 'sid-B', '203.0.113.2', 'B');

        $this->devices->terminateSession('sid-A');

        $active = $this->devices->getActiveForUser($user);
        $this->assertCount(1, $active);
        $this->assertSame('sid-B', $active[0]->session_id);
    }

    public function testGetAllForUserIncludesTerminated(): void
    {
        $user = $this->makeUser();

        $this->devices->createSession($user, 'sid-A', '203.0.113.1', 'A');
        $this->devices->createSession($user, 'sid-B', '203.0.113.2', 'B');
        $this->devices->terminateSession('sid-A');

        $all = $this->devices->getAllForUser($user);
        $this->assertCount(2, $all);
    }

    public function testFindBySessionIdReturnsRow(): void
    {
        $user = $this->makeUser();

        $created = $this->devices->createSession($user, 'sid-find', '203.0.113.1');

        $found = $this->devices->findBySessionId('sid-find');
        $this->assertInstanceOf(DeviceSession::class, $found);
        $this->assertSame((int) $created->id, (int) $found->id);
    }

    public function testFindBySessionIdReturnsNullWhenMissing(): void
    {
        $this->assertNotInstanceOf(DeviceSession::class, $this->devices->findBySessionId('does-not-exist'));
    }

    public function testTouchSessionUpdatesLastActive(): void
    {
        $user = $this->makeUser();

        $this->devices->createSession($user, 'sid-touch', '203.0.113.1');

        // Backdate last_active to verify touchSession updates it.
        $this->devices->where('session_id', 'sid-touch')
            ->set('last_active', '2020-01-01 00:00:00')
            ->update();

        // Read raw column via the query builder to dodge entity date casting.
        $rawBefore = $this->devices->builder()
            ->select('last_active')
            ->where('session_id', 'sid-touch')
            ->get()
            ->getRow();
        $this->assertSame('2020-01-01 00:00:00', (string) ($rawBefore->last_active ?? ''));

        $this->devices->touchSession('sid-touch');

        $rawAfter = $this->devices->builder()
            ->select('last_active')
            ->where('session_id', 'sid-touch')
            ->get()
            ->getRow();
        $this->assertNotSame('2020-01-01 00:00:00', (string) ($rawAfter->last_active ?? ''));
    }

    public function testTerminateAllForUserKeepsExceptedSession(): void
    {
        $user = $this->makeUser();

        $this->devices->createSession($user, 'sid-A', '1.1.1.1');
        $this->devices->createSession($user, 'sid-B', '2.2.2.2');
        $this->devices->createSession($user, 'sid-keep', '3.3.3.3');

        $this->devices->terminateAllForUser($user, 'sid-keep');

        $active = $this->devices->getActiveForUser($user);
        $this->assertCount(1, $active);
        $this->assertSame('sid-keep', $active[0]->session_id);
    }

    public function testTerminateAllForUserWithNullExceptTerminatesEverything(): void
    {
        $user = $this->makeUser();

        $this->devices->createSession($user, 'sid-A', '1.1.1.1');
        $this->devices->createSession($user, 'sid-B', '2.2.2.2');

        $this->devices->terminateAllForUser($user);

        $this->assertCount(0, $this->devices->getActiveForUser($user));
    }

    public function testMarkTrustedAndFindTrustedByUuid(): void
    {
        $user = $this->makeUser();

        $session = $this->devices->createSession($user, 'sid-trust', '1.1.1.1');
        $this->devices->markTrusted((string) $session->uuid, 600);

        $trusted = $this->devices->findTrustedByUuid((string) $session->uuid);
        $this->assertInstanceOf(DeviceSession::class, $trusted);
        $this->assertSame((int) $session->id, (int) $trusted->id);
    }

    public function testMarkTrustedNoOpsForEmptyUuidOrZeroLifetime(): void
    {
        $this->devices->markTrusted('', 600);
        $this->devices->markTrusted('whatever', 0);

        // Nothing was inserted/updated.
        $this->assertSame(0, $this->devices->where('trusted_until IS NOT NULL')->countAllResults());
    }

    public function testFindTrustedByUuidReturnsNullForUnknownOrExpired(): void
    {
        $user    = $this->makeUser();
        $session = $this->devices->createSession($user, 'sid-x', '1.1.1.1');

        // Empty input.
        $this->assertNotInstanceOf(DeviceSession::class, $this->devices->findTrustedByUuid(''));

        // Without trust set.
        $this->assertNotInstanceOf(DeviceSession::class, $this->devices->findTrustedByUuid((string) $session->uuid));

        // Trust expired in the past.
        $this->devices->where('uuid', $session->uuid)
            ->set('trusted_until', '2000-01-01 00:00:00')
            ->update();
        $this->assertNotInstanceOf(DeviceSession::class, $this->devices->findTrustedByUuid((string) $session->uuid));
    }

    public function testRevokeTrustClearsFlag(): void
    {
        $user = $this->makeUser();

        $session = $this->devices->createSession($user, 'sid-t', '1.1.1.1');
        $this->devices->markTrusted((string) $session->uuid, 600);
        $this->assertInstanceOf(DeviceSession::class, $this->devices->findTrustedByUuid((string) $session->uuid));

        $this->devices->revokeTrust((string) $session->uuid);
        $this->assertNotInstanceOf(DeviceSession::class, $this->devices->findTrustedByUuid((string) $session->uuid));

        // Empty UUID is a no-op (covered for completeness).
        $this->devices->revokeTrust('');
    }

    public function testEnforceConcurrentSessionLimitTerminatesOldest(): void
    {
        $user = $this->makeUser();

        // Create 3 sessions; the limit is 2 → expect one terminated (the oldest by last_active).
        $this->devices->createSession($user, 'sid-1', '1.1.1.1');
        $this->devices->where('session_id', 'sid-1')->set('last_active', '2020-01-01 00:00:00')->update();

        $this->devices->createSession($user, 'sid-2', '2.2.2.2');
        $this->devices->where('session_id', 'sid-2')->set('last_active', '2024-01-01 00:00:00')->update();

        $this->devices->createSession($user, 'sid-3', '3.3.3.3');
        $this->devices->where('session_id', 'sid-3')->set('last_active', '2025-01-01 00:00:00')->update();

        $terminated = $this->devices->enforceConcurrentSessionLimit($user, 2);
        $this->assertSame(2, $terminated, 'with limit=2, must leave room for the new session → terminate 2 oldest');

        $active = $this->devices->getActiveForUser($user);
        $this->assertCount(1, $active);
        $this->assertSame('sid-3', $active[0]->session_id);
    }

    public function testEnforceConcurrentSessionLimitNoOpsWhenUnderLimit(): void
    {
        $user = $this->makeUser();

        $this->devices->createSession($user, 'sid-A', '1.1.1.1');

        $this->assertSame(0, $this->devices->enforceConcurrentSessionLimit($user, 5));
        $this->assertSame(0, $this->devices->enforceConcurrentSessionLimit($user, 0));
    }

    public function testPurgeOldSessionsRemovesTerminatedRows(): void
    {
        $user = $this->makeUser();

        $this->devices->createSession($user, 'sid-old', '1.1.1.1');
        $this->devices->where('session_id', 'sid-old')
            ->set('logged_out_at', '2000-01-01 00:00:00')
            ->update();

        $this->devices->createSession($user, 'sid-new', '2.2.2.2');
        $this->devices->terminateSession('sid-new'); // terminated just now → not purged

        $this->devices->purgeOldSessions(30);

        $this->assertNotInstanceOf(DeviceSession::class, $this->devices->findBySessionId('sid-old'));
        $this->assertInstanceOf(DeviceSession::class, $this->devices->findBySessionId('sid-new'));
    }

    public function testParseUserAgentRecognisesCommonStrings(): void
    {
        $this->assertSame('Unknown Device', DeviceSession::parseUserAgent(''));
        $this->assertSame(
            'Microsoft Edge on Windows',
            DeviceSession::parseUserAgent('Mozilla/5.0 Edg/120.0 Windows'),
        );
        $this->assertSame(
            'Firefox on Linux',
            DeviceSession::parseUserAgent('Mozilla/5.0 Firefox/110.0 Linux'),
        );
        $this->assertSame(
            'Chrome on macOS',
            DeviceSession::parseUserAgent('Mozilla/5.0 (Macintosh) Chrome/120.0'),
        );
        $this->assertSame(
            'Safari on iOS',
            DeviceSession::parseUserAgent('Mozilla/5.0 (iPhone) Safari/600.0'),
        );
        $this->assertSame(
            'cURL on Linux',
            DeviceSession::parseUserAgent('curl/7.0 Linux'),
        );
    }
}
