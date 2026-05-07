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
use Daycry\Auth\Models\DeviceSessionModel;
use Daycry\Auth\Models\UserModel;
use Throwable;

/**
 * Admin CLI to terminate a user's active device sessions.
 *
 * Usage:
 *   php spark auth:sessions terminate -e user@example.com
 *   php spark auth:sessions terminate -i 42
 */
class SessionsCommand extends BaseCommand
{
    protected $name        = 'auth:sessions';
    protected $description = 'Terminate active device sessions for a user.';
    protected $usage       = 'auth:sessions terminate -e <email> | -i <id>';

    /**
     * Command's Options
     *
     * @var array<string, string>
     */
    protected $options = [
        '-e' => 'Target user email.',
        '-i' => 'Target user id.',
    ];

    public function run(array $params): int
    {
        $action = array_shift($params) ?? '';

        if ($action !== 'terminate') {
            CLI::error('Unsupported action. Supported: terminate.');

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
            /** @var DeviceSessionModel $deviceModel */
            $deviceModel = model(DeviceSessionModel::class);
            $deviceModel->terminateAllForUser($user);

            CLI::write('Terminated all device sessions for user ' . $user->id, 'green');

            return 0;
        } catch (Throwable $e) {
            CLI::error('Session termination failed: ' . $e->getMessage());

            return 1;
        }
    }
}
