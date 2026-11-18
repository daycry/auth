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

use Config\Services;
use Daycry\Auth\Authorization\GroupPermissionRepository;
use Daycry\Auth\Interfaces\GroupPermissionRepositoryInterface;
use Daycry\Auth\Models\GroupModel;
use Daycry\Auth\Models\GroupUserModel;
use Daycry\Auth\Models\PermissionModel;
use Daycry\Auth\Models\PermissionUserModel;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class GroupPermissionRepositoryTest extends DatabaseTestCase
{
    public function testSyncGroupPermissionsReplacesTheWholeSet(): void
    {
        $groupId = (int) model(GroupModel::class)->insert(['name' => 'sync_group'], true);
        $keep    = (int) model(PermissionModel::class)->insert(['name' => 'sync.keep'], true);
        $drop    = (int) model(PermissionModel::class)->insert(['name' => 'sync.drop'], true);
        $add     = (int) model(PermissionModel::class)->insert(['name' => 'sync.add'], true);

        $repository = service('groupPermissionRepository');
        $this->assertTrue($repository->syncGroupPermissions($groupId, [$keep, $drop]));
        $this->assertTrue($repository->syncGroupPermissions($groupId, [$keep, $add]));

        $table = $this->tables['permissions_groups'];
        $this->seeInDatabase($table, ['group_id' => $groupId, 'permission_id' => $keep]);
        $this->seeInDatabase($table, ['group_id' => $groupId, 'permission_id' => $add]);
        $this->dontSeeInDatabase($table, ['group_id' => $groupId, 'permission_id' => $drop]);
    }

    public function testSyncGroupPermissionsWithEmptySetClearsTheGroup(): void
    {
        $groupId = (int) model(GroupModel::class)->insert(['name' => 'empty_group'], true);
        $perm    = (int) model(PermissionModel::class)->insert(['name' => 'empty.perm'], true);

        $repository = new GroupPermissionRepository();
        $repository->syncGroupPermissions($groupId, [$perm]);
        $repository->syncGroupPermissions($groupId, []);

        $this->dontSeeInDatabase($this->tables['permissions_groups'], ['group_id' => $groupId]);
    }

    public function testUserPersistsGroupsAndPermissionsThroughADomainOnlyContract(): void
    {
        $repository = new class () implements GroupPermissionRepositoryInterface {
            public array $writes = [];

            public function saveUserGroups(int|string $userId, array $cache, array $existing): void
            {
                $this->writes[] = ['groups', $userId, $cache, $existing];
            }

            public function saveUserPermissions(int|string $userId, array $cache, array $existing): void
            {
                $this->writes[] = ['permissions', $userId, $cache, $existing];
            }

            public function syncGroupPermissions($groupId, array $permissionIds): bool
            {
                return true;
            }
        };
        Services::injectMock('groupPermissionRepository', $repository);
        $groupId      = (int) model(GroupModel::class)->insert(['name' => 'contract_team'], true);
        $permissionId = (int) model(PermissionModel::class)->insert(['name' => 'contract.read'], true);
        $user         = fake(UserModel::class);

        $user->addGroup('contract_team');
        $user->addPermission('contract.read');

        $this->assertSame([
            ['groups', $user->id, [$groupId], []],
            ['permissions', $user->id, [$permissionId], []],
        ], $repository->writes);
    }

    public function testDomainMethodsPreserveExistingPivotOverrides(): void
    {
        $repository = new class () extends GroupPermissionRepository {
            public array $writes = [];

            public function saveUserPivot($userId, string $type, $model, array $cache, array $existing): void
            {
                $this->writes[] = [$type, $model, $cache, $existing];
            }
        };

        $repository->saveUserGroups(42, [7], []);
        $repository->saveUserPermissions(42, [8], []);

        $this->assertSame(['group_id', 'permission_id'], array_column($repository->writes, 0));
        $this->assertInstanceOf(GroupUserModel::class, $repository->writes[0][1]);
        $this->assertInstanceOf(PermissionUserModel::class, $repository->writes[1][1]);
        $this->assertSame([7], $repository->writes[0][2]);
        $this->assertSame([8], $repository->writes[1][2]);
    }
}
