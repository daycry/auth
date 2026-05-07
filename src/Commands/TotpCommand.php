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
use Daycry\Auth\Models\UserModel;
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
        $action = array_shift($params) ?? '';

        if ($action !== 'reset') {
            CLI::error('Unsupported action. Supported: reset.');

            return 1;
        }

        $email = (string) (CLI::getOption('e') ?? '');
        $id    = (string) (CLI::getOption('i') ?? '');

        if ($email === '' && $id === '') {
            CLI::error('Specify -e <email> or -i <id>.');

            return 1;
        }

        /** @var UserModel $userModel */
        $userModel = model(UserModel::class);
        $user      = $id !== ''
            ? $userModel->findById((int) $id)
            : $userModel->findByCredentials(['email' => $email]);

        if ($user === null) {
            CLI::error('User not found.');

            return 1;
        }

        try {
            $user->disableTotp();

            (new AuditLogger())->record(
                AuditLogger::EVENT_TOTP_ADMIN_RESET,
                (int) $user->id,
                ['initiator' => 'cli'],
            );

            CLI::write('TOTP reset for user ' . $user->id . '. Backup codes were also purged.', 'green');

            return 0;
        } catch (Throwable $e) {
            CLI::error('TOTP reset failed: ' . $e->getMessage());

            return 1;
        }
    }
}
