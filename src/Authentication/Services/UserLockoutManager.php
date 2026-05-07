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

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use Config\Database;
use Daycry\Auth\Config\Auth as AuthConfig;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Interfaces\UserProviderInterface;
use Daycry\Auth\Result;
use Daycry\Auth\Services\AuditLogger;

/**
 * Manages per-user account lockout after repeated failed login attempts.
 *
 * Extracted from Session authenticator to keep lockout logic in a single,
 * testable place.
 */
class UserLockoutManager
{
    private ?BaseConnection $db = null;
    private ?string $usersTable = null;

    public function __construct(
        private readonly UserProviderInterface $provider,
    ) {
    }

    /**
     * Lazy-resolve the DB connection and users table name from config.
     */
    private function db(): BaseConnection
    {
        if ($this->db === null) {
            /** @var AuthConfig $authConfig */
            $authConfig       = config('Auth');
            $this->db         = Database::connect($authConfig->DBGroup);
            $this->usersTable = $authConfig->tables['users'];
        }

        return $this->db;
    }

    private function usersTable(): string
    {
        $this->db();

        return (string) $this->usersTable;
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

        (new AuditLogger())->record(AuditLogger::EVENT_USER_UNLOCKED, (int) $user->id, [
            'reason' => 'lockout_expired',
        ]);

        return null;
    }

    /**
     * Records a failed login attempt for the given user.
     *
     * Increments the failure counter atomically (single UPDATE with
     * a SQL expression to avoid lost-update race conditions under
     * concurrent failed logins) and then locks the account when the
     * configured threshold is reached.
     */
    public function recordFailedAttempt(User $user): void
    {
        $maxAttempts = (int) setting('AuthSecurity.userMaxAttempts');

        if ($maxAttempts <= 0) {
            return;
        }

        $db    = $this->db();
        $table = $this->usersTable();

        // Atomic increment — `false` on the third arg disables value escaping.
        $db->table($table)
            ->where('id', $user->id)
            ->set('failed_login_count', 'failed_login_count + 1', false)
            ->update();

        // Re-read the post-increment count to evaluate the threshold.
        $row = $db->table($table)
            ->select('failed_login_count, locked_until')
            ->where('id', $user->id)
            ->get()
            ->getRow();

        if ($row === null) {
            return;
        }

        $count = (int) ($row->failed_login_count ?? 0);

        if ($count >= $maxAttempts && $row->locked_until === null) {
            $lockedUntil = Time::now()
                ->addSeconds((int) setting('AuthSecurity.userLockoutTime'))
                ->format('Y-m-d H:i:s');

            $db->table($table)
                ->where('id', $user->id)
                ->update(['locked_until' => $lockedUntil]);

            (new AuditLogger())->record(AuditLogger::EVENT_USER_LOCKED, (int) $user->id, [
                'failed_login_count' => $count,
                'locked_until'       => $lockedUntil,
            ]);
        }
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
