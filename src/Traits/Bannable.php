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

use Daycry\Auth\Models\UserModel;

trait Bannable
{
    private function bannableUserModel(): UserModel
    {
        /** @var UserModel */
        return model(UserModel::class);
    }

    /**
     * Is the user banned?
     */
    public function isBanned(): bool
    {
        return $this->status && $this->status === 'banned';
    }

    /**
     * Ban the user from logging in.
     *
     * @return $this
     */
    public function ban(?string $message = null): self
    {
        $this->status         = 'banned';
        $this->status_message = $message;

        $this->bannableUserModel()->save($this);

        return $this;
    }

    /**
     * Unban the user and allow them to login
     *
     * @return $this
     */
    public function unBan(): self
    {
        $this->status         = null;
        $this->status_message = null;

        $this->bannableUserModel()->save($this);

        return $this;
    }

    /**
     * Returns the ban message.
     */
    public function getBanMessage(): ?string
    {
        return $this->status_message;
    }
}
