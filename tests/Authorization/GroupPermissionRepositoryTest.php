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

use Daycry\Auth\Authorization\GroupPermissionRepository;
use Daycry\Auth\Models\GroupModel;
use Daycry\Auth\Models\PermissionModel;
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
        $repository->syncGroupPermissions($groupId, [$keep, $drop]);
        $repository->syncGroupPermissions($groupId, [$keep, $add]);

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
}
