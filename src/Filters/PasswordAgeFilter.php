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

namespace Daycry\Auth\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;
use Throwable;

/**
 * Forces password rotation: when `AuthSecurity::$passwordMaxAge` is > 0
 * and the user's `password_changed_at` is older than that threshold,
 * the request is redirected to the force-password-reset flow.
 *
 * Apply alongside the `auth` (or `session`) filter on protected routes:
 *
 *     $routes->group('app', ['filter' => 'session,password-age'], ...);
 */
class PasswordAgeFilter implements FilterInterface
{
    /**
     * @param array|null $arguments Unused — settings drive behaviour.
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $maxAge = (int) (setting('AuthSecurity.passwordMaxAge') ?? 0);

        if ($maxAge <= 0 || ! auth()->loggedIn()) {
            return null;
        }

        $user = auth()->user();

        if ($user === null) {
            return null;
        }

        $changedAt = $user->password_changed_at ?? null;

        if ($changedAt === null) {
            // No timestamp recorded — leave it alone (older accounts).
            return null;
        }

        try {
            $changed = $changedAt instanceof Time
                ? $changedAt
                : Time::parse((string) $changedAt);
        } catch (Throwable) {
            return null;
        }

        if ($changed->isAfter(Time::now()->subSeconds($maxAge))) {
            return null; // password is fresh enough
        }

        // Mark the identity as needing reset and bounce the user to the
        // force-reset flow (handled by ForcePasswordResetController).
        $user->forcePasswordReset();

        $route = config('Auth')->forcePasswordResetRedirect();

        return redirect()->to($route)->with('error', lang('Auth.passwordExpired'));
    }

    /**
     * @param array|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
        // Nothing to do.
    }
}
