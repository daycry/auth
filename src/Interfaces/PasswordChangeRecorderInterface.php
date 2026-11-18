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

namespace Daycry\Auth\Interfaces;

use Daycry\Auth\Entities\User;

/**
 * Application contract for replacing PasswordChangeRecorder without inheritance.
 */
interface PasswordChangeRecorderInterface
{
    /**
     * @param string|null $previousHash The bcrypt hash *before* this change.
     *                                  Pass null when there is no prior hash
     *                                  (first time set).
     */
    public function record(User $user, ?string $previousHash): void;
}
