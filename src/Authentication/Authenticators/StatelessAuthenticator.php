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

/**
 * Base class for stateless authenticators (AccessToken, JWT).
 *
 * Provides shared login/logout/loggedIn/loginById logic.
 * Concrete classes must implement getTokenFromRequest() to extract
 * the raw credential from the current HTTP request.
 */
abstract class StatelessAuthenticator extends Base
{
    /**
     * Returns the raw token extracted from the current request.
     * Returns an empty string when no token is present.
     */
    abstract protected function getTokenFromRequest(): string;

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
     * Since stateless authenticators carry no session state,
     * the token is re-validated on every request.
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
