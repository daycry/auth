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

use CodeIgniter\I18n\Time;
use Daycry\Auth\Models\AuditLogModel;
use Daycry\Auth\Models\BaseModel;
use Daycry\Auth\Models\DeviceSessionModel;
use Daycry\Auth\Models\LoginModel;
use Daycry\Auth\Models\RememberModel;
use RuntimeException;
use Throwable;

/**
 * Maintenance CLI that purges stale auth records.
 *
 * Run this on a schedule (cron / daycry/jobs) instead of relying on the
 * probabilistic, on-the-login-request purge:
 *   php spark auth:purge
 *   php spark auth:purge --days 7
 *
 * Removes:
 *   - expired remember-me tokens (`auth_remember_tokens`)
 *   - terminated device sessions older than --days (default 30)
 */
class PurgeCommand extends BaseCommand
{
    protected $name        = 'auth:purge';
    protected $description = 'Purge expired remember-me tokens and old terminated device sessions.';
    protected $usage       = 'auth:purge [--days <n>]';

    /**
     * Command's Options
     *
     * @var array<string, string>
     */
    protected $options = [
        '--days'       => 'Age in days above which terminated device sessions are removed (default 30).',
        '--logins'     => 'Login retention in days (0 disables cleanup; defaults to AuthSecurity.loginRetentionDays).',
        '--audit'      => 'Audit retention in days (0 disables cleanup; defaults to AuthSecurity.auditRetentionDays).',
        '--batch-size' => 'Maximum history rows per DELETE (default 1000, maximum 10000).',
    ];

    public function run(array $params): int
    {
        $days = (int) ($params['days'] ?? 30);

        if ($days <= 0) {
            $days = 30;
        }

        try {
            model(RememberModel::class)->purgeOldRememberTokens();
            model(DeviceSessionModel::class)->purgeOldSessions($days);

            $loginDays = (int) ($params['logins'] ?? setting('AuthSecurity.loginRetentionDays') ?? 0);
            $auditDays = (int) ($params['audit'] ?? setting('AuthSecurity.auditRetentionDays') ?? 0);
            $batchSize = max(1, min(10000, (int) ($params['batch-size'] ?? 1000)));

            if ($loginDays > 0) {
                $this->purgeHistory(model(LoginModel::class), 'date', $loginDays, $batchSize);
            }
            if ($auditDays > 0) {
                $this->purgeHistory(model(AuditLogModel::class), 'created_at', $auditDays, $batchSize);
            }

            $this->write(
                'Purged expired remember-me tokens and terminated device sessions older than ' . $days . ' days.',
                'green',
            );

            return 0;
        } catch (Throwable $e) {
            $this->error('Purge failed: ' . $e->getMessage());

            return 1;
        }
    }

    /**
     * Indexed, bounded statements keep retention from becoming one massive write.
     */
    protected function purgeHistory(BaseModel $model, string $dateColumn, int $days, int $batchSize): void
    {
        $cutoff = Time::now()->subDays($days)->format('Y-m-d H:i:s');

        do {
            $rows = $model->builder()->select('id')->where($dateColumn . ' <', $cutoff)
                ->orderBy($dateColumn)->orderBy('id')->limit($batchSize)->get()->getResultArray();
            if ($rows === []) {
                break;
            }
            $deleted = $model->builder()->whereIn('id', array_column($rows, 'id'))
                ->where($dateColumn . ' <', $cutoff)->delete();
            if ($deleted === false) {
                throw new RuntimeException('History retention could not delete a batch.');
            }
        } while (count($rows) === $batchSize);
    }
}
