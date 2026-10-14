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

use CodeIgniter\Config\Factories;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Enums\IdentityType;
use Daycry\Auth\Models\DeviceSessionModel;
use Daycry\Auth\Models\LoginModel;
use Daycry\Auth\Models\PasswordHistoryModel;
use Daycry\Auth\Models\RememberModel;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Models\WebAuthnCredentialModel;
use Daycry\Auth\Services\AuditLogger;
use Daycry\Auth\Services\UserAnonymizer;
use RuntimeException;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class UserAnonymizerTest extends DatabaseTestCase
{
    public function testRemovesEveryPieceOfPersonalDataAndKeepsTheRow(): void
    {
        $user  = $this->userWithFootprint('gone@example.com');
        $other = $this->userWithFootprint('stays@example.com');

        (new UserAnonymizer())->anonymize($user, 'self-service', (int) $user->id);

        $id = (int) $user->id;
        $this->seeInDatabase($this->tables['users'], ['id' => $id, 'username' => 'deleted_' . $id, 'active' => 0]);

        foreach (['identities', 'device_sessions', 'totp_backup_codes', 'password_history', 'remember_tokens', 'webauthn_credentials'] as $table) {
            $this->dontSeeInDatabase($this->tables[$table], ['user_id' => $id]);
        }
        // Login history: successful (user_id) and failed (identifier = e-mail).
        $this->dontSeeInDatabase($this->tables['logins'], ['user_id' => $id]);
        $this->dontSeeInDatabase($this->tables['logins'], ['identifier' => 'gone@example.com']);

        $this->seeInDatabase($this->tables['audit_logs'], [
            'user_id'    => $id,
            'actor_id'   => $id,
            'event_type' => AuditLogger::EVENT_USER_ANONYMIZED,
            'metadata'   => json_encode(['initiator' => 'self-service']),
        ]);

        // Other accounts are untouched.
        $this->seeInDatabase($this->tables['identities'], ['user_id' => $other->id, 'secret' => 'stays@example.com']);
        $this->seeInDatabase($this->tables['logins'], ['identifier' => 'stays@example.com']);
        $this->seeInDatabase($this->tables['webauthn_credentials'], ['user_id' => $other->id]);
    }

    public function testRollsBackWhenAStepFails(): void
    {
        $user = $this->userWithFootprint('rollback@example.com');

        Factories::injectMock('models', WebAuthnCredentialModel::class, new class () extends WebAuthnCredentialModel {
            public function delete($id = null, bool $purge = false): void
            {
                throw new RuntimeException('disk full');
            }
        });

        try {
            (new UserAnonymizer())->anonymize($user);
            $this->fail('Expected the failure to propagate.');
        } catch (RuntimeException $e) {
            $this->assertSame('disk full', $e->getMessage());
        } finally {
            Factories::reset('models');
        }

        $this->seeInDatabase($this->tables['identities'], ['user_id' => $user->id, 'secret' => 'rollback@example.com']);
        $this->seeInDatabase($this->tables['users'], ['id' => $user->id, 'username' => $user->username]);
        $this->dontSeeInDatabase($this->tables['audit_logs'], ['user_id' => $user->id, 'event_type' => AuditLogger::EVENT_USER_ANONYMIZED]);
    }

    /**
     * A user with data in every table the anonymizer must clear.
     */
    private function userWithFootprint(string $email): User
    {
        $user = fake(UserModel::class);
        $id   = (int) $user->id;

        model(UserIdentityModel::class)->createEmailIdentity($user, ['email' => $email, 'password' => 'Secret-Password-1']);
        model(UserIdentityModel::class)->insert(['user_id' => $id, 'type' => IdentityType::oauthProvider('github'), 'secret' => 'gh-' . $id, 'name' => 'Social']);
        model(DeviceSessionModel::class)->insert(['user_id' => $id, 'session_id' => 'sid-' . $id, 'ip_address' => '203.0.113.1', 'user_agent' => 'UA', 'last_active' => date('Y-m-d H:i:s')]);
        $this->db->table($this->tables['totp_backup_codes'])->insert(['user_id' => $id, 'code_hash' => hash('sha256', 'code-' . $id), 'created_at' => date('Y-m-d H:i:s')]);
        model(PasswordHistoryModel::class)->insert(['user_id' => $id, 'password_hash' => 'old-hash', 'created_at' => date('Y-m-d H:i:s')]);
        model(RememberModel::class)->rememberUser($user, 'sel-' . $id, hash('sha256', 'v'), date('Y-m-d H:i:s', time() + 3600));
        model(WebAuthnCredentialModel::class)->insert(['user_id' => $id, 'credential_id' => 'cred-' . $id, 'credential' => '{}', 'user_handle' => 'h', 'name' => 'Key', 'sign_count' => 0]);
        model(LoginModel::class)->recordLoginAttempt('email_password', $email, true, '203.0.113.1', 'UA', $id);
        model(LoginModel::class)->recordLoginAttempt('email_password', $email, false, '203.0.113.1', 'UA');

        return model(UserModel::class)->findById($id);
    }
}
