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

namespace Tests\Support;

use Daycry\Auth\Entities\User;
use Daycry\Auth\Models\UserModel;

trait FakeUser
{
    private User $user;

    protected function setUpFakeUser(): void
    {
        $this->user = fake(UserModel::class);
    }
}
