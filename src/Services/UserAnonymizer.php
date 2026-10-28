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

namespace Daycry\Auth\Services;

use Config\Database;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Interfaces\UserAnonymizerInterface;
use Daycry\Auth\Models\DeviceSessionModel;
use Daycry\Auth\Models\LoginModel;
use Daycry\Auth\Models\PasswordHistoryModel;
use Daycry\Auth\Models\RememberModel;
use Daycry\Auth\Models\TotpBackupCodeModel;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Models\WebAuthnCredentialModel;
use Throwable;

/**
 * Anonymises an account (GDPR erasure) while keeping the users row, so
 * foreign keys and the audit trail stay intact.
 *
 * Removes every identity (login e-mail + password, OAuth, tokens, pending
 * codes), device sessions, remember-me tokens, passkeys, TOTP backup codes,
 * password history and the login history (successful sign-ins by user id,
 * failed attempts by the login e-mail), then overwrites the users row.
 * Everything runs in one transaction; a failure rolls back and is rethrown.
 *
 * Shared by `php spark auth:gdpr anonymize` and the self-service account
 * deletion endpoint.
 */
class UserAnonymizer implements UserAnonymizerInterface
{
    /**
     * @param string   $initiator Recorded in the audit entry ('cli', 'self-service', ...)
     * @param int|null $actorId   Who performed it (the user themselves, an admin, null for CLI)
     *
     * @throws Throwable When any step fails (nothing is changed then)
     */
    public function anonymize(User $user, string $initiator = 'cli', ?int $actorId = null): void
    {
        $userId = (int) $user->id;
        $email  = strtolower((string) $user->email);

        $db = Database::connect(setting('Auth.DBGroup'));
        $db->transException(true)->transStart();

        try {
            model(UserIdentityModel::class)->where('user_id', $userId)->delete();
            model(DeviceSessionModel::class)->where('user_id', $userId)->delete();
            model(RememberModel::class)->where('user_id', $userId)->delete();
            model(WebAuthnCredentialModel::class)->where('user_id', $userId)->delete();
            model(TotpBackupCodeModel::class)->where('user_id', $userId)->delete();
            model(PasswordHistoryModel::class)->where('user_id', $userId)->delete();

            $logins = model(LoginModel::class)->groupStart()->where('user_id', $userId);
            if ($email !== '') {
                $logins->orWhere('identifier', $email);
            }
            $logins->groupEnd()->delete();

            model(UserModel::class)->builder()->where('id', $userId)->update([
                'username'            => 'deleted_' . $userId,
                'active'              => 0,
                'failed_login_count'  => 0,
                'locked_until'        => null,
                'password_changed_at' => null,
            ]);

            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();

            throw $e;
        }

        service('auditLogger')->record(AuditLogger::EVENT_USER_ANONYMIZED, $userId, ['initiator' => $initiator], $actorId);
    }
}
