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

namespace Daycry\Auth\Authentication\Authenticators;

use Daycry\Auth\Entities\User;
use Daycry\Auth\Exceptions\AuthenticationException;
use Daycry\Auth\Result;

/**
 * Base class for stateless authenticators (AccessToken, JWT).
 *
 * Provides shared login/logout/loggedIn/loginById logic.
 * Concrete classes must implement getTokenFromRequest() to extract
 * the raw credential from the current HTTP request.
 *
 * Note on memoization: loggedIn() short-circuits via `$this->user !== null`
 * after the first successful attempt(). The token is only re-validated on
 * the very first call per request. Subsequent calls within the same request
 * return immediately without hitting the DB or JWT verification again.
 */
abstract class StatelessAuthenticator extends Base
{
    /**
     * Returns the raw token extracted from the current request.
     * Returns an empty string when no token is present.
     */
    abstract protected function getTokenFromRequest(): string;

    /**
     * Attempts to authenticate with the given credentials (the request
     * token when none is given). Used by loggedIn().
     *
     * @param array<string, mixed> $credentials Expects a `token` key
     *
     * @throws AuthenticationException
     */
    abstract public function attempt(array $credentials = []): Result;

    /**
     * Returns $credentials, or the request token when they carry none.
     *
     * @param array<string, mixed> $credentials
     *
     * @return array<string, mixed>
     */
    protected function resolveTokenCredentials(array $credentials): array
    {
        if (! array_key_exists('token', $credentials) || empty($credentials['token'])) {
            return ['token' => $this->getTokenFromRequest()];
        }

        return $credentials;
    }

    /**
     * The failed Result for a request that carries no token.
     */
    protected function noTokenResult(): Result
    {
        return new Result([
            'success' => false,
            'reason'  => lang(
                'Auth.noToken',
                [service('settings')->get('Auth.authenticatorHeader')[$this->method]],
            ),
        ]);
    }

    /**
     * Never persists the raw, replayable bearer credential in the login log:
     * records a non-reversible SHA-256 fingerprint instead (for access tokens
     * the same hash they are stored under), so logs stay correlatable
     * without being usable to authenticate.
     */
    public function getLogCredentials(array $credentials): mixed
    {
        $this->authType = $this->method;

        $token = $credentials['token'] ?? '';

        return $token === '' ? '' : hash('sha256', (string) $token);
    }

    /**
     * Logs the given user in by saving them to the class.
     */
    public function login(User $user, bool $actions = true): void
    {
        $this->user = $user;
    }

    /**
     * Logs the current user out.
     */
    public function logout(): void
    {
        $this->user = null;
    }

    /**
     * Checks if the user is currently logged in.
     *
     * On the first call per request, the token from the HTTP request is
     * validated (DB lookup for AccessToken, signature check for JWT).
     * Once a user is resolved, subsequent calls short-circuit via the
     * in-memory `$this->user` reference — no repeated validation.
     */
    public function loggedIn(): bool
    {
        if ($this->user !== null) {
            return true;
        }

        return $this->attempt(['token' => $this->getTokenFromRequest()])->isOK();
    }

    /**
     * Logs a user in based on their ID.
     *
     * @param int|string $userId
     *
     * @throws AuthenticationException
     */
    public function loginById($userId): void
    {
        $user = $this->provider->findById($userId);

        if ($user === null) {
            throw AuthenticationException::forInvalidUser();
        }

        $this->login($user);
    }
}
