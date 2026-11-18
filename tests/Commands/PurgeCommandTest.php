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

namespace Tests\Commands;

use CodeIgniter\Events\Events;
use CodeIgniter\I18n\Time;
use Daycry\Auth\Commands\PurgeCommand;
use Daycry\Auth\Entities\User as UserEntity;
use Daycry\Auth\Models\AuditLogModel;
use Daycry\Auth\Models\DeviceSessionModel;
use Daycry\Auth\Models\LoginModel;
use Daycry\Auth\Models\RememberModel;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Test\MockInputOutput;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class PurgeCommandTest extends DatabaseTestCase
{
    private ?MockInputOutput $io = null;

    protected function tearDown(): void
    {
        parent::tearDown();

        PurgeCommand::resetInputOutput();
    }

    private function setMockIo(): void
    {
        $this->io = new MockInputOutput();
        $this->io->setInputs([]);
        PurgeCommand::setInputOutput($this->io);
    }

    private function makeUser(): UserEntity
    {
        /** @var UserEntity $user */
        $user = fake(UserModel::class);

        return $user;
    }

    public function testPurgeRemovesExpiredTokensAndOldSessions(): void
    {
        $user = $this->makeUser();

        /** @var RememberModel $remember */
        $remember = model(RememberModel::class);
        $remember->rememberUser($user, 'expired-selector', hash('sha256', 'v'), Time::now()->subDays(2)->format('Y-m-d H:i:s'));

        /** @var DeviceSessionModel $devices */
        $devices = model(DeviceSessionModel::class);
        $devices->createSession($user, 'old-session', '10.0.0.1');
        $devices->where('session_id', 'old-session')
            ->set('logged_out_at', Time::now()->subDays(60)->format('Y-m-d H:i:s'))
            ->update();

        $this->setMockIo();
        command('auth:purge');

        $this->assertStringContainsString('Purged', $this->io->getOutputs());
        $this->dontSeeInDatabase($this->tables['remember_tokens'], ['selector' => 'expired-selector']);
        $this->dontSeeInDatabase($this->tables['device_sessions'], ['session_id' => 'old-session']);
    }

    public function testPurgeKeepsValidData(): void
    {
        $user = $this->makeUser();

        /** @var RememberModel $remember */
        $remember = model(RememberModel::class);
        $remember->rememberUser($user, 'valid-selector', hash('sha256', 'v'), Time::now()->addDays(10)->format('Y-m-d H:i:s'));

        /** @var DeviceSessionModel $devices */
        $devices = model(DeviceSessionModel::class);
        $devices->createSession($user, 'recent-session', '10.0.0.2');
        $devices->where('session_id', 'recent-session')
            ->set('logged_out_at', Time::now()->subDays(5)->format('Y-m-d H:i:s'))
            ->update();

        $this->setMockIo();
        command('auth:purge');

        // A non-expired remember token and a recently-terminated session survive.
        $this->seeInDatabase($this->tables['remember_tokens'], ['selector' => 'valid-selector']);
        $this->seeInDatabase($this->tables['device_sessions'], ['session_id' => 'recent-session']);
    }

    public function testConfiguredRetentionPurgesOnlyOldLoginAndAuditRows(): void
    {
        setting('AuthSecurity.loginRetentionDays', 30);
        setting('AuthSecurity.auditRetentionDays', 90);
        $user  = $this->makeUser();
        $login = model(LoginModel::class);
        $audit = model(AuditLogModel::class);

        foreach ([1, 100] as $days) {
            $date = Time::now()->subDays($days)->format('Y-m-d H:i:s');
            $login->insert(['user_id' => $user->id, 'ip_address' => '127.0.0.1', 'id_type' => 'email_password', 'identifier' => 'age-' . $days, 'date' => $date, 'success' => 1]);
            $audit->insert(['user_id' => $user->id, 'event_type' => 'age-' . $days, 'created_at' => $date]);
        }
        $this->setMockIo();
        command('auth:purge');
        $this->dontSeeInDatabase($this->tables['logins'], ['identifier' => 'age-100']);
        $this->dontSeeInDatabase($this->tables['audit_logs'], ['event_type' => 'age-100']);
        $this->seeInDatabase($this->tables['logins'], ['identifier' => 'age-1']);
        $this->seeInDatabase($this->tables['audit_logs'], ['event_type' => 'age-1']);
    }

    public function testDisabledRetentionKeepsHistoricalRecords(): void
    {
        setting('AuthSecurity.loginRetentionDays', 0);
        setting('AuthSecurity.auditRetentionDays', 0);
        $user = $this->makeUser();
        $old  = Time::now()->subDays(400)->format('Y-m-d H:i:s');
        model(LoginModel::class)->insert(['user_id' => $user->id, 'ip_address' => '127.0.0.1', 'id_type' => 'email_password', 'identifier' => 'retained', 'date' => $old, 'success' => 1]);
        model(AuditLogModel::class)->insert(['user_id' => $user->id, 'event_type' => 'retained', 'created_at' => $old]);
        $this->setMockIo();
        command('auth:purge');
        $this->seeInDatabase($this->tables['logins'], ['identifier' => 'retained']);
        $this->seeInDatabase($this->tables['audit_logs'], ['event_type' => 'retained']);
    }

    public function testHistoryCleanupUsesBoundedDeleteBatches(): void
    {
        $user = $this->makeUser();
        $date = Time::now()->subDays(100)->format('Y-m-d H:i:s');

        for ($i = 0; $i < 7; $i++) {
            model(LoginModel::class)->insert(['user_id' => $user->id, 'ip_address' => '127.0.0.1', 'id_type' => 'email_password', 'identifier' => 'batch-' . $i, 'date' => $date, 'success' => 1]);
        }
        $deletes  = [];
        $listener = static function ($query) use (&$deletes): void {
            $sql = strtolower((string) $query);
            if (str_contains($sql, 'delete from') && str_contains($sql, 'logins')) {
                $deletes[] = $sql;
            }
        };
        Events::on('DBQuery', $listener);

        try {
            $this->setMockIo();
            command('auth:purge --logins 30 --batch-size 3');
        } finally {
            Events::removeListener('DBQuery', $listener);
        }
        $this->assertCount(3, $deletes);
        $this->assertSame(0, model(LoginModel::class)->countAllResults());
    }
}
