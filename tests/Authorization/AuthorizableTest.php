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
use Daycry\Auth\Exceptions\AuthorizationException;
use Daycry\Auth\Models\GroupModel;
use Daycry\Auth\Models\PermissionModel;
use Daycry\Auth\Models\UserModel;
use Daycry\Exceptions\Exceptions\LogicException;
use Locale;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeUser;

/**
 * @internal
 */
final class AuthorizableTest extends DatabaseTestCase
{
    use FakeUser;

    protected $refresh = true;
    protected $namespace;

    protected function setUp(): void
    {
        parent::setUp();

        // Refresh should take care of this....
        // db_connect()->table($this->tables['groups_users'])->truncate();
        // db_connect()->table($this->tables['permissions_users'])->truncate();
    }

    public function testAddGroupWithExistingGroups(): void
    {
        $beta = fake(GroupModel::class, ['name' => 'beta']);

        $this->user->addGroup('admin', 'beta');

        // Make sure it doesn't record duplicates
        $this->user->addGroup('admin', 'beta');

        $this->seeInDatabase($this->tables['groups_users'], [
            'user_id'  => $this->user->id,
            'group_id' => 1,
        ]);
        $this->seeInDatabase($this->tables['groups_users'], [
            'user_id'  => $this->user->id,
            'group_id' => $beta->id,
        ]);

        $this->assertTrue($this->user->inGroup('admin'));
        $this->assertTrue($this->user->inGroup('beta'));
        $this->assertFalse($this->user->inGroup('user'));
    }

    public function testAddGroupWithUnknownGroup(): void
    {
        $this->expectException(AuthorizationException::class);

        $this->user->addGroup('admin', 'foo');
    }

    public function testRemoveGroupNoGroups(): void
    {
        $this->assertSame($this->user, $this->user->removeGroup('admin'));
    }

    public function testRemoveGroupExistingGroup(): void
    {
        $this->hasInDatabase($this->tables['groups_users'], [
            'user_id'    => $this->user->id,
            'group_id'   => 1,
            'created_at' => Time::now()->toDateTimeString(),
        ]);

        $otherUser = fake(UserModel::class);
        $this->hasInDatabase($this->tables['groups_users'], [
            'user_id'    => $otherUser->id,
            'group_id'   => 1,
            'created_at' => Time::now()->toDateTimeString(),
        ]);

        $this->user->removeGroup('admin');
        $this->assertEmpty($this->user->getGroups());
        $this->dontSeeInDatabase($this->tables['groups_users'], [
            'user_id'  => $this->user->id,
            'group_id' => 'admin',
        ]);

        // Make sure we didn't delete the group from anyone else
        $this->seeInDatabase($this->tables['groups_users'], [
            'user_id'  => $otherUser->id,
            'group_id' => 1,
        ]);
    }

    public function testAddPermissionWithNoExistingPermissions(): void
    {
        $adminAccess = fake(PermissionModel::class, ['name' => 'admin.access']);
        $betaAccess  = fake(PermissionModel::class, ['name' => 'beta.access']);

        $this->user->addPermission('admin.access', 'beta.access');
        // Make sure it doesn't record duplicates
        $this->user->addPermission('admin.access', 'beta.access');

        $this->seeInDatabase($this->tables['permissions_users'], [
            'user_id'       => $this->user->id,
            'permission_id' => $adminAccess->id,
        ]);
        $this->seeInDatabase($this->tables['permissions_users'], [
            'user_id'       => $this->user->id,
            'permission_id' => $betaAccess->id,
        ]);

        $this->assertTrue($this->user->can('admin.access'));
        $this->assertTrue($this->user->can('beta.access'));
        $this->assertFalse($this->user->can('user.manage'));
    }

    public function testAddPermissionWithExistingPermissions(): void
    {
        $adminAccess = fake(PermissionModel::class, ['name' => 'admin.access']);
        $usersManage = fake(PermissionModel::class, ['name' => 'users.manage']);

        $this->hasInDatabase($this->tables['permissions_users'], [
            'user_id'       => $this->user->id,
            'permission_id' => $adminAccess->id,
            'created_at'    => Time::now()->toDateTimeString(),
        ]);
        $this->hasInDatabase($this->tables['permissions_users'], [
            'user_id'       => $this->user->id,
            'permission_id' => $usersManage->id,
            'created_at'    => Time::now()->toDateTimeString(),
        ]);

        $this->user->addPermission('admin.access', 'users.manage');
        // Make sure it doesn't record duplicates
        $this->user->addPermission('admin.access', 'users.manage');

        $this->seeInDatabase($this->tables['permissions_users'], [
            'user_id'       => $this->user->id,
            'permission_id' => $adminAccess->id,
        ]);
        $this->seeInDatabase($this->tables['permissions_users'], [
            'user_id'       => $this->user->id,
            'permission_id' => $usersManage->id,
        ]);

        $this->assertTrue($this->user->can('admin.access'));
        $this->assertTrue($this->user->can('users.manage'));
        $this->assertFalse($this->user->can('beta.access'));
    }

    public function testAddPermissionsWithUnknownPermission(): void
    {
        $this->expectException(AuthorizationException::class);

        $this->user->addPermission('admin.access', 'foo');
    }

    public function testRemovePermissionNoPermissions(): void
    {
        $this->assertCount(count($this->user->getPermissions()), $this->user->removePermission('admin.access')->getPermissions());
    }

    public function testRemovePermissionExistingPermissions(): void
    {
        $adminAccess = fake(PermissionModel::class, ['name' => 'admin.access']);

        $this->hasInDatabase($this->tables['permissions_users'], [
            'user_id'       => $this->user->id,
            'permission_id' => $adminAccess->id,
            'created_at'    => Time::now()->toDateTimeString(),
        ]);

        $otherUser = fake(UserModel::class);
        $this->hasInDatabase($this->tables['permissions_users'], [
            'user_id'       => $otherUser->id,
            'permission_id' => $adminAccess->id,
            'created_at'    => Time::now()->toDateTimeString(),
        ]);

        $this->user->removePermission('admin.access');
        $this->assertEmpty($this->user->getPermissions());
        $this->dontSeeInDatabase($this->tables['permissions_users'], [
            'user_id'       => $this->user->id,
            'permission_id' => $adminAccess->id,
        ]);

        // Make sure it didn't delete the other user's permission
        $this->seeInDatabase($this->tables['permissions_users'], [
            'user_id'       => $otherUser->id,
            'permission_id' => $adminAccess->id,
        ]);
    }

    public function testHasPermission(): void
    {
        fake(PermissionModel::class, ['name' => 'admin.access']);

        $this->user->addPermission('admin.access');

        $this->assertTrue($this->user->hasPermission('admin.access'));
        $this->assertFalse($this->user->hasPermission('beta.access'));
    }

    public function testCanCascadesToGroupsSimple(): void
    {
        $adminAccess = fake(PermissionModel::class, ['name' => 'admin.access']);

        $this->hasInDatabase($this->tables['permissions_groups'], [
            'group_id'      => 1,
            'permission_id' => $adminAccess->id,
            'created_at'    => Time::now()->toDateTimeString(),
        ]);

        $this->user->addGroup('admin');

        $this->assertTrue($this->user->can('admin.access'));
    }

    public function testCanCascadesToGroupsWithWildcards(): void
    {
        $adminAccess = fake(PermissionModel::class, ['name' => 'admin.*']);

        $this->hasInDatabase($this->tables['permissions_groups'], [
            'group_id'      => 1,
            'permission_id' => $adminAccess->id,
            'created_at'    => Time::now()->toDateTimeString(),
        ]);

        $this->user->addGroup('admin');

        $this->assertTrue($this->user->can('admin.access'));
    }

    public function testCanGetsInvalidPermission(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Invalid permission: developer');

        $this->assertTrue($this->user->can('developer'));
    }

    /**
     * @see https://github.com/codeigniter4/shield/pull/791#discussion_r1297712860
     */
    public function testCanWorksWithMultiplePermissions(): void
    {
        fake(PermissionModel::class, ['name' => 'users.create']);
        fake(PermissionModel::class, ['name' => 'users.edit']);

        // Check for user's direct permissions (user-level permissions)
        $this->user->addPermission('users.create', 'users.edit');

        $this->assertTrue($this->user->can('users.create', 'users.edit'));
        $this->assertFalse($this->user->can('beta.access', 'admin.access'));

        $this->user->removePermission('users.create', 'users.edit');

        $this->assertFalse($this->user->can('users.edit', 'users.create'));

        // Check for user's group permissions (group-level permissions)
        $this->user->addGroup('admin');

        $adminAccess = fake(PermissionModel::class, ['name' => 'admin.*']);
        $betaAccess  = fake(PermissionModel::class, ['name' => 'beta.*']);
        $usersAccess = fake(PermissionModel::class, ['name' => 'users.*']);

        $this->hasInDatabase($this->tables['permissions_groups'], [
            'group_id'      => 1,
            'permission_id' => $adminAccess->id,
            'created_at'    => Time::now()->toDateTimeString(),
        ]);

        $this->hasInDatabase($this->tables['permissions_groups'], [
            'group_id'      => 1,
            'permission_id' => $betaAccess->id,
            'created_at'    => Time::now()->toDateTimeString(),
        ]);

        $this->hasInDatabase($this->tables['permissions_groups'], [
            'group_id'      => 1,
            'permission_id' => $usersAccess->id,
            'created_at'    => Time::now()->toDateTimeString(),
        ]);

        $this->assertTrue($this->user->can('admin.access', 'beta.access'));
        $this->assertTrue($this->user->can('admin.*', 'users.*'));
    }

    /**
     * @see https://github.com/codeigniter4/shield/pull/238
     */
    public function testCreatedAtIfDefaultLocaleSetFaWithAddGroup(): void
    {
        $currentLocale = Locale::getDefault();
        Locale::setDefault('fa');

        Time::setTestNow('March 10, 2017', 'America/Chicago');

        $this->user->addGroup('admin');

        $this->seeInDatabase($this->tables['groups_users'], [
            'id'         => 1,
            'user_id'    => $this->user->id,
            'group_id'   => 1,
            'until_at'   => null,
            'created_at' => '2017-03-10 06:00:00',
            'updated_at' => '2017-03-10 06:00:00',
            'deleted_at' => null,
        ]);

        Locale::setDefault($currentLocale);
        Time::setTestNow();
    }

    public function testBanningUser(): void
    {
        $this->assertFalse($this->user->isBanned());

        $this->user->ban();

        $this->assertTrue($this->user->isBanned());
    }

    public function testUnbanningUser(): void
    {
        $this->user->ban();

        $this->assertTrue($this->user->isBanned());

        $this->user->unBan();

        $this->assertFalse($this->user->isBanned());
    }

    public function testGetBanMessage(): void
    {
        $this->assertNull($this->user->getBanMessage());

        $this->user->ban('You are banned');

        $this->assertSame('You are banned', $this->user->getBanMessage());
    }
}
