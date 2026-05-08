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

use Config\Services;
use Daycry\Auth\Commands\GdprCommand;
use Daycry\Auth\Entities\AuditLog;
use Daycry\Auth\Entities\User as UserEntity;
use Daycry\Auth\Models\AuditLogModel;
use Daycry\Auth\Models\DeviceSessionModel;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Services\AuditLogger;
use Daycry\Auth\Test\MockInputOutput;
use ReflectionMethod;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class GdprCommandTest extends DatabaseTestCase
{
    private ?MockInputOutput $io = null;

    protected function tearDown(): void
    {
        parent::tearDown();

        GdprCommand::resetInputOutput();
    }

    private function setMockIo(array $inputs = []): void
    {
        $this->io = new MockInputOutput();
        $this->io->setInputs($inputs);
        GdprCommand::setInputOutput($this->io);
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

        command('auth:gdpr export');

        $this->assertStringContainsString('Specify -e', $this->io->getOutputs());
    }

    public function testRejectsUnknownAction(): void
    {
        $this->createUser('alice@example.com');
        $this->setMockIo();

        command('auth:gdpr unknown -e alice@example.com');

        $this->assertStringContainsString('Unsupported action', $this->io->getOutputs());
    }

    public function testReportsUnknownUser(): void
    {
        $this->setMockIo();

        command('auth:gdpr export -e nobody@example.com');

        $this->assertStringContainsString('User not found', $this->io->getOutputs());
    }

    public function testExportProducesJsonOnStdout(): void
    {
        $this->createUser('alice@example.com');

        $this->setMockIo();
        command('auth:gdpr export -e alice@example.com');

        $output = $this->io->getOutputs();
        $this->assertStringContainsString('"username"', $output);
        $this->assertStringContainsString('"identities"', $output);
        $this->assertStringContainsString('"device_sessions"', $output);
        $this->assertStringContainsString('"login_history"', $output);
        $this->assertStringContainsString('"audit_log"', $output);
    }

    public function testExportRedactsTokenSecrets(): void
    {
        $user = $this->createUser('alice@example.com');

        // Issue an access token so identities[] has a sensitive secret.
        $user->generateAccessToken('mobile-app', ['*']);

        $this->setMockIo();
        command('auth:gdpr export -e alice@example.com');

        $output = $this->io->getOutputs();
        $this->assertStringContainsString('redacted: hashed token', $output);
    }

    public function testExportProducesValidJson(): void
    {
        $user = $this->createUser('alice@example.com');

        $this->setMockIo();
        command('auth:gdpr export -e alice@example.com');

        $output = $this->io->getOutputs();

        // Strip color codes and collect everything between the first `{`
        // and the last `}` — the entire payload is one JSON document.
        $stripped = preg_replace('/\033\[[0-9;]*m/', '', $output) ?? $output;
        $start    = strpos($stripped, '{');
        $end      = strrpos($stripped, '}');

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $json = substr($stripped, $start, $end - $start + 1);
        $data = json_decode($json, true);

        $this->assertIsArray($data);
        $this->assertSame((int) $user->id, $data['user']['id']);
        $this->assertArrayHasKey('identities', $data);
        $this->assertArrayHasKey('device_sessions', $data);
    }

    public function testAnonymizeAbortsOnNo(): void
    {
        $user = $this->createUser('alice@example.com');

        $this->setMockIo(['n']);
        command('auth:gdpr anonymize -e alice@example.com');

        $this->assertStringContainsString('Aborted', $this->io->getOutputs());

        // Username should still be intact.
        $reloaded = model(UserModel::class)->findById($user->id);
        $this->assertSame($user->username, $reloaded->username);
    }

    public function testExportLooksUpUserById(): void
    {
        $user   = $this->createUser('alice@example.com');
        $userId = (int) $user->id;

        $this->setMockIo();
        command('auth:gdpr export -i ' . $userId);

        $output = $this->io->getOutputs();
        $this->assertStringContainsString('"id":', $output);
        $this->assertStringContainsString('"username"', $output);
    }

    public function testExportWritesToOutputPath(): void
    {
        $user = $this->createUser('alice@example.com');

        // CI4's command() helper re-tokenises arguments and Windows
        // backslashes get mangled, so call exportAction() directly via
        // reflection with an absolute path.
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gdpr_export_' . bin2hex(random_bytes(4)) . '.json';

        try {
            $this->setMockIo();

            $cmd       = new GdprCommand(Services::logger(), Services::commands());
            $reflector = new ReflectionMethod($cmd, 'exportAction');
            $exit      = $reflector->invoke($cmd, $user, $tmp);

            $this->assertSame(0, $exit);
            $this->assertFileExists($tmp);

            $payload = json_decode((string) file_get_contents($tmp), true);
            $this->assertIsArray($payload);
            $this->assertSame((int) $user->id, $payload['user']['id']);
            $this->assertArrayHasKey('identities', $payload);
            $this->assertArrayHasKey('audit_log', $payload);
        } finally {
            if (file_exists($tmp)) {
                @unlink($tmp);
            }
        }
    }

    public function testAnonymizeReplacesUsernameAndDropsIdentities(): void
    {
        $user   = $this->createUser('alice@example.com');
        $userId = (int) $user->id;

        // Seed a device session so we can verify it's wiped.
        $devices = model(DeviceSessionModel::class);
        $devices->createSession($user, 'session-A', '203.0.113.1', 'Mozilla');

        $this->setMockIo(['y']);
        command('auth:gdpr anonymize -e alice@example.com');

        $reloaded = model(UserModel::class)->findById($userId);
        $this->assertSame('deleted_' . $userId, $reloaded->username);
        $this->assertSame(0, (int) $reloaded->active);

        // Identities and device sessions should be gone.
        $this->assertSame(0, model(UserIdentityModel::class)->where('user_id', $userId)->countAllResults());
        $this->assertSame(0, $devices->where('user_id', $userId)->countAllResults());

        // An EVENT_USER_ANONYMIZED audit entry should exist.
        $entry = model(AuditLogModel::class)
            ->where('event_type', AuditLogger::EVENT_USER_ANONYMIZED)
            ->where('user_id', $userId)
            ->first();
        $this->assertInstanceOf(AuditLog::class, $entry);
    }
}
