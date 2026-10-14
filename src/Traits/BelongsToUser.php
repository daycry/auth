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

use Daycry\Auth\Entities\User;

/**
 * Lazy, memoized access to the user referenced by a pivot entity's
 * `user_id` attribute (GroupUser, PermissionUser).
 */
trait BelongsToUser
{
    private ?User $user = null;

    /**
     * Returns the related user, resolved through the configured user provider.
     *
     * @return User|null
     */
    public function getUser()
    {
        if ($this->user instanceof User) {
            return $this->user;
        }

        $this->user = auth()->getProvider()->findById($this->attributes['user_id']);

        return $this->user;
    }
}
