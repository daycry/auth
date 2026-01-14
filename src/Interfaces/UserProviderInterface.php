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

interface UserProviderInterface
{
    /**
     * Locates a User object by ID.
     *
     * @param int|string $id
     */
    public function findById($id): ?User;

    /**
     * Locate a User object by the given credentials.
     *
     * @param array<string, string> $credentials
     */
    public function findByCredentials(array $credentials): ?User;

    /**
     * Updates the user's last active date.
     */
    public function updateActiveDate(User $user): void;

    /**
     * Check if a user exists in the database.
     *
     * @param array|object $data
     */
    public function save($data): bool;
}
