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

use CodeIgniter\I18n\Time;
use Daycry\Auth\Commands\PurgeCommand;
use Daycry\Auth\Entities\User as UserEntity;
use Daycry\Auth\Models\DeviceSessionModel;
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
}
