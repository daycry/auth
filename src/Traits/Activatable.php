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

namespace Daycry\Auth\Traits;

use Daycry\Auth\Authentication\Actions\EmailActivator;
use Daycry\Auth\Models\UserModel;

trait Activatable
{
    private function activatableUserModel(): UserModel
    {
        /** @var UserModel */
        return model(UserModel::class);
    }

    /**
     * Returns true if the user has been activated
     * and activation is required after registration.
     */
    public function isActivated(): bool
    {
        // If activation is not required, then we're always active.
        return ! $this->shouldActivate() || $this->active;
    }

    /**
     * Returns true if the user has not been activated.
     */
    public function isNotActivated(): bool
    {
        return ! $this->isActivated();
    }

    /**
     * Activates the user.
     */
    public function activate(): void
    {
        $this->activatableUserModel()->update($this->id, ['active' => 1]);
    }

    /**
     * Deactivates the user.
     */
    public function deactivate(): void
    {
        $this->activatableUserModel()->update($this->id, ['active' => 0]);
    }

    /**
     * Does the Auth actions require activation?
     *
     * True for EmailActivator and its subclasses, and, for backwards
     * compatibility, for any custom action whose class name contains
     * 'Activator'.
     */
    private function shouldActivate(): bool
    {
        $action = setting('Auth.actions')['register'] ?? '';

        return is_string($action)
            && (is_a($action, EmailActivator::class, true) || str_contains($action, 'Activator'));
    }
}
