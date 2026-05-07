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

namespace Daycry\Auth\Commands;

use CodeIgniter\CLI\CLI;
use CodeIgniter\I18n\Time;
use Daycry\Auth\Authentication\Authenticators\AccessToken;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Enums\IdentityType;
use Daycry\Auth\Models\AuditLogModel;
use Daycry\Auth\Models\DeviceSessionModel;
use Daycry\Auth\Models\LoginModel;
use Daycry\Auth\Models\PasswordHistoryModel;
use Daycry\Auth\Models\TotpBackupCodeModel;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Services\AuditLogger;
use Throwable;

/**
 * GDPR-friendly data export and account anonymization.
 *
 * Usage:
 *   php spark auth:gdpr export -e <email> [-o <path>]
 *   php spark auth:gdpr anonymize -e <email>
 *
 * `export` writes a JSON document containing the user's row, identities
 * (with secrets redacted), device sessions, login history, audit log
 * entries, and password-history metadata.
 *
 * `anonymize` keeps the user row (preserving FK integrity) but replaces
 * personal fields with placeholders, deletes identities/tokens/device
 * sessions/password-history/backup-codes, and writes an audit-log entry.
 */
class GdprCommand extends BaseCommand
{
    protected $name        = 'auth:gdpr';
    protected $description = 'GDPR helpers: export user data / anonymize a user account.';
    protected $usage       = <<<'EOL'
        auth:gdpr export -e <email> [-o <path>]
        auth:gdpr anonymize -e <email>
        EOL;

    /**
     * @var array<string, string>
     */
    protected $options = [
        '-e' => 'Target user email.',
        '-i' => 'Target user id (alternative to -e).',
        '-o' => 'Output file path (export only). Defaults to stdout.',
    ];

    public function run(array $params): int
    {
        $action = array_shift($params) ?? '';
        $email  = (string) (CLI::getOption('e') ?? '');
        $id     = (string) (CLI::getOption('i') ?? '');

        if ($email === '' && $id === '') {
            CLI::error('Specify -e <email> or -i <id>.');

            return 1;
        }

        /** @var UserModel $userModel */
        $userModel = model(UserModel::class);
        $user      = $id !== ''
            ? $userModel->findById((int) $id)
            : $userModel->findByCredentials(['email' => $email]);

        if (! $user instanceof User) {
            CLI::error('User not found.');

            return 1;
        }

        return match ($action) {
            'export'    => $this->exportAction($user),
            'anonymize' => $this->anonymizeAction($user, $userModel),
            default     => $this->unsupported(),
        };
    }

    private function unsupported(): int
    {
        CLI::error('Unsupported action. Supported: export, anonymize.');

        return 1;
    }

    private function exportAction(User $user): int
    {
        try {
            $payload = [
                'exported_at' => Time::now()->toDateTimeString(),
                'user'        => [
                    'id'                  => $user->id,
                    'uuid'                => $user->uuid ?? null,
                    'username'            => $user->username,
                    'email'               => $user->email ?? null,
                    'active'              => (bool) ($user->active ?? false),
                    'created_at'          => (string) ($user->created_at ?? ''),
                    'updated_at'          => (string) ($user->updated_at ?? ''),
                    'failed_login_count'  => $user->failed_login_count ?? 0,
                    'locked_until'        => $user->locked_until ?? null,
                    'password_changed_at' => $user->password_changed_at ?? null,
                ],
                'identities'       => $this->collectIdentities($user),
                'device_sessions'  => $this->collectDeviceSessions($user),
                'login_history'    => $this->collectLoginHistory($user),
                'audit_log'        => $this->collectAuditLog($user),
                'password_history' => $this->collectPasswordHistoryMeta($user),
                'backup_codes'     => $this->collectBackupCodesMeta($user),
            ];

            $json = json_encode(
                $payload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );

            if ($json === false) {
                CLI::error('JSON encoding failed.');

                return 1;
            }

            $output = (string) (CLI::getOption('o') ?? '');

            if ($output !== '') {
                file_put_contents($output, $json);
                CLI::write('Wrote export to ' . $output, 'green');
            } else {
                CLI::write($json);
            }

            return 0;
        } catch (Throwable $e) {
            CLI::error('Export failed: ' . $e->getMessage());

            return 1;
        }
    }

    private function anonymizeAction(User $user, UserModel $userModel): int
    {
        if (CLI::prompt('This will permanently anonymize user ' . $user->id . '. Continue?', ['y', 'n']) !== 'y') {
            CLI::write('Aborted.', 'yellow');

            return 0;
        }

        try {
            $userId = (int) $user->id;

            // Soft-revoke and delete identities (passwords, tokens, OAuth).
            /** @var UserIdentityModel $identityModel */
            $identityModel = model(UserIdentityModel::class);
            $identityModel->where('user_id', $userId)->delete();

            // Drop device sessions / login history / backup codes /
            // password history. We keep audit log entries with user_id set
            // but the row itself anonymised below.
            /** @var DeviceSessionModel $deviceModel */
            $deviceModel = model(DeviceSessionModel::class);
            $deviceModel->where('user_id', $userId)->delete();

            /** @var TotpBackupCodeModel $backup */
            $backup = model(TotpBackupCodeModel::class);
            $backup->where('user_id', $userId)->delete();

            /** @var PasswordHistoryModel $history */
            $history = model(PasswordHistoryModel::class);
            $history->where('user_id', $userId)->delete();

            // Anonymise the user row (preserve FKs).
            $userModel->where('id', $userId)->set([
                'username'            => 'deleted_' . $userId,
                'active'              => 0,
                'failed_login_count'  => 0,
                'locked_until'        => null,
                'password_changed_at' => null,
            ])->update();

            // Final audit-log entry with anonymisation.
            (new AuditLogger())->record(
                AuditLogger::EVENT_USER_ANONYMIZED,
                $userId,
                ['initiator' => 'cli'],
            );

            CLI::write('User ' . $userId . ' anonymised. Identities and tokens removed.', 'green');

            return 0;
        } catch (Throwable $e) {
            CLI::error('Anonymization failed: ' . $e->getMessage());

            return 1;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectIdentities(User $user): array
    {
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        $rows   = $identityModel->where('user_id', $user->id)->findAll();
        $result = [];

        foreach ($rows as $row) {
            $type = is_object($row) ? ($row->type ?? '') : ($row['type'] ?? '');

            $entry = [
                'id'           => is_object($row) ? ($row->id ?? null) : ($row['id'] ?? null),
                'type'         => $type,
                'name'         => is_object($row) ? ($row->name ?? null) : ($row['name'] ?? null),
                'expires'      => is_object($row) ? ($row->expires ?? null) : ($row['expires'] ?? null),
                'last_used_at' => is_object($row) ? ($row->last_used_at ?? null) : ($row['last_used_at'] ?? null),
                'revoked_at'   => is_object($row) ? ($row->revoked_at ?? null) : ($row['revoked_at'] ?? null),
                'created_at'   => is_object($row) ? ($row->created_at ?? null) : ($row['created_at'] ?? null),
            ];

            // Redact secret fields — they are not portable user data and
            // exposing them would defeat the purpose of revoking a token.
            if ($type === IdentityType::EMAIL_PASSWORD->value) {
                $entry['secret']  = is_object($row) ? ($row->secret ?? null) : ($row['secret'] ?? null);
                $entry['secret2'] = '<redacted: bcrypt hash>';
            } elseif ($type === AccessToken::ID_TYPE_ACCESS_TOKEN || $type === IdentityType::JWT_REFRESH->value) {
                $entry['secret']  = '<redacted: hashed token>';
                $entry['secret2'] = null;
            } else {
                $entry['secret']  = is_object($row) ? ($row->secret ?? null) : ($row['secret'] ?? null);
                $entry['secret2'] = is_object($row) ? ($row->secret2 ?? null) : ($row['secret2'] ?? null);
            }

            $result[] = $entry;
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectDeviceSessions(User $user): array
    {
        /** @var DeviceSessionModel $deviceModel */
        $deviceModel = model(DeviceSessionModel::class);

        $rows   = $deviceModel->where('user_id', $user->id)->findAll();
        $result = [];

        foreach ($rows as $row) {
            $result[] = [
                'uuid'          => is_object($row) ? ($row->uuid ?? null) : ($row['uuid'] ?? null),
                'session_id'    => is_object($row) ? ($row->session_id ?? null) : ($row['session_id'] ?? null),
                'device_name'   => is_object($row) ? ($row->device_name ?? null) : ($row['device_name'] ?? null),
                'ip_address'    => is_object($row) ? ($row->ip_address ?? null) : ($row['ip_address'] ?? null),
                'user_agent'    => is_object($row) ? ($row->user_agent ?? null) : ($row['user_agent'] ?? null),
                'last_active'   => is_object($row) ? ($row->last_active ?? null) : ($row['last_active'] ?? null),
                'logged_out_at' => is_object($row) ? ($row->logged_out_at ?? null) : ($row['logged_out_at'] ?? null),
                'trusted_until' => is_object($row) ? ($row->trusted_until ?? null) : ($row['trusted_until'] ?? null),
                'created_at'    => is_object($row) ? ($row->created_at ?? null) : ($row['created_at'] ?? null),
            ];
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectLoginHistory(User $user): array
    {
        /** @var LoginModel $loginModel */
        $loginModel = model(LoginModel::class);

        $rows   = $loginModel->recentForUser($user, 500);
        $result = [];

        foreach ($rows as $row) {
            $result[] = [
                'date'       => (string) ($row->date ?? ''),
                'success'    => (bool) ($row->success ?? false),
                'id_type'    => (string) ($row->id_type ?? ''),
                'identifier' => (string) ($row->identifier ?? ''),
                'ip_address' => (string) ($row->ip_address ?? ''),
                'user_agent' => (string) ($row->user_agent ?? ''),
            ];
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectAuditLog(User $user): array
    {
        /** @var AuditLogModel $auditModel */
        $auditModel = model(AuditLogModel::class);
        $rows       = $auditModel->recentForUser((int) $user->id, 500);
        $result     = [];

        foreach ($rows as $row) {
            $result[] = [
                'created_at' => (string) ($row->created_at ?? ''),
                'event_type' => (string) ($row->event_type ?? ''),
                'ip_address' => (string) ($row->ip_address ?? ''),
                'user_agent' => (string) ($row->user_agent ?? ''),
                'metadata'   => $row->getMetadata(),
            ];
        }

        return $result;
    }

    /**
     * Returns metadata about historic password hashes (count + dates only;
     * never the hashes themselves — those are bcrypt strings, not user data).
     *
     * @return array<string, mixed>
     */
    private function collectPasswordHistoryMeta(User $user): array
    {
        /** @var PasswordHistoryModel $history */
        $history = model(PasswordHistoryModel::class);

        $count = $history->where('user_id', $user->id)->countAllResults();

        return ['count' => $count];
    }

    /**
     * Returns counts of remaining/used backup codes.
     *
     * @return array<string, int>
     */
    private function collectBackupCodesMeta(User $user): array
    {
        /** @var TotpBackupCodeModel $backup */
        $backup = model(TotpBackupCodeModel::class);

        $remaining = $backup->where('user_id', $user->id)->where('used_at')->countAllResults();
        $used      = $backup->where('user_id', $user->id)->where('used_at !=')->countAllResults();

        return ['remaining' => $remaining, 'used' => $used];
    }
}
