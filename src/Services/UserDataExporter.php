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

use CodeIgniter\I18n\Time;
use Daycry\Auth\Authentication\Authenticators\AccessToken;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Enums\IdentityType;
use Daycry\Auth\Exceptions\DataExportLimitException;
use Daycry\Auth\Interfaces\UserDataExporterInterface;
use Daycry\Auth\Models\AuditLogModel;
use Daycry\Auth\Models\DeviceSessionModel;
use Daycry\Auth\Models\LoginModel;
use Daycry\Auth\Models\PasswordHistoryModel;
use Daycry\Auth\Models\TotpBackupCodeModel;
use Daycry\Auth\Models\UserIdentityModel;

/**
 * Redacted portable account data shared by CLI and authenticated self-service.
 */
class UserDataExporter implements UserDataExporterInterface
{
    /**
     * Materialized downloads stay bounded; larger exports use a replacement service.
     */
    protected function recordLimit(): int
    {
        return max(1, min(5000, (int) (setting('AuthSecurity.dataExportMaxRecords') ?? 1000)));
    }

    protected function assertWithinLimit(int $count): void
    {
        if ($count > $this->recordLimit()) {
            throw new DataExportLimitException(lang('Auth.dataExportTooLarge'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        return [
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
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectIdentities(User $user): array
    {
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        $rows = $identityModel->where('user_id', $user->id)->findAll($this->recordLimit() + 1);
        $this->assertWithinLimit(count($rows));
        $result = [];

        foreach ($rows as $row) {
            $type = ($row->type ?? '');

            $entry = [
                'id'           => ($row->id ?? null),
                'type'         => $type,
                'name'         => ($row->name ?? null),
                'expires'      => ($row->expires ?? null),
                'last_used_at' => ($row->last_used_at ?? null),
                'revoked_at'   => ($row->revoked_at ?? null),
                'created_at'   => ($row->created_at ?? null),
            ];

            // Redact secret fields — they are not portable user data and
            // exposing them would defeat the purpose of revoking a token.
            if ($type === IdentityType::EMAIL_PASSWORD->value) {
                $entry['secret']  = ($row->secret ?? null);
                $entry['secret2'] = '<redacted: bcrypt hash>';
            } elseif ($type === AccessToken::ID_TYPE_ACCESS_TOKEN || $type === IdentityType::JWT_REFRESH->value) {
                $entry['secret']  = '<redacted: hashed token>';
                $entry['secret2'] = null;
            } else {
                $entry['secret']  = '<redacted: authentication secret>';
                $entry['secret2'] = null;
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

        $rows = $deviceModel->where('user_id', $user->id)->findAll($this->recordLimit() + 1);
        $this->assertWithinLimit(count($rows));
        $result = [];

        foreach ($rows as $row) {
            $result[] = [
                'uuid'          => ($row->uuid ?? null),
                'device_name'   => ($row->device_name ?? null),
                'ip_address'    => ($row->ip_address ?? null),
                'user_agent'    => ($row->user_agent ?? null),
                'last_active'   => ($row->last_active ?? null),
                'logged_out_at' => ($row->logged_out_at ?? null),
                'trusted_until' => ($row->trusted_until ?? null),
                'created_at'    => ($row->created_at ?? null),
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

        $rows = $loginModel->where('user_id', $user->id)->orderBy('id', 'DESC')->findAll($this->recordLimit() + 1);
        $this->assertWithinLimit(count($rows));
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
        $rows       = $auditModel->where('user_id', $user->id)->orderBy('id', 'DESC')->findAll($this->recordLimit() + 1);
        $this->assertWithinLimit(count($rows));
        $result = [];

        foreach ($rows as $row) {
            $result[] = [
                'created_at' => (string) ($row->created_at ?? ''),
                'event_type' => (string) ($row->event_type ?? ''),
                'ip_address' => (string) ($row->ip_address ?? ''),
                'user_agent' => (string) ($row->user_agent ?? ''),
                'metadata'   => ['redacted' => true],
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
