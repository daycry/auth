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

use CodeIgniter\Config\Factories;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Entities\UserIdentity;
use Daycry\Auth\Interfaces\ActionInterface;
use Daycry\Auth\Models\UserIdentityModel;

/**
 * Coordinates post-authentication actions (Email2FA, EmailActivator, Totp2FA).
 *
 * Determines which action (if any) a user needs to complete after login
 * and creates the necessary identity records. Session state mutation is
 * left to the caller (Session authenticator).
 */
class PendingActionCoordinator
{
    public function __construct(
        private readonly UserIdentityModel $identityModel,
    ) {
    }

    /**
     * Returns all identity types associated with configured auth actions.
     *
     * @return list<string>
     */
    public function getActionTypes(): array
    {
        $actions = setting('Auth.actions');
        $types   = [];

        foreach ($actions as $actionClass) {
            if ($actionClass === null) {
                continue;
            }

            /** @var ActionInterface $action */
            $action  = Factories::actions($actionClass); // @phpstan-ignore-line
            $types[] = $action->getType();
        }

        return $types;
    }

    /**
     * Gets identities for configured auth actions.
     *
     * @return list<UserIdentity>
     */
    public function getIdentitiesForAction(User $user): array
    {
        return $this->identityModel->getIdentitiesByTypes(
            $user,
            $this->getActionTypes(),
        );
    }

    /**
     * Finds the first pending action identity for the user.
     *
     * Returns an array with the action class and identity extra data
     * if a pending action is found, or null if none exists.
     *
     * @return array{actionClass: class-string<ActionInterface>, message: string|null}|null
     */
    public function findPendingAction(User $user): ?array
    {
        $authActions = setting('Auth.actions');

        foreach ($authActions as $actionClass) {
            if ($actionClass === null) {
                continue;
            }

            /** @var ActionInterface $action */
            $action = Factories::actions($actionClass); // @phpstan-ignore-line

            $identity = $this->identityModel->getIdentityByType($user, $action->getType());

            if ($identity instanceof UserIdentity) {
                return [
                    'actionClass' => $actionClass,
                    'message'     => $identity->extra,
                ];
            }
        }

        return null;
    }

    /**
     * Creates identity for the given action type if configured.
     *
     * Returns true if an action was activated, false if skipped or not configured.
     *
     * @param string $type 'register' or 'login'
     */
    public function activateAction(string $type, User $user): bool
    {
        $actionClass = setting('Auth.actions')[$type] ?? null;

        if ($actionClass === null) {
            return false;
        }

        /** @var ActionInterface $action */
        $action = Factories::actions($actionClass); // @phpstan-ignore-line

        // Create identity for the action
        $secret = $action->createIdentity($user);

        // Empty return means the action decided to skip itself
        return $secret !== '';
    }
}
