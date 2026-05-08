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

use Daycry\Auth\Commands\AuditCommand;
use Daycry\Auth\Entities\User as UserEntity;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Services\AuditLogger;
use Daycry\Auth\Test\MockInputOutput;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class AuditCommandTest extends DatabaseTestCase
{
    private ?MockInputOutput $io = null;

    protected function tearDown(): void
    {
        parent::tearDown();

        AuditCommand::resetInputOutput();
    }

    private function setMockIo(array $inputs = []): void
    {
        $this->io = new MockInputOutput();
        $this->io->setInputs($inputs);
        AuditCommand::setInputOutput($this->io);
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

    public function testEmptyResultMessage(): void
    {
        $this->setMockIo();

        command('auth:audit');

        $this->assertStringContainsString(
            'No audit entries match',
            $this->io->getOutputs(),
        );
    }

    public function testInvalidSinceFails(): void
    {
        $this->setMockIo();

        command('auth:audit --since invalid');

        $this->assertStringContainsString('Invalid --since', $this->io->getOutputs());
    }

    public function testListsRecentEntriesDoesNotEmitEmptyMessage(): void
    {
        $logger = new AuditLogger();
        $logger->record(AuditLogger::EVENT_TOTP_ENABLED, 1);
        $logger->record(AuditLogger::EVENT_PASSWORD_CHANGED, 2);

        $this->setMockIo();

        command('auth:audit');

        // CLI::table() writes directly to STDOUT (not via the InputOutput
        // mock); we can only assert that the "no entries" message did NOT
        // appear, which would indicate the rows were enumerated.
        $this->assertStringNotContainsString(
            'No audit entries match',
            $this->io->getOutputs(),
        );
    }

    public function testFiltersByEventTypeFindsMatchingRow(): void
    {
        $logger = new AuditLogger();
        $logger->record(AuditLogger::EVENT_TOTP_ENABLED, 1);
        $logger->record(AuditLogger::EVENT_PASSWORD_CHANGED, 2);

        $this->setMockIo();

        command('auth:audit --type totp.enabled');

        // "No entries" should NOT appear (1 match was found).
        $this->assertStringNotContainsString(
            'No audit entries match',
            $this->io->getOutputs(),
        );
    }

    public function testFiltersByUserEmailFindsMatchingRow(): void
    {
        $alice = $this->createUser('alice@example.com');
        $bob   = $this->createUser('bob@example.com');

        $logger = new AuditLogger();
        $logger->record(AuditLogger::EVENT_TOTP_ENABLED, (int) $alice->id);
        $logger->record(AuditLogger::EVENT_TOTP_ENABLED, (int) $bob->id);

        $this->setMockIo();

        command('auth:audit --user alice@example.com');

        $this->assertStringNotContainsString(
            'No audit entries match',
            $this->io->getOutputs(),
        );
    }

    public function testReportsUnknownUserFilter(): void
    {
        $this->setMockIo();

        command('auth:audit --user nobody@example.com');

        $this->assertStringContainsString('User not found', $this->io->getOutputs());
    }

    public function testParseSinceAcceptsAllUnits(): void
    {
        $logger = new AuditLogger();
        $logger->record(AuditLogger::EVENT_TOTP_ENABLED, 1);

        foreach (['10s', '1m', '1h', '1d', '1w'] as $window) {
            $this->setMockIo();
            command('auth:audit --since ' . $window);
            // Just verify no "invalid" error.
            $this->assertStringNotContainsString('Invalid --since', $this->io->getOutputs());
        }
    }
}
