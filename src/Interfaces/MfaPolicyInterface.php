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
 * Application-replaceable policy for enrollment and session factor proofs.
 */
interface MfaPolicyInterface
{
    /**
     * Whether this account is subject to the configured policy.
     */
    public function requires(User $user): bool;

    /**
     * Whether at least one supported, active second factor is enrolled.
     */
    public function hasFactor(User $user): bool;

    /**
     * Whether the fixed enrollment grace period is still open.
     */
    public function withinGrace(User $user): bool;

    /**
     * Whether this account must enroll before accessing application routes.
     */
    public function enrollmentRequired(User $user): bool;

    /**
     * Whether this session has a factor proof bound to this account.
     */
    public function verified(User $user): bool;

    /**
     * Record a verified factor only after the caller validated the proof.
     */
    public function markVerified(User $user): void;
}
