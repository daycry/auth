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

use CodeIgniter\Exceptions\LogicException;
use CodeIgniter\I18n\Time;
use Daycry\Auth\Entities\Group;
use Daycry\Auth\Exceptions\AuthorizationException;
use Daycry\Auth\Models\GroupModel;
use Daycry\Auth\Models\GroupUserModel;
use Daycry\Auth\Models\PermissionGroupModel;
use Daycry\Auth\Models\PermissionModel;
use Daycry\Auth\Models\PermissionUserModel;

trait Authorizable
{
    protected ?array $groupCache       = null;
    protected ?array $permissionsCache = null;
    protected ?array $groups           = null;
    protected ?array $permissions      = null;

    /**
     * Adds one or more groups to the current User.
     *
     * @return $this
     */
    public function addGroup(string ...$groups): self
    {
        $this->populateGroups();

        $groupCount = count($this->groupCache);

        foreach ($groups as $group) {
            $group = strtolower($group);

            // don't allow dupes
            if (in_array($group, $this->groupCache, true)) {
                continue;
            }

            $groupsNames = ($this->groups) ? array_values($this->groups) : [];

            // make sure it's a valid group
            if (! in_array($group, $groupsNames, true)) {
                throw AuthorizationException::forUnknownGroup($group);
            }

            $this->groupCache[] = $group;
        }

        // Only save the results if there's anything new.
        if (count($this->groupCache) > $groupCount) {
            $this->saveGroups();
        }

        return $this;
    }

    /**
     * Removes one or more groups from the user.
     *
     * @return $this
     */
    public function removeGroup(string ...$groups): self
    {
        $this->populateGroups();

        foreach ($groups as &$group) {
            $group = strtolower($group);
        }

        // Remove from local cache
        $this->groupCache = array_diff($this->groupCache, $groups);

        // Update the database.
        $this->saveGroups();

        return $this;
    }

    /**
     * Given an array of groups, will update the database
     * so only those groups are valid for this user, removing
     * all groups not in this list.
     *
     * @return $this
     *
     * @throws AuthorizationException
     */
    public function syncGroups(string ...$groups): self
    {
        $this->populateGroups();

        foreach ($groups as $group) {
            if (! in_array($group, array_values($this->groups), true)) {
                throw AuthorizationException::forUnknownGroup($group);
            }
        }

        $this->groupCache = $groups;
        $this->saveGroups();

        return $this;
    }

    /**
     * Returns all groups this user is a part of.
     */
    public function getGroups(): ?array
    {
        $this->populateGroups();

        return array_values($this->groupCache);
    }

    /**
     * Returns all permissions this user has
     * assigned directly to them.
     */
    public function getPermissions(): ?array
    {
        $this->populatePermissions();

        return array_values($this->permissionsCache);
    }

    /**
     * Adds one or more permissions to the current user.
     *
     * @return $this
     *
     * @throws AuthorizationException
     */
    public function addPermission(string ...$permissions): self
    {
        $this->populatePermissions();

        $permissionCount = count($this->permissionsCache);

        foreach ($permissions as $permission) {
            $permission = strtolower($permission);

            // don't allow dupes
            if (in_array($permission, $this->permissionsCache, true)) {
                continue;
            }

            $permissionsNames = ($this->permissions) ? array_values($this->permissions) : [];

            // make sure it's a valid group
            if (! in_array($permission, $permissionsNames, true)) {
                throw AuthorizationException::forUnknownPermission($permission);
            }

            $this->permissionsCache[] = $permission;
        }

        // Only save the results if there's anything new.
        if (count($this->permissionsCache) > $permissionCount) {
            $this->savePermissions();
        }

        return $this;
    }

    /**
     * Removes one or more permissions from the current user.
     *
     * @return $this
     */
    public function removePermission(string ...$permissions): self
    {
        $this->populatePermissions();

        foreach ($permissions as &$permission) {
            $permission = strtolower($permission);
        }

        // Remove from local cache
        $this->permissionsCache = array_diff($this->permissionsCache, $permissions);

        // Update the database.
        $this->savePermissions();

        return $this;
    }

    /**
     * Given an array of permissions, will update the database
     * so only those permissions are valid for this user, removing
     * all permissions not in this list.
     *
     * @return $this
     *
     * @throws AuthorizationException
     */
    public function syncPermissions(string ...$permissions): self
    {
        $this->populatePermissions();

        foreach ($permissions as $permission) {
            if (! in_array($permission, array_values($this->permissions), true)) {
                throw AuthorizationException::forUnknownPermission($permission);
            }
        }

        $this->permissionsCache = $permissions;
        $this->savePermissions();

        return $this;
    }

    /**
     * Checks to see if the user has the permission set
     * directly on themselves. This disregards any groups
     * they are part of.
     */
    public function hasPermission(string $permission): bool
    {
        $this->populatePermissions();

        $permission = strtolower($permission);

        return in_array($permission, $this->permissionsCache, true);
    }

    /**
     * Checks user permissions and their group permissions
     * to see if the user has a specific permission or group
     * of permissions.
     *
     * @param string $permissions string(s) consisting of a scope and action, like `users.create`
     */
    public function can(string ...$permissions): bool
    {
        // Get user's permissions and store in cache
        $this->populatePermissions();

        // Check the groups the user belongs to
        $this->populateGroups();

        foreach ($permissions as $permission) {
            // Permission must contain a scope and action
            if (! str_contains($permission, '.')) {
                throw new LogicException(
                    'A permission must be a string consisting of a scope and action, like `users.create`.'
                    . ' Invalid permission: ' . $permission,
                );
            }

            $permission = strtolower($permission);

            if (in_array('*', $this->permissionsCache, true)) {
                return true;
            }

            // Check user's permissions
            if (in_array($permission, $this->permissionsCache, true)) {
                return true;
            }

            if (count($this->groupCache) === 0) {
                return false;
            }

            foreach ($this->groupCache as $group) {
                $group       = model(GroupModel::class)->where('name', $group)->first();
                $permissions = $this->getGroupPermissions($group);

                if (in_array('*', array_column($permissions, 'name'), true)) {
                    return true;
                }

                // Check exact match
                if (isset($permissions) && in_array($permission, array_column($permissions, 'name'), true)) {
                    return true;
                }

                // Check wildcard match
                $check = substr($permission, 0, strpos($permission, '.')) . '.*';
                if (isset($permissions) && in_array($check, array_column($permissions, 'name'), true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Checks to see if the user is a member of one
     * of the groups passed in.
     */
    public function inGroup(string ...$groups): bool
    {
        $this->populateGroups();

        foreach ($groups as $group) {
            if (in_array(strtolower($group), $this->groupCache, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * User for populate all groups
     */
    private function getAllUserGroups(): array
    {
        $userGroupModel = model(GroupUserModel::class);
        $userGroups     = $userGroupModel->getForUser($this);

        $ids = [];

        foreach ($userGroups as $userGroup) {
            $ids[] = $userGroup->group_id;
        }

        $groupModel = model(GroupModel::class);

        if ($ids !== []) {
            return $groupModel->getByIds($ids);
        }

        return [];
    }

    /**
     * User for populate all permissions
     */
    private function getAllUserPermissions(): array
    {
        $userPermissionsModel = model(PermissionUserModel::class);
        $userPermissions      = $userPermissionsModel->getForUser($this);

        $ids = [];

        foreach ($userPermissions as $userPermission) {
            $ids[] = $userPermission->permission_id;
        }

        $permissionModel = model(PermissionModel::class);

        if ($ids !== []) {
            return $permissionModel->getByIds($ids);
        }

        return [];
    }

    /**
     * User for populate all permissions
     */
    private function getGroupPermissions(Group $group): array
    {
        /** @var PermissionGroupModel $groupPermissionsModel */
        $groupPermissionsModel = model(PermissionGroupModel::class);
        $groupPermissions      = $groupPermissionsModel->getForGroup($group);

        $ids = [];

        foreach ($groupPermissions as $groupPermission) {
            $ids[] = $groupPermission->permission_id;
        }

        $permissionModel = model(PermissionModel::class);

        if ($ids !== []) {
            return $permissionModel->getByIds($ids);
        }

        return [];
    }

    /**
     * Used internally to populate the User groups
     * so we hit the database as little as possible.
     */
    private function populateGroups(): void
    {
        if (is_array($this->groupCache) && is_array($this->groups)) {
            return;
        }

        $groupModel = model(GroupModel::class);
        $rows       = $groupModel->findAll();

        foreach ($rows as $row) {
            $this->groups[$row->id] = $row->name;
        }

        $this->groupCache = array_column($this->getAllUserGroups(), 'name');
    }

    /**
     * Used internally to populate the User permissions
     * so we hit the database as little as possible.
     */
    private function populatePermissions(): void
    {
        if (is_array($this->permissionsCache) && is_array($this->permissions)) {
            return;
        }

        /** @var PermissionModel $permissionModel */
        $permissionModel = model(PermissionModel::class);
        $rows            = $permissionModel->findAll();

        foreach ($rows as $row) {
            $this->permissions[$row->id] = $row->name;
        }

        $this->permissionsCache = array_column($this->getAllUserPermissions(), 'name');
    }

    /**
     * Inserts or Updates the current groups.
     */
    private function saveGroups(): void
    {
        /** @var GroupUserModel $model */
        $model = model(GroupUserModel::class);

        $names = $this->groupCache;

        $cache = [];

        foreach ($names as $name) {
            $cache[] = array_search($name, $this->groups, true);
        }

        $existing = array_column($this->getAllUserGroups(), 'id');

        $this->saveGroupsOrPermissions('group_id', $model, $cache, $existing);
    }

    /**
     * Inserts or Updates either the current permissions.
     */
    private function savePermissions(): void
    {
        /** @var PermissionUserModel $model */
        $model = model(PermissionUserModel::class);

        $names = $this->permissionsCache;

        $cache = [];

        foreach ($names as $name) {
            $cache[] = array_search($name, $this->permissions, true);
        }

        $existing = array_column($this->getAllUserPermissions(), 'id');

        $this->saveGroupsOrPermissions('permission_id', $model, $cache, $existing);
    }

    /**
     * @phpstan-param 'group'|'permission'               $type
     * @param         GroupUserModel|PermissionUserModel $model
     */
    private function saveGroupsOrPermissions(string $type, $model, array $cache, array $existing): void
    {
        $new = array_diff($cache, $existing);

        // Delete any not in the cache
        if ($cache !== []) {
            $model->deleteNotIn($this->id, $cache);
        }
        // Nothing in the cache? Then make sure
        // we delete all from this user
        else {
            $model->deleteAll($this->id);
        }

        // Insert new ones
        if ($new !== []) {
            $inserts = [];

            foreach ($new as $item) {
                $inserts[] = [
                    'user_id'    => $this->id,
                    $type        => $item,
                    'created_at' => Time::now()->format('Y-m-d H:i:s'),
                ];
            }

            $model->insertBatch($inserts);
        }
    }
}
