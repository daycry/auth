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
use Daycry\Auth\Entities\User;
use Daycry\Auth\Services\AuditLogger;
use Throwable;

/**
 * Admin CLI to manage a user's TOTP 2FA enrollment.
 *
 * Usage:
 *   php spark auth:totp reset -e user@example.com
 *   php spark auth:totp reset -i 42
 */
class TotpCommand extends BaseCommand
{
    protected $name        = 'auth:totp';
    protected $description = 'Admin TOTP management (reset).';
    protected $usage       = 'auth:totp reset -e <email> | -i <id>';

    /**
     * @var array<string, string>
     */
    protected $options = [
        '-e' => 'Target user email.',
        '-i' => 'Target user id.',
    ];

    public function run(array $params): int
    {
        $action = $params[0] ?? '';

        if ($action !== 'reset') {
            $this->error('Unsupported action. Supported: reset.');

            return 1;
        }

        $user = $this->resolveTargetUser($params);

        if (! $user instanceof User) {
            return 1;
        }

        try {
            $user->disableTotp();

            service('auditLogger')->record(
                AuditLogger::EVENT_TOTP_ADMIN_RESET,
                (int) $user->id,
                ['initiator' => 'cli'],
            );

            $this->write('TOTP reset for user ' . $user->id . '. Backup codes were also purged.', 'green');

            return 0;
        } catch (Throwable $e) {
            $this->error('TOTP reset failed: ' . $e->getMessage());

            return 1;
        }
    }
}
