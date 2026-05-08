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

use Daycry\Auth\Commands\TotpCommand;
use Daycry\Auth\Entities\AuditLog;
use Daycry\Auth\Entities\User as UserEntity;
use Daycry\Auth\Models\AuditLogModel;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Services\AuditLogger;
use Daycry\Auth\Test\MockInputOutput;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class TotpCommandTest extends DatabaseTestCase
{
    private ?MockInputOutput $io = null;

    protected function tearDown(): void
    {
        parent::tearDown();

        TotpCommand::resetInputOutput();
    }

    private function setMockIo(array $inputs = []): void
    {
        $this->io = new MockInputOutput();
        $this->io->setInputs($inputs);
        TotpCommand::setInputOutput($this->io);
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

        command('auth:totp reset');

        $this->assertStringContainsString('Specify -e', $this->io->getOutputs());
    }

    public function testRejectsUnknownAction(): void
    {
        $this->setMockIo();

        command('auth:totp setup -e user@example.com');

        $this->assertStringContainsString('Unsupported action', $this->io->getOutputs());
    }

    public function testReportsUnknownUser(): void
    {
        $this->setMockIo();

        command('auth:totp reset -e nobody@example.com');

        $this->assertStringContainsString('User not found', $this->io->getOutputs());
    }

    public function testResetClearsTotpAndWritesAuditEntry(): void
    {
        $user = $this->createUser('alice@example.com');
        $user->enableTotp();
        $user->confirmTotp();

        $this->assertTrue($user->hasTotpEnabled());

        $this->setMockIo();
        command('auth:totp reset -e alice@example.com');

        // Reload to verify TOTP was disabled.
        $reloaded = model(UserModel::class)->findById($user->id);
        $this->assertInstanceOf(UserEntity::class, $reloaded);
        $this->assertFalse($reloaded->hasTotpEnabled());

        // Audit entry recorded.
        $entry = model(AuditLogModel::class)
            ->where('event_type', AuditLogger::EVENT_TOTP_ADMIN_RESET)
            ->where('user_id', $user->id)
            ->first();

        $this->assertInstanceOf(AuditLog::class, $entry);
        $this->assertSame('cli', $entry->getMetadata()['initiator']);
    }

    public function testResetWorksByUserId(): void
    {
        $user = $this->createUser('byid@example.com');
        $user->enableTotp();
        $user->confirmTotp();

        $this->setMockIo();
        command('auth:totp reset -i ' . (int) $user->id);

        $reloaded = model(UserModel::class)->findById($user->id);
        $this->assertInstanceOf(UserEntity::class, $reloaded);
        $this->assertFalse($reloaded->hasTotpEnabled());
    }
}
