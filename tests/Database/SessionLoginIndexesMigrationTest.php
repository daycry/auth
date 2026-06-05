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

namespace Tests\Database;

use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Database\Migrations\AddSessionAndLoginIndexes;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Models\DeviceSessionModel;
use Daycry\Auth\Models\LoginModel;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class SessionLoginIndexesMigrationTest extends DatabaseTestCase
{
    /**
     * Asserts an index whose columns match $fields exists on $table.
     *
     * Matches on the index `fields` (driver-agnostic) rather than the
     * generated index name.
     *
     * @param list<string> $fields
     */
    private function assertIndexOnColumns(string $table, array $fields): void
    {
        $found = false;

        foreach ($this->db->getIndexData($table) as $index) {
            if ((array) $index->fields === $fields) {
                $found = true;
                break;
            }
        }

        $this->assertTrue(
            $found,
            sprintf('expected an index on %s(%s)', $table, implode(', ', $fields)),
        );
    }

    private function makeUser(): User
    {
        /** @var User $user */
        $user = fake(UserModel::class);

        return $user;
    }

    public function testDeviceSessionsHasUserIdLoggedOutAtIndex(): void
    {
        $this->assertIndexOnColumns(
            config('Auth')->tables['device_sessions'],
            ['user_id', 'logged_out_at'],
        );
    }

    public function testDeviceSessionsHasLoggedOutAtIndex(): void
    {
        $this->assertIndexOnColumns(
            config('Auth')->tables['device_sessions'],
            ['logged_out_at'],
        );
    }

    public function testLoginsHasUserIdSuccessIdIndex(): void
    {
        $this->assertIndexOnColumns(
            config('Auth')->tables['logins'],
            ['user_id', 'success', 'id'],
        );
    }

    public function testGetActiveForUserStillReturnsOnlyActiveSessions(): void
    {
        $devices = model(DeviceSessionModel::class);
        $user    = $this->makeUser();

        $devices->createSession($user, 'sid-A', '203.0.113.1', 'A');
        $devices->createSession($user, 'sid-B', '203.0.113.2', 'B');
        $devices->terminateSession('sid-A');

        $active = $devices->getActiveForUser($user);

        $this->assertCount(1, $active);
        $this->assertSame('sid-B', $active[0]->session_id);
    }

    public function testEnforceConcurrentSessionLimitTerminatesOldest(): void
    {
        $devices = model(DeviceSessionModel::class);
        $user    = $this->makeUser();

        $devices->createSession($user, 'sid-1', '1.1.1.1');
        $devices->where('session_id', 'sid-1')->set('last_active', '2020-01-01 00:00:00')->update();

        $devices->createSession($user, 'sid-2', '2.2.2.2');
        $devices->where('session_id', 'sid-2')->set('last_active', '2025-01-01 00:00:00')->update();

        $terminated = $devices->enforceConcurrentSessionLimit($user, 1);

        $this->assertSame(2, $terminated);
        $this->assertCount(0, $devices->getActiveForUser($user));
    }

    public function testPurgeOldSessionsRemovesOnlyOldTerminatedRows(): void
    {
        $devices = model(DeviceSessionModel::class);
        $user    = $this->makeUser();

        $devices->createSession($user, 'sid-old', '1.1.1.1');
        $devices->where('session_id', 'sid-old')
            ->set('logged_out_at', '2000-01-01 00:00:00')
            ->update();

        $devices->createSession($user, 'sid-new', '2.2.2.2');
        $devices->terminateSession('sid-new');

        $devices->purgeOldSessions(30);

        $this->assertNull($devices->findBySessionId('sid-old'));
        $this->assertNotNull($devices->findBySessionId('sid-new'));
    }

    public function testLastLoginAndPreviousLoginReturnExpectedRows(): void
    {
        $logins = model(LoginModel::class);
        $user   = $this->makeUser();

        $logins->recordLoginAttempt(Session::ID_TYPE_EMAIL_PASSWORD, 'u@example.com', true, '1.1.1.1', 'ua', (int) $user->id);
        $logins->recordLoginAttempt(Session::ID_TYPE_EMAIL_PASSWORD, 'u@example.com', false, '1.1.1.2', 'ua', (int) $user->id);
        $logins->recordLoginAttempt(Session::ID_TYPE_EMAIL_PASSWORD, 'u@example.com', true, '1.1.1.3', 'ua', (int) $user->id);

        $last = $logins->lastLogin($user);
        $this->assertNotNull($last);
        $this->assertSame('1.1.1.3', $last->ip_address);

        $previous = $logins->previousLogin($user);
        $this->assertNotNull($previous);
        $this->assertSame('1.1.1.1', $previous->ip_address);
    }

    public function testMigrationUpIsIdempotent(): void
    {
        $migration = new AddSessionAndLoginIndexes();

        // Running up() again against tables that already have the indexes must
        // not throw (guarded / idempotent).
        $migration->up();

        $this->assertIndexOnColumns(
            config('Auth')->tables['device_sessions'],
            ['user_id', 'logged_out_at'],
        );
    }
}
