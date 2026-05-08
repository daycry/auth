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

namespace Tests\Models;

use CodeIgniter\I18n\Time;
use Daycry\Auth\Entities\Group;
use Daycry\Auth\Models\GroupModel;
use Daycry\Auth\Models\PermissionGroupModel;
use Daycry\Auth\Models\PermissionModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class PermissionGroupModelTest extends DatabaseTestCase
{
    private PermissionGroupModel $pivot;
    private GroupModel $groups;
    private PermissionModel $permissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pivot       = model(PermissionGroupModel::class);
        $this->groups      = model(GroupModel::class);
        $this->permissions = model(PermissionModel::class);
    }

    private function createGroup(string $name): Group
    {
        // Suffix every name to avoid colliding with the seeder's default groups.
        $unique = $name . '_' . bin2hex(random_bytes(3));
        $id     = $this->groups->insert(['name' => $unique, 'description' => $unique . ' group'], true);

        /** @var Group $group */
        $group = $this->groups->find($id);

        return $group;
    }

    private function createPermissionAttachedTo(Group $group, string $name, ?string $until = null): int
    {
        // Suffix permission names too — `name` is unique.
        $unique   = $name . '_' . bin2hex(random_bytes(3));
        $existing = $this->permissions->where('name', $unique)->first();
        $permId   = $existing !== null ? (int) $existing->id : (int) $this->permissions->insert(['name' => $unique], true);

        $this->pivot->insert([
            'group_id'      => $group->id,
            'permission_id' => $permId,
            'until_at'      => $until,
        ]);

        return (int) $permId;
    }

    public function testGetForGroupReturnsRowsWithoutExpiry(): void
    {
        $group = $this->createGroup('admin');
        $this->createPermissionAttachedTo($group, 'users.read');
        $this->createPermissionAttachedTo($group, 'users.write');

        $rows = $this->pivot->getForGroup($group);

        $this->assertNotNull($rows);
        $this->assertCount(2, $rows);
    }

    public function testGetForGroupExcludesExpiredEntries(): void
    {
        $group = $this->createGroup('temp');

        // Past expiry — should be filtered out
        $this->createPermissionAttachedTo($group, 'users.read', Time::now()->subHours(1)->format('Y-m-d H:i:s'));

        // Future expiry — should remain
        $this->createPermissionAttachedTo($group, 'users.write', Time::now()->addDays(1)->format('Y-m-d H:i:s'));

        // No expiry — should always show up
        $this->createPermissionAttachedTo($group, 'users.delete');

        $rows = $this->pivot->getForGroup($group);
        $this->assertNotNull($rows);
        $this->assertCount(2, $rows);
    }

    public function testDeleteAllRemovesEverythingForGroup(): void
    {
        $groupA = $this->createGroup('A');
        $groupB = $this->createGroup('B');

        $this->createPermissionAttachedTo($groupA, 'users.read');
        $this->createPermissionAttachedTo($groupA, 'users.write');
        $this->createPermissionAttachedTo($groupB, 'users.read');

        $this->pivot->deleteAll($groupA->id);

        $this->assertSame(0, $this->pivot->where('group_id', $groupA->id)->countAllResults());
        $this->assertSame(1, $this->pivot->where('group_id', $groupB->id)->countAllResults());
    }

    public function testDeleteNotInRemovesEntriesNotInList(): void
    {
        $group = $this->createGroup('keep-some');

        $idA = $this->createPermissionAttachedTo($group, 'a');
        $idB = $this->createPermissionAttachedTo($group, 'b');
        $idC = $this->createPermissionAttachedTo($group, 'c');

        // Keep only A and C — B must be deleted.
        $this->pivot->deleteNotIn($group->id, [$idA, $idC]);

        $remaining = $this->pivot->where('group_id', $group->id)->findAll();
        $this->assertCount(2, $remaining);

        $remainingIds = array_map(static fn ($r): int => (int) $r->permission_id, $remaining);
        $this->assertContains($idA, $remainingIds);
        $this->assertContains($idC, $remainingIds);
        $this->assertNotContains($idB, $remainingIds);
    }
}
