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

namespace Daycry\Auth\Authentication\Services;

use CodeIgniter\I18n\Time;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Models\DeviceSessionModel;
use Daycry\Auth\Models\LoginModel;
use Throwable;

/**
 * Detects "suspicious" login characteristics by comparing the current
 * login's IP address and User-Agent against the user's recent history.
 *
 * Output is a list of flag strings ({@see FLAG_*}). The empty list means
 * "not suspicious" — caller should not raise an alert.
 *
 * Lookups go to {@see LoginModel} (auth_logs / auth_logins) and
 * {@see DeviceSessionModel} (auth_device_sessions). Both already exist
 * — this service is pure read-only analysis.
 */
class SuspiciousLoginDetector
{
    public const FLAG_NEW_IP     = 'new_ip';
    public const FLAG_NEW_DEVICE = 'new_device';

    /**
     * Days to look back when deciding whether an IP / device is new.
     */
    private const LOOKBACK_DAYS = 30;

    /**
     * Returns the list of suspicious flags raised by this login.
     * Returns an empty list when the login looks routine.
     *
     * @return list<string>
     */
    public function analyse(User $user, string $ipAddress, ?string $userAgent): array
    {
        $flags = [];

        try {
            if ($ipAddress !== '' && $this->isNewIp($user, $ipAddress)) {
                $flags[] = self::FLAG_NEW_IP;
            }

            if ($userAgent !== null && $userAgent !== '' && $this->isNewDevice($user, $userAgent)) {
                $flags[] = self::FLAG_NEW_DEVICE;
            }
        } catch (Throwable $e) {
            log_message('warning', 'SuspiciousLoginDetector::analyse failed: {message}', [
                'message' => $e->getMessage(),
            ]);
        }

        return $flags;
    }

    /**
     * True when this IP has not been seen for the user in the last
     * {@see LOOKBACK_DAYS} days.
     */
    public function isNewIp(User $user, string $ipAddress): bool
    {
        /** @var LoginModel $loginModel */
        $loginModel = model(LoginModel::class);

        $cutoff = Time::now()->subDays(self::LOOKBACK_DAYS)->toDateTimeString();

        $count = $loginModel
            ->where('user_id', $user->id)
            ->where('success', 1)
            ->where('ip_address', $ipAddress)
            ->where('date >=', $cutoff)
            ->countAllResults();

        return $count === 0;
    }

    /**
     * True when this user-agent has not been seen for the user in the
     * last {@see LOOKBACK_DAYS} days. Compared against active and
     * historical device sessions.
     */
    public function isNewDevice(User $user, string $userAgent): bool
    {
        /** @var DeviceSessionModel $deviceModel */
        $deviceModel = model(DeviceSessionModel::class);

        $count = $deviceModel
            ->where('user_id', $user->id)
            ->where('user_agent', $userAgent)
            ->countAllResults();

        return $count === 0;
    }
}
