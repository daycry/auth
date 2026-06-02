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
use Daycry\Auth\Services\AuditLogger;

trait Authorizable
{
    protected ?array $groupCache       = null;
    protected ?array $permissionsCache = null;
    protected ?array $groups           = null;
    protected ?array $permissions      = null;

    /**
     * @var array<string, list<string>>|null Group name => list of permission names
     */
    protected ?array $groupPermissionsCache = null;

    private function groupModel(): GroupModel
    {
        /** @var GroupModel */
        return model(GroupModel::class);
    }

    private function groupUserModel(): GroupUserModel
    {
        /** @var GroupUserModel */
        return model(GroupUserModel::class);
    }

    private function permissionModel(): PermissionModel
    {
        /** @var PermissionModel */
        return model(PermissionModel::class);
    }

    private function permissionUserModel(): PermissionUserModel
    {
        /** @var PermissionUserModel */
        return model(PermissionUserModel::class);
    }

    private function permissionGroupModel(): PermissionGroupModel
    {
        /** @var PermissionGroupModel */
        return model(PermissionGroupModel::class);
    }

    /**
     * Adds one or more groups to the current User.
     *
     * @return $this
     */
    public function addGroup(string ...$groups): self
    {
        $this->populateGroups();

        $groupCount = count($this->groupCache);
        $added      = [];

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
            $added[]            = $group;
        }

        // Only save the results if there's anything new.
        if (count($this->groupCache) > $groupCount) {
            $this->saveGroups();

            (new AuditLogger())->record(
                AuditLogger::EVENT_GROUP_ASSIGNED,
                (int) $this->id,
                ['groups' => $added],
            );
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
        unset($group);

        $removed = array_values(array_intersect($this->groupCache, $groups));

        // Remove from local cache
        $this->groupCache = array_diff($this->groupCache, $groups);

        // Update the database.
        $this->saveGroups();

        if ($removed !== []) {
            (new AuditLogger())->record(
                AuditLogger::EVENT_GROUP_REVOKED,
                (int) $this->id,
                ['groups' => $removed],
            );
        }

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
        $added           = [];

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
            $added[]                  = $permission;
        }

        // Only save the results if there's anything new.
        if (count($this->permissionsCache) > $permissionCount) {
            $this->savePermissions();

            (new AuditLogger())->record(
                AuditLogger::EVENT_PERMISSION_GRANTED,
                (int) $this->id,
                ['permissions' => $added],
            );
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
        unset($permission);

        $removed = array_values(array_intersect($this->permissionsCache, $permissions));

        // Remove from local cache
        $this->permissionsCache = array_diff($this->permissionsCache, $permissions);

        // Update the database.
        $this->savePermissions();

        if ($removed !== []) {
            (new AuditLogger())->record(
                AuditLogger::EVENT_PERMISSION_REVOKED,
                (int) $this->id,
                ['permissions' => $removed],
            );
        }

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

            // Check the user's directly-assigned permissions.
            if ($this->permissionMatches($permission, $this->permissionsCache)) {
                return true;
            }

            // Check the permissions inherited from each of the user's groups.
            foreach ($this->groupCache as $groupName) {
                $groupPermNames = $this->groupPermissionsCache[$groupName] ?? [];

                if ($this->permissionMatches($permission, $groupPermNames)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Determines whether a single permission is granted by a set of granted
     * permission names. Matching is uniform across user-level and group-level
     * permissions and honours three forms:
     *
     *  - the global wildcard `*`;
     *  - an exact match (`users.edit`);
     *  - a scope wildcard (`users.*` covers `users.edit`).
     *
     * @param string        $permission   already-lowercased `scope.action` permission
     * @param array<string> $grantedNames the granted permission names to check against
     */
    private function permissionMatches(string $permission, array $grantedNames): bool
    {
        if (in_array('*', $grantedNames, true)) {
            return true;
        }

        if (in_array($permission, $grantedNames, true)) {
            return true;
        }

        // Scope wildcard, e.g. `users.*` covers `users.edit`.
        if (str_contains($permission, '.')) {
            $scopeWildcard = substr($permission, 0, strpos($permission, '.')) . '.*';

            if (in_array($scopeWildcard, $grantedNames, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks an ability registered with the Gate (closure or policy).
     *
     * Sister method to {@see can()} — `can()` handles RBAC permission
     * strings ("users.create"), `canDo()` handles abilities backed by
     * a closure or a Policy class and may receive resource arguments:
     *
     *     $user->canDo('post.update', $post)
     *
     * @param mixed ...$arguments Forwarded to the resolved rule.
     */
    public function canDo(string $ability, ...$arguments): bool
    {
        return service('gate')->forUser($this)->allows($ability, ...$arguments);
    }

    /**
     * Negation of {@see canDo()}.
     *
     * @param mixed ...$arguments
     */
    public function cantDo(string $ability, ...$arguments): bool
    {
        return ! $this->canDo($ability, ...$arguments);
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
        $userGroupModel = $this->groupUserModel();
        $userGroups     = $userGroupModel->getForUser($this);

        $ids = [];

        foreach ($userGroups as $userGroup) {
            $ids[] = $userGroup->group_id;
        }

        $groupModel = $this->groupModel();

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
        $userPermissionsModel = $this->permissionUserModel();
        $userPermissions      = $userPermissionsModel->getForUser($this);

        $ids = [];

        foreach ($userPermissions as $userPermission) {
            $ids[] = $userPermission->permission_id;
        }

        $permissionModel = $this->permissionModel();

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
        $groupPermissionsModel = $this->permissionGroupModel();
        $groupPermissions      = $groupPermissionsModel->getForGroup($group);

        $ids = [];

        foreach ($groupPermissions as $groupPermission) {
            $ids[] = $groupPermission->permission_id;
        }

        $permissionModel = $this->permissionModel();

        if ($ids !== []) {
            return $permissionModel->getByIds($ids);
        }

        return [];
    }

    /**
     * Loads all permissions for all of the user's groups in two queries
     * (one for permission_group rows, one for permission names) and
     * populates $groupPermissionsCache. This eliminates the N+1 problem
     * where can() previously called getGroupPermissions() per group.
     */
    private function eagerLoadGroupPermissions(): void
    {
        // Resolve group IDs from group names
        $groupIds = [];

        foreach ($this->groupCache as $groupName) {
            $id = array_search($groupName, $this->groups, true);

            if ($id !== false) {
                $groupIds[] = $id;
            }
        }

        if ($groupIds === []) {
            return;
        }

        // Single query: get all permission_group rows for all user groups (respecting until_at)
        $groupPermModel = $this->permissionGroupModel();
        $now            = Time::now()->format('Y-m-d H:i:s');

        $allGroupPerms = $groupPermModel
            ->whereIn('group_id', $groupIds)
            ->groupStart()
            ->where('until_at')
            ->orWhere('until_at >', $now)
            ->groupEnd()
            ->findAll();

        // Build a map of group_id => [permission_id, ...] and collect all permission IDs
        $permIds      = [];
        $groupPermMap = [];

        foreach ($allGroupPerms as $gp) {
            $pid                  = (int) $gp->permission_id;
            $gid                  = (int) $gp->group_id;
            $permIds[]            = $pid;
            $groupPermMap[$gid][] = $pid;
        }

        // Single query: get all permission names by IDs
        $permNames = [];

        if ($permIds !== []) {
            $permModel = $this->permissionModel();
            $perms     = $permModel->getByIds(array_unique($permIds));

            foreach ($perms as $p) {
                $permNames[(int) $p->id] = $p->name;
            }
        }

        // Build the cache: groupName => [permissionName, ...]
        foreach ($this->groupCache as $groupName) {
            $gId                                     = array_search($groupName, $this->groups, true);
            $this->groupPermissionsCache[$groupName] = [];

            if ($gId !== false && isset($groupPermMap[(int) $gId])) {
                foreach ($groupPermMap[(int) $gId] as $pid) {
                    if (isset($permNames[$pid])) {
                        $this->groupPermissionsCache[$groupName][] = $permNames[$pid];
                    }
                }
            }
        }
    }

    /**
     * Used internally to populate the User groups
     * so we hit the database as little as possible.
     * Reads from persistent cache when permissionCacheEnabled is true.
     */
    private function populateGroups(): void
    {
        if (is_array($this->groupCache) && is_array($this->groups) && is_array($this->groupPermissionsCache)) {
            return;
        }

        // Try persistent cache first
        if (service('settings')->get('AuthSecurity.permissionCacheEnabled')) {
            /** @var array{groups: array<int, string>, groupCache: list<string>, groupPermissionsCache: array<string, list<string>>}|null $cached */
            $cached = cache($this->getPermissionCacheKey('groups'));

            if ($cached !== null) {
                $this->groups                = $cached['groups'];
                $this->groupCache            = $cached['groupCache'];
                $this->groupPermissionsCache = $cached['groupPermissionsCache'];

                return;
            }
        }

        $groupModel = $this->groupModel();
        $rows       = $groupModel->findAll();

        foreach ($rows as $row) {
            $this->groups[$row->id] = $row->name;
        }

        $this->groupCache = array_column($this->getAllUserGroups(), 'name');

        // Eager-load all group permissions in a single pass (eliminates N+1 queries in can())
        $this->groupPermissionsCache = [];

        if ($this->groupCache !== []) {
            $this->eagerLoadGroupPermissions();
        }

        // Store in persistent cache
        if (service('settings')->get('AuthSecurity.permissionCacheEnabled')) {
            $ttl = (int) (service('settings')->get('AuthSecurity.permissionCacheTTL') ?? 300);
            cache()->save($this->getPermissionCacheKey('groups'), [
                'groups'                => $this->groups,
                'groupCache'            => $this->groupCache,
                'groupPermissionsCache' => $this->groupPermissionsCache,
            ], $ttl);
        }
    }

    /**
     * Used internally to populate the User permissions
     * so we hit the database as little as possible.
     * Reads from persistent cache when permissionCacheEnabled is true.
     */
    private function populatePermissions(): void
    {
        if (is_array($this->permissionsCache) && is_array($this->permissions)) {
            return;
        }

        // Try persistent cache first
        if (service('settings')->get('AuthSecurity.permissionCacheEnabled')) {
            /** @var array{permissions: array<int, string>, permissionsCache: list<string>}|null $cached */
            $cached = cache($this->getPermissionCacheKey('permissions'));

            if ($cached !== null) {
                $this->permissions      = $cached['permissions'];
                $this->permissionsCache = $cached['permissionsCache'];

                return;
            }
        }

        $permissionModel = $this->permissionModel();
        $rows            = $permissionModel->findAll();

        foreach ($rows as $row) {
            $this->permissions[$row->id] = $row->name;
        }

        $this->permissionsCache = array_column($this->getAllUserPermissions(), 'name');

        // Store in persistent cache
        if (service('settings')->get('AuthSecurity.permissionCacheEnabled')) {
            $ttl = (int) (service('settings')->get('AuthSecurity.permissionCacheTTL') ?? 300);
            cache()->save($this->getPermissionCacheKey('permissions'), [
                'permissions'      => $this->permissions,
                'permissionsCache' => $this->permissionsCache,
            ], $ttl);
        }
    }

    /**
     * Returns the cache key for this user's permission/group cache entry.
     *
     * @param string $type 'groups' or 'permissions'
     */
    private function getPermissionCacheKey(string $type): string
    {
        return 'auth_' . $type . '_' . $this->id;
    }

    /**
     * Deletes the persistent cache entries for this user's groups and permissions.
     * Call this when group/permission assignments change.
     */
    public function clearPermissionCache(): void
    {
        cache()->delete($this->getPermissionCacheKey('groups'));
        cache()->delete($this->getPermissionCacheKey('permissions'));

        $this->groupPermissionsCache = null;
    }

    /**
     * Inserts or Updates the current groups.
     */
    private function saveGroups(): void
    {
        $model = $this->groupUserModel();

        $names = $this->groupCache;

        $cache = [];

        foreach ($names as $name) {
            $cache[] = array_search($name, $this->groups, true);
        }

        $existing = array_column($this->getAllUserGroups(), 'id');

        $this->saveGroupsOrPermissions('group_id', $model, $cache, $existing);

        // Invalidate persistent cache after DB write
        if (service('settings')->get('AuthSecurity.permissionCacheEnabled')) {
            cache()->delete($this->getPermissionCacheKey('groups'));
        }

        // Force re-population of group permissions on next access
        $this->groupPermissionsCache = null;
    }

    /**
     * Inserts or Updates either the current permissions.
     */
    private function savePermissions(): void
    {
        $model = $this->permissionUserModel();

        $names = $this->permissionsCache;

        $cache = [];

        foreach ($names as $name) {
            $cache[] = array_search($name, $this->permissions, true);
        }

        $existing = array_column($this->getAllUserPermissions(), 'id');

        $this->saveGroupsOrPermissions('permission_id', $model, $cache, $existing);

        // Invalidate persistent cache after DB write
        if (service('settings')->get('AuthSecurity.permissionCacheEnabled')) {
            cache()->delete($this->getPermissionCacheKey('permissions'));
        }
    }

    /**
     * @param         GroupUserModel|PermissionUserModel $model
     * @phpstan-param 'group_id'|'permission_id'         $type
     */
    private function saveGroupsOrPermissions(string $type, $model, array $cache, array $existing): void
    {
        // Persistence (and its transaction) lives in the repository, so the
        // entity itself no longer opens DB transactions.
        service('groupPermissionRepository')->saveUserPivot($this->id, $type, $model, $cache, $existing);
    }
}
