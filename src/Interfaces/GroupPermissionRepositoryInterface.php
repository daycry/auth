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

/**
 * Application contract for replacing GroupPermissionRepository without inheritance.
 */
interface GroupPermissionRepositoryInterface
{
    /**
     * Persist the desired group IDs for a user in one transaction.
     *
     * @param list<int|string> $cache    Desired group IDs
     * @param list<int|string> $existing Currently persisted group IDs
     */
    public function saveUserGroups(int|string $userId, array $cache, array $existing): void;

    /**
     * Persist the desired permission IDs for a user in one transaction.
     *
     * @param list<int|string> $cache    Desired permission IDs
     * @param list<int|string> $existing Currently persisted permission IDs
     */
    public function saveUserPermissions(int|string $userId, array $cache, array $existing): void;

    /**
     * Replaces a group's permission set with $permissionIds in one
     * transaction, so a failed insert never leaves the group without
     * permissions.
     *
     * @param int|string       $groupId
     * @param list<int|string> $permissionIds
     *
     * @return bool False when the transaction was rolled back
     */
    public function syncGroupPermissions($groupId, array $permissionIds): bool;
}
