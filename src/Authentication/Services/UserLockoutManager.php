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
use Daycry\Auth\Interfaces\UserProviderInterface;
use Daycry\Auth\Result;

/**
 * Manages per-user account lockout after repeated failed login attempts.
 *
 * Extracted from Session authenticator to keep lockout logic in a single,
 * testable place.
 */
class UserLockoutManager
{
    public function __construct(
        private readonly UserProviderInterface $provider,
    ) {
    }

    /**
     * Checks whether the user account is currently locked.
     *
     * If the lockout has expired, the counter is reset automatically.
     *
     * @return Result|null A failure Result when locked, null when not locked.
     */
    public function isLockedOut(User $user): ?Result
    {
        $maxAttempts = (int) setting('AuthSecurity.userMaxAttempts');

        if ($maxAttempts <= 0) {
            return null;
        }

        if ($user->locked_until === null) {
            return null;
        }

        $lockedUntil = Time::parse((string) $user->locked_until);

        if ($lockedUntil->isAfter(Time::now())) {
            $minutesLeft = (int) ceil(Time::now()->difference($lockedUntil)->getMinutes());

            return new Result([
                'success' => false,
                'reason'  => lang('Auth.userLockedOut', [$minutesLeft]),
            ]);
        }

        // Lockout has expired — reset counter automatically
        $this->provider->update($user->id, ['failed_login_count' => 0, 'locked_until' => null]);

        return null;
    }

    /**
     * Records a failed login attempt for the given user.
     *
     * Increments the failure counter and locks the account when the
     * configured threshold is reached.
     */
    public function recordFailedAttempt(User $user): void
    {
        $maxAttempts = (int) setting('AuthSecurity.userMaxAttempts');

        if ($maxAttempts <= 0) {
            return;
        }

        $count = ((int) ($user->failed_login_count ?? 0)) + 1;
        $data  = ['failed_login_count' => $count];

        if ($count >= $maxAttempts) {
            $data['locked_until'] = Time::now()
                ->addSeconds((int) setting('AuthSecurity.userLockoutTime'))
                ->format('Y-m-d H:i:s');
        }

        $this->provider->update($user->id, $data);
    }

    /**
     * Resets the failed-login counter on successful authentication.
     */
    public function resetOnSuccess(User $user): void
    {
        $maxAttempts = (int) setting('AuthSecurity.userMaxAttempts');

        if ($maxAttempts <= 0) {
            return;
        }

        if (((int) ($user->failed_login_count ?? 0)) > 0 || $user->locked_until !== null) {
            $this->provider->update($user->id, ['failed_login_count' => 0, 'locked_until' => null]);
        }
    }
}
