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
use Throwable;

/**
 * Application contract for replacing UserAnonymizer without inheritance.
 */
interface UserAnonymizerInterface
{
    /**
     * @param string   $initiator Recorded in the audit entry ('cli', 'self-service', ...)
     * @param int|null $actorId   Who performed it (the user themselves, an admin, null for CLI)
     *
     * @throws Throwable When any step fails (nothing is changed then)
     */
    public function anonymize(User $user, string $initiator = 'cli', ?int $actorId = null): void;
}
