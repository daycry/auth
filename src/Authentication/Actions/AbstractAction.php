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

namespace Daycry\Auth\Authentication\Actions;

use CodeIgniter\Exceptions\RuntimeException;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Entities\UserIdentity;
use Daycry\Auth\Interfaces\ActionInterface;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Traits\Viewable;

/**
 * Base class for post-authentication actions (Email2FA, EmailActivator, Totp2FA).
 *
 * Provides shared helpers so concrete actions only implement their
 * action-specific logic.
 */
abstract class AbstractAction implements ActionInterface
{
    use Viewable;

    /**
     * The identity type this action operates on.
     */
    protected string $type;

    /**
     * Returns the pending-login user from the session authenticator.
     *
     * @throws RuntimeException When no pending user exists.
     */
    protected function requirePendingUser(): User
    {
        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        $user = $authenticator->getPendingUser();

        if ($user === null) {
            throw new RuntimeException('Cannot get the pending login User.');
        }

        return $user;
    }

    /**
     * Returns the session authenticator instance.
     */
    protected function getSessionAuthenticator(): Session
    {
        /** @var Session */
        return auth('session')->getAuthenticator();
    }

    /**
     * Returns the UserIdentityModel instance.
     */
    protected function getIdentityModel(): UserIdentityModel
    {
        /** @var UserIdentityModel */
        return model(UserIdentityModel::class);
    }

    /**
     * Returns an identity for this action type for the given user.
     */
    protected function getIdentity(User $user): ?UserIdentity
    {
        return $this->getIdentityModel()->getIdentityByType($user, $this->type);
    }

    /**
     * Returns the string type of the action class.
     */
    public function getType(): string
    {
        return $this->type;
    }
}
