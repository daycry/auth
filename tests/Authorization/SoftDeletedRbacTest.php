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

namespace Tests\Authorization;

use CodeIgniter\I18n\Time;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Exceptions\AuthorizationException;
use Daycry\Auth\Models\GroupModel;
use Daycry\Auth\Models\PermissionGroupModel;
use Daycry\Auth\Models\PermissionModel;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeUser;

/**
 * A (soft-)deleted RBAC row — a group, a permission, or any of the three
 * pivot rows (groups_users, permissions_users, permissions_groups) — must
 * never participate in an authorization decision, exactly like a hard-deleted
 * row would not. The guard is config-agnostic: with hard-delete the row is
 * gone (deleted_at always null), so these checks are a no-op; with soft-delete
 * enabled they exclude the soft-deleted row.
 *
 * @internal
 */
final class SoftDeletedRbacTest extends DatabaseTestCase
{
    use FakeUser;

    protected $refresh = true;

    /**
     * A fresh User entity with empty in-memory group/permission caches, so each
     * assertion reads RBAC state straight from the database.
     */
    private function freshUser(): User
    {
        /** @var User $user */
        $user = model(UserModel::class)->find($this->user->id);

        return $user;
    }

    /**
     * Marks matching rows of an Auth table as soft-deleted.
     *
     * @param array<string, int|string> $where
     */
    private function softDelete(string $tableKey, array $where): void
    {
        $this->db->table($this->tables[$tableKey])
            ->where($where)
            ->update(['deleted_at' => Time::now()->format('Y-m-d H:i:s')]);
    }

    public function testSoftDeletedGroupDropsMembership(): void
    {
        $this->user->addGroup('admin');

        // Sanity: the membership is active before the group is soft-deleted.
        $this->assertTrue($this->freshUser()->inGroup('admin'));

        $this->softDelete('groups', ['id' => 1]);

        $fresh = $this->freshUser();
        $this->assertFalse($fresh->inGroup('admin'));
        $this->assertNotContains('admin', $fresh->getGroups());
    }

    public function testSoftDeletedGroupUserPivotDropsMembership(): void
    {
        $this->user->addGroup('admin');

        $this->assertTrue($this->freshUser()->inGroup('admin'));

        $this->softDelete('groups_users', ['user_id' => $this->user->id, 'group_id' => 1]);

        $this->assertFalse($this->freshUser()->inGroup('admin'));
    }

    public function testSoftDeletedPermissionRevokesDirectPermission(): void
    {
        $permission = fake(PermissionModel::class, ['name' => 'admin.access']);
        $this->user->addPermission('admin.access');

        $this->assertTrue($this->freshUser()->can('admin.access'));

        $this->softDelete('permissions', ['id' => $permission->id]);

        $fresh = $this->freshUser();
        $this->assertFalse($fresh->can('admin.access'));
        $this->assertNotContains('admin.access', $fresh->getPermissions());
    }

    public function testSoftDeletedPermissionUserPivotRevokesDirectPermission(): void
    {
        fake(PermissionModel::class, ['name' => 'admin.access']);
        $this->user->addPermission('admin.access');

        $this->assertTrue($this->freshUser()->can('admin.access'));

        $this->softDelete('permissions_users', ['user_id' => $this->user->id]);

        $this->assertFalse($this->freshUser()->can('admin.access'));
    }

    public function testSoftDeletedPermissionGroupPivotRevokesGroupPermission(): void
    {
        $permission = fake(PermissionModel::class, ['name' => 'admin.access']);

        $this->hasInDatabase($this->tables['permissions_groups'], [
            'group_id'      => 1,
            'permission_id' => $permission->id,
            'created_at'    => Time::now()->toDateTimeString(),
        ]);

        $this->user->addGroup('admin');

        $this->assertTrue($this->freshUser()->can('admin.access'));

        $this->softDelete('permissions_groups', ['group_id' => 1, 'permission_id' => $permission->id]);

        $this->assertFalse($this->freshUser()->can('admin.access'));
    }

    public function testSoftDeletedPermissionRevokesGroupCascade(): void
    {
        $permission = fake(PermissionModel::class, ['name' => 'admin.access']);

        $this->hasInDatabase($this->tables['permissions_groups'], [
            'group_id'      => 1,
            'permission_id' => $permission->id,
            'created_at'    => Time::now()->toDateTimeString(),
        ]);

        $this->user->addGroup('admin');

        $this->assertTrue($this->freshUser()->can('admin.access'));

        // Soft-delete the permission row itself (the pivot stays): the group
        // cascade must drop it because the permission name can no longer resolve.
        $this->softDelete('permissions', ['id' => $permission->id]);

        $this->assertFalse($this->freshUser()->can('admin.access'));
    }

    public function testGetForGroupExcludesSoftDeletedPivot(): void
    {
        $permission = fake(PermissionModel::class, ['name' => 'admin.access']);

        $this->hasInDatabase($this->tables['permissions_groups'], [
            'group_id'      => 1,
            'permission_id' => $permission->id,
            'created_at'    => Time::now()->toDateTimeString(),
        ]);

        /** @var \Daycry\Auth\Entities\Group $group */
        $group = model(GroupModel::class)->find(1);

        $this->assertCount(1, model(PermissionGroupModel::class)->getForGroup($group));

        $this->softDelete('permissions_groups', ['group_id' => 1, 'permission_id' => $permission->id]);

        $this->assertCount(0, model(PermissionGroupModel::class)->getForGroup($group));
    }

    public function testCannotAddSoftDeletedGroup(): void
    {
        $beta = fake(GroupModel::class, ['name' => 'beta']);
        $this->softDelete('groups', ['id' => $beta->id]);

        $this->expectException(AuthorizationException::class);
        $this->freshUser()->addGroup('beta');
    }

    public function testCannotAddSoftDeletedPermission(): void
    {
        $permission = fake(PermissionModel::class, ['name' => 'reports.view']);
        $this->softDelete('permissions', ['id' => $permission->id]);

        $this->expectException(AuthorizationException::class);
        $this->freshUser()->addPermission('reports.view');
    }
}
