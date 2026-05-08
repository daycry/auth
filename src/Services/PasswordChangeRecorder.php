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
use Daycry\Auth\Entities\User;
use Daycry\Auth\Models\PasswordHistoryModel;
use Daycry\Auth\Models\UserModel;
use Throwable;

/**
 * Centralises bookkeeping that must run whenever a password actually
 * changes on persistence:
 *   - records the *previous* hash in `auth_password_history`
 *     (when {@see \Daycry\Auth\Config\AuthSecurity::$passwordHistorySize} > 0)
 *   - stamps `users.password_changed_at`
 *
 * Call {@see record()} *before* persisting the new password (we need the
 * prior hash) but *after* validation passes. Failures inside the recorder
 * never propagate — the user-visible flow is the source of truth.
 */
class PasswordChangeRecorder
{
    /**
     * @param string|null $previousHash The bcrypt hash *before* this change.
     *                                  Pass null when there is no prior hash
     *                                  (first time set).
     */
    public function record(User $user, ?string $previousHash): void
    {
        try {
            $retain = (int) (setting('AuthSecurity.passwordHistorySize') ?? 0);

            if ($retain > 0 && $previousHash !== null && $previousHash !== '') {
                /** @var PasswordHistoryModel $history */
                $history = model(PasswordHistoryModel::class);
                $history->recordHash($user, $previousHash, $retain);
            }

            $this->stampChangedAt((int) $user->id);
        } catch (Throwable $e) {
            log_message('warning', 'PasswordChangeRecorder::record failed: {message}', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Updates `users.password_changed_at = NOW()` atomically. Done as a
     * direct UPDATE (not via entity save) so it is BC with custom user
     * providers that don't expose the column on their entity yet.
     */
    private function stampChangedAt(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        try {
            /** @var UserModel $userModel */
            $userModel = model(UserModel::class);

            // Use the underlying query builder so the write bypasses
            // `$allowedFields` (the column is intentionally not on that
            // list to keep `password_changed_at` invisible to mass
            // assignment via $user->fill() etc.).
            $userModel->builder()
                ->where('id', $userId)
                ->update(['password_changed_at' => Time::now()->toDateTimeString()]);
        } catch (Throwable) {
            // Column may not exist yet (migration not run) — silent skip.
        }
    }
}
