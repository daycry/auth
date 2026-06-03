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

use Daycry\Auth\Models\DeviceSessionModel;
use Daycry\Auth\Models\RememberModel;
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
        '--days' => 'Age in days above which terminated device sessions are removed (default 30).',
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
}
