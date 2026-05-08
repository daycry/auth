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

use Daycry\Auth\Commands\SessionsCommand;
use Daycry\Auth\Entities\User as UserEntity;
use Daycry\Auth\Models\DeviceSessionModel;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Test\MockInputOutput;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class SessionsCommandTest extends DatabaseTestCase
{
    private ?MockInputOutput $io = null;

    protected function tearDown(): void
    {
        parent::tearDown();

        SessionsCommand::resetInputOutput();
    }

    private function setMockIo(array $inputs = []): void
    {
        $this->io = new MockInputOutput();
        $this->io->setInputs($inputs);
        SessionsCommand::setInputOutput($this->io);
    }

    private function createUser(string $email = 'user@example.com'): UserEntity
    {
        /** @var UserEntity $user */
        $user = fake(UserModel::class);
        model(UserIdentityModel::class)->createEmailIdentity($user, [
            'email'    => $email,
            'password' => 'secret123',
        ]);

        return $user;
    }

    public function testRequiresIdentifier(): void
    {
        $this->setMockIo();

        command('auth:sessions terminate');

        $this->assertStringContainsString('Specify -e', $this->io->getOutputs());
    }

    public function testRejectsUnknownAction(): void
    {
        $this->setMockIo();

        command('auth:sessions list -e user@example.com');

        $this->assertStringContainsString('Unsupported action', $this->io->getOutputs());
    }

    public function testReportsUnknownUser(): void
    {
        $this->setMockIo();

        command('auth:sessions terminate -e nobody@example.com');

        $this->assertStringContainsString('User not found', $this->io->getOutputs());
    }

    public function testTerminatesAllActiveSessions(): void
    {
        $user = $this->createUser('alice@example.com');

        $devices = model(DeviceSessionModel::class);
        $devices->createSession($user, 'session-A', '203.0.113.1', 'Mozilla');
        $devices->createSession($user, 'session-B', '203.0.113.2', 'curl');

        $this->setMockIo();
        command('auth:sessions terminate -e alice@example.com');

        $active = $devices->getActiveForUser($user);

        $this->assertSame([], $active);
    }

    public function testWorksByUserId(): void
    {
        $user = $this->createUser('byid@example.com');

        $devices = model(DeviceSessionModel::class);
        $devices->createSession($user, 'session-X', '203.0.113.7', 'browser');

        $this->setMockIo();
        command('auth:sessions terminate -i ' . (int) $user->id);

        $this->assertSame([], $devices->getActiveForUser($user));
    }
}
