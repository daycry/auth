<?php

declare(strict_types=1);

namespace Daycry\Auth\Test;

use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Entities\User;

/**
 * Trait AuthenticationTesting
 *
 * Helper methods for testing using Shield.
 */
trait AuthenticationTesting
{
    /**
     * Logs the user for testing purposes.
     *
     * @param bool $pending Whether pending login state or not.
     *
     * @return $this
     */
    public function actingAs(User $user, bool $pending = false): self
    {
        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        if ($pending) {
            $authenticator->startLogin($user);
        } else {
            $authenticator->login($user);
        }

        return $this;
    }
}
