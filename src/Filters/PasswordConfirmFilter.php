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

/**
 * Forces a fresh password confirmation before sensitive actions
 * ("sudo mode"). Apply on routes that change critical state (email,
 * 2FA settings, API token generation, OAuth unlink, account deletion).
 *
 * Behaviour:
 *   - Reads `password_confirmed_at` from the user's session.
 *   - If absent, or older than `AuthSecurity::$passwordConfirmationLifetime`
 *     seconds, redirects to /auth/confirm-password with the original URL
 *     stashed in `intended_url` for post-confirmation redirect.
 *   - Otherwise, the request proceeds untouched.
 *
 * Anonymous requests are left alone: this filter is **not** a
 * replacement for `auth` / `session`. Apply it AFTER an authentication
 * filter on the same route.
 */
class PasswordConfirmFilter implements FilterInterface
{
    /**
     * @param array|null $arguments Optional per-route lifetime override in
     *                              seconds, e.g. `password-confirm:60` requires
     *                              a confirmation no older than 60 seconds for
     *                              that route regardless of the global setting.
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! auth()->loggedIn()) {
            return null; // not our job; let the auth filter handle this
        }

        $lifetime = (int) (setting('AuthSecurity.passwordConfirmationLifetime') ?? 0);

        // A per-route argument overrides the global lifetime, letting more
        // sensitive routes demand a fresher confirmation ("sudo mode").
        if (is_array($arguments) && isset($arguments[0]) && is_numeric($arguments[0])) {
            $lifetime = (int) $arguments[0];
        }

        // 0 means "always require fresh confirmation" — only the
        // confirmation endpoint itself can satisfy it, so any other
        // protected route falls through to the redirect below.
        if ($lifetime > 0 && $this->confirmedRecently($lifetime)) {
            return null;
        }

        // Stash the URL the user wanted to reach so we can come back after
        // a successful confirmation.
        session()->setTempdata('passwordConfirmIntendedUrl', current_url(), 300);

        return redirect()->route('password-confirm-show')
            ->with('error', lang('Auth.passwordConfirmRequired'));
    }

    private function confirmedRecently(int $lifetime): bool
    {
        $confirmedAt = session('password_confirmed_at');

        if (! is_int($confirmedAt) && ! is_numeric($confirmedAt)) {
            return false;
        }

        return ((int) $confirmedAt) + $lifetime >= time();
    }

    /**
     * @param array|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
        // Nothing required.
    }
}
