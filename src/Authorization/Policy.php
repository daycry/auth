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

namespace Daycry\Auth\Authorization;

use Daycry\Auth\Entities\User;

/**
 * Base class for class-based authorization policies.
 *
 * A policy groups authorization rules for a single resource type. Each
 * action becomes a method that receives the authenticated user (or null)
 * plus any context arguments and returns a boolean (or
 * {@see PolicyResponse} for richer semantics):
 *
 *     class PostPolicy extends Policy
 *     {
 *         public function update(?User $user, Post $post): bool
 *         {
 *             return $user !== null && $user->id === $post->author_id;
 *         }
 *
 *         public function delete(?User $user, Post $post): PolicyResponse
 *         {
 *             if ($user === null) {
 *                 return PolicyResponse::deny('You must be logged in.');
 *             }
 *
 *             return $user->id === $post->author_id
 *                 ? PolicyResponse::allow()
 *                 : PolicyResponse::deny('Only the author can delete this post.');
 *         }
 *     }
 *
 * Implement {@see before()} to short-circuit checks (e.g. admins bypass
 * everything). Returning a non-null value from `before()` skips the
 * action method entirely.
 */
abstract class Policy
{
    /**
     * Runs before any action method. Return true to allow, false /
     * a deny PolicyResponse to deny, or null to fall through to the
     * action-specific method.
     *
     * @param User|null   $user      Logged-in user or null for guests.
     * @param string      $ability   The action being checked (e.g. 'update').
     * @param list<mixed> $arguments Additional context.
     */
    public function before(?User $user, string $ability, array $arguments): bool|PolicyResponse|null
    {
        return null;
    }
}
