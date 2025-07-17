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

use Daycry\Auth\Authorization\Groups;
use Daycry\Auth\Entities\Group;
use Daycry\Auth\Models\GroupModel;
use Exception;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class GroupsTest extends DatabaseTestCase
{
    private Groups $groups;

    protected function setUp(): void
    {
        parent::setUp();

        $this->groups = new Groups();
    }

    public function testInfoWithExistingGroup(): void
    {
        // Use the admin group that's already seeded
        $group = $this->groups->info('admin');

        $this->assertInstanceOf(Group::class, $group);
        $this->assertSame('admin', $group->name);
        $this->assertSame('Admin group', $group->description);
    }

    public function testInfoWithNonExistentGroup(): void
    {
        $group = $this->groups->info('nonexistent');

        $this->assertNull($group);
    }

    public function testSaveNewGroup(): void
    {
        $group = new Group([
            'name'        => 'testgroup',
            'description' => 'Test group description',
        ]);

        $result = $this->groups->save($group);

        $this->assertTrue($result);

        // Verify the group was saved
        $savedGroup = $this->groups->info('testgroup');
        $this->assertInstanceOf(Group::class, $savedGroup);
        $this->assertSame('testgroup', $savedGroup->name);
        $this->assertSame('Test group description', $savedGroup->description);
    }

    public function testSaveExistingGroup(): void
    {
        // Get the admin group that's already seeded
        $group = $this->groups->info('admin');
        $this->assertInstanceOf(Group::class, $group);

        // Update the description
        $group->description = 'Updated Admin Description';

        $result = $this->groups->save($group);

        $this->assertTrue($result);

        // Verify the update
        $updatedGroup = $this->groups->info('admin');
        $this->assertSame('Updated Admin Description', $updatedGroup->description);
    }

    public function testSaveGroupWithInvalidData(): void
    {
        $group = new Group([
            'name'        => null, // Null name should fail validation
            'description' => 'Test description',
        ]);

        $result = $this->groups->save($group);

        $this->assertFalse($result);
    }

    public function testSaveGroupWithDuplicateName(): void
    {
        // Try to create another group with same name as existing admin group
        $group = new Group([
            'name'        => 'admin',
            'description' => 'Duplicate admin group',
        ]);

        try {
            $result = $this->groups->save($group);
            $this->assertFalse($result);
        } catch (Exception $e) {
            // Database level constraint failure is expected
            $this->assertStringContainsString('UNIQUE constraint failed', $e->getMessage());
        }
    }

    public function testInfoAndSaveWorkflow(): void
    {
        // Create a group
        $group = new Group([
            'name'        => 'workflow',
            'description' => 'Test workflow',
        ]);

        // Save the group
        $saveResult = $this->groups->save($group);
        $this->assertTrue($saveResult);

        // Retrieve the group using info method
        $retrievedGroup = $this->groups->info('workflow');
        $this->assertInstanceOf(Group::class, $retrievedGroup);
        $this->assertSame('workflow', $retrievedGroup->name);
        $this->assertSame('Test workflow', $retrievedGroup->description);
    }

    public function testSaveNewGroupWithMinimalData(): void
    {
        $group = new Group([
            'name' => 'minimal',
        ]);

        $result = $this->groups->save($group);

        $this->assertTrue($result);

        // Verify the group was saved
        $savedGroup = $this->groups->info('minimal');
        $this->assertInstanceOf(Group::class, $savedGroup);
        $this->assertSame('minimal', $savedGroup->name);
        $this->assertNull($savedGroup->description);
    }

    public function testSaveGroupWithLongName(): void
    {
        $group = new Group([
            'name'        => str_repeat('a', 30), // Maximum allowed length
            'description' => 'Test description',
        ]);

        $result = $this->groups->save($group);

        $this->assertTrue($result);

        // Verify the group was saved
        $savedGroup = $this->groups->info(str_repeat('a', 30));
        $this->assertInstanceOf(Group::class, $savedGroup);
        $this->assertSame(str_repeat('a', 30), $savedGroup->name);
    }

    public function testMultipleGroupCreation(): void
    {
        $groupNames = ['group1', 'group2', 'group3'];

        foreach ($groupNames as $groupName) {
            $group = new Group([
                'name'        => $groupName,
                'description' => "Description for {$groupName}",
            ]);

            $result = $this->groups->save($group);
            $this->assertTrue($result);
        }

        // Verify all groups were created
        foreach ($groupNames as $groupName) {
            $savedGroup = $this->groups->info($groupName);
            $this->assertInstanceOf(Group::class, $savedGroup);
            $this->assertSame($groupName, $savedGroup->name);
        }
    }

    public function testUpdateGroupDescription(): void
    {
        // Create a new group
        $group = new Group([
            'name'        => 'updatetest',
            'description' => 'Original description',
        ]);

        $saveResult = $this->groups->save($group);
        $this->assertTrue($saveResult);

        // Retrieve the group to get the ID
        $savedGroup = $this->groups->info('updatetest');
        $this->assertInstanceOf(Group::class, $savedGroup);

        // Update the group
        $savedGroup->description = 'Updated description';

        $updateResult = $this->groups->save($savedGroup);
        $this->assertTrue($updateResult);

        // Verify the update
        $updatedGroup = $this->groups->info('updatetest');
        $this->assertSame('Updated description', $updatedGroup->description);
    }

    public function testSeededGroupsExist(): void
    {
        // Test that the seeded groups exist
        $adminGroup = $this->groups->info('admin');
        $userGroup  = $this->groups->info('user');

        $this->assertInstanceOf(Group::class, $adminGroup);
        $this->assertInstanceOf(Group::class, $userGroup);

        $this->assertSame('admin', $adminGroup->name);
        $this->assertSame('user', $userGroup->name);

        $this->assertSame('Admin group', $adminGroup->description);
        $this->assertSame('default group', $userGroup->description);
    }

    public function testGroupModelDirectAccess(): void
    {
        // Test that we can access the model directly
        $groupModel = model(GroupModel::class);
        $allGroups  = $groupModel->findAll();

        // Should have at least the seeded admin and user groups
        $this->assertGreaterThanOrEqual(2, count($allGroups));

        $groupNames = array_column($allGroups, 'name');
        $this->assertContains('admin', $groupNames);
        $this->assertContains('user', $groupNames);
    }
}
