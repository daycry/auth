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

namespace Tests\Entities;

use CodeIgniter\I18n\Time;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Entities\Login;
use Daycry\Auth\Entities\UserIdentity;
use Daycry\Auth\Exceptions\AuthorizationException;
use Daycry\Auth\Models\GroupModel;
use Daycry\Auth\Models\GroupUserModel;
use Daycry\Auth\Models\LoginModel;
use Daycry\Auth\Models\PermissionGroupModel;
use Daycry\Auth\Models\PermissionModel;
use Daycry\Auth\Models\PermissionUserModel;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Daycry\Exceptions\Exceptions\LogicException;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeUser;

/**
 * @internal
 */
final class UserTest extends DatabaseTestCase
{
    use FakeUser;

    protected $namespace;
    protected $refresh = true;

    public function testGetIdentitiesNone(): void
    {
        // when none, returns empty array
        $this->assertEmpty($this->user->identities);
    }

    public function testGetIdentitiesSome(): void
    {
        fake(UserIdentityModel::class, ['user_id' => $this->user->id, 'type' => 'password']);
        fake(UserIdentityModel::class, ['user_id' => $this->user->id, 'type' => 'access_token']);

        $identities = $this->user->identities;

        $this->assertCount(2, $identities);
    }

    public function testGetIdentitiesByType(): void
    {
        fake(UserIdentityModel::class, ['user_id' => $this->user->id, 'type' => 'password']);
        fake(UserIdentityModel::class, ['user_id' => $this->user->id, 'type' => 'access_token']);

        $identities = $this->user->getIdentities('access_token');

        $this->assertCount(1, $identities);
        $this->assertInstanceOf(UserIdentity::class, $identities[0]);
        $this->assertSame('access_token', $identities[0]->type);
        $this->assertEmpty($this->user->getIdentities('foo'));
    }

    public function testModelFindAllWithIdentities(): void
    {
        fake(UserModel::class);
        fake(UserIdentityModel::class, ['user_id' => $this->user->id, 'type' => 'password']);
        fake(UserIdentityModel::class, ['user_id' => $this->user->id, 'type' => 'access_token']);

        // Grab the user again, using the model's identity helper
        model(UserModel::class)->withIdentities()->findAll();

        $identities = $this->user->identities;

        $this->assertCount(2, $identities);
    }

    public function testModelFindAllWithIdentitiesUserNotExists(): void
    {
        $users = model(UserModel::class)->where('active', 0)->withIdentities()->findAll();

        $this->assertSame([], $users);
    }

    public function testModelFindByIdWithIdentities(): void
    {
        fake(UserModel::class);
        fake(UserIdentityModel::class, ['user_id' => $this->user->id, 'type' => 'password']);
        fake(UserIdentityModel::class, ['user_id' => $this->user->id, 'type' => 'access_token']);

        // Grab the user again, using the model's identity helper
        $user = model(UserModel::class)->withIdentities()->findById(1);

        $this->assertCount(2, $user->identities);
    }

    public function testModelFindByIdWithIdentitiesUserNotExists(): void
    {
        $user = model(UserModel::class)->where('active', 0)->withIdentities()->findById(1);

        $this->assertNull($user);
    }

    public function testLastLogin(): void
    {
        fake(
            UserIdentityModel::class,
            ['user_id' => $this->user->id, 'type' => Session::ID_TYPE_EMAIL_PASSWORD, 'secret' => 'foo@example.com']
        );

        // No logins found.
        $this->assertNull($this->user->lastLogin());

        fake(
            LoginModel::class,
            ['id_type' => 'email', 'identifier' => $this->user->email, 'user_id' => $this->user->id]
        );
        $login2 = fake(
            LoginModel::class,
            ['id_type' => 'email', 'identifier' => $this->user->email, 'user_id' => $this->user->id]
        );
        fake(
            LoginModel::class,
            [
                'id_type'    => 'email',
                'identifier' => $this->user->email,
                'user_id'    => $this->user->id,
                'success'    => false,
            ]
        );

        $last = $this->user->lastLogin();

        $this->assertInstanceOf(Login::class, $last); // @phpstan-ignore-line
        $this->assertSame($login2->id, $last->id);
        $this->assertInstanceOf(Time::class, $last->date);
    }

    public function testPreviousLogin(): void
    {
        fake(
            UserIdentityModel::class,
            ['user_id' => $this->user->id, 'type' => Session::ID_TYPE_EMAIL_PASSWORD, 'secret' => 'foo@example.com']
        );

        // No logins found.
        $this->assertNull($this->user->previousLogin());

        $login1 = fake(
            LoginModel::class,
            ['id_type' => 'email', 'identifier' => $this->user->email, 'user_id' => $this->user->id]
        );

        // The very most login is skipped.
        $this->assertNull($this->user->previousLogin());

        fake(
            LoginModel::class,
            ['id_type' => 'email', 'identifier' => $this->user->email, 'user_id' => $this->user->id]
        );
        fake(
            LoginModel::class,
            [
                'id_type'    => 'email',
                'identifier' => $this->user->email,
                'user_id'    => $this->user->id,
                'success'    => false,
            ]
        );

        $previous = $this->user->previousLogin();

        $this->assertInstanceOf(Login::class, $previous); // @phpstan-ignore-line
        $this->assertSame($login1->id, $previous->id);
        $this->assertInstanceOf(Time::class, $previous->date);
    }

    /**
     * @see https://github.com/codeigniter4/shield/issues/103
     */
    public function testUpdateEmail(): void
    {
        // Update user's email
        $this->user->email  = 'foo@bar.com';
        $this->user->active = false;

        $users = model(UserModel::class);
        $users->save($this->user);

        /** @var User $user */
        $user = $users->find($this->user->id);

        $this->seeInDatabase($this->tables['identities'], [
            'user_id' => $user->id,
            'secret'  => 'foo@bar.com',
        ]);
        $this->assertSame('foo@bar.com', $user->email);
    }

    /**
     * @see https://github.com/codeigniter4/shield/issues/103
     */
    public function testUpdatePassword(): void
    {
        // Update user's email
        $this->user->email    = 'foo@bar.com';
        $this->user->password = 'foobar';
        $this->user->active   = false;

        $users = model(UserModel::class);
        $users->save($this->user);

        $user = $users->find($this->user->id);

        $this->assertTrue(service('passwords')->verify('foobar', $user->password_hash));
    }

    /**
     * @see https://github.com/codeigniter4/shield/issues/103
     */
    public function testUpdatePasswordHash(): void
    {
        // Update user's email
        $hash                      = service('passwords')->hash('foobar');
        $this->user->email         = 'foo@bar.com';
        $this->user->password_hash = $hash;
        $this->user->active        = false;

        $users = model(UserModel::class);
        $users->save($this->user);

        /** @var User $user */
        $user = $users->find($this->user->id);

        $this->seeInDatabase($this->tables['identities'], [
            'user_id' => $user->id,
            'secret'  => 'foo@bar.com',
            'secret2' => $hash,
        ]);
    }

    public function testCreateEmailIdentity(): void
    {
        $identity = $this->user->getEmailIdentity();
        $this->assertNull($identity);

        $this->user->createEmailIdentity([
            'email'    => 'foo@example.com',
            'password' => 'passbar',
        ]);

        $identity = $this->user->getEmailIdentity();
        $this->assertSame('foo@example.com', $identity->secret);
    }

    public function testSaveEmailIdentity(): void
    {
        $hash                      = service('passwords')->hash('passbar');
        $this->user->email         = 'foo@example.com';
        $this->user->password_hash = $hash;

        $this->user->saveEmailIdentity();

        $identity = $this->user->getEmailIdentity();
        $this->assertSame('foo@example.com', $identity->secret);
    }

    public function testActivate(): void
    {
        $this->user->active = false;
        model(UserModel::class)->save($this->user);

        $this->seeInDatabase($this->tables['users'], [
            'id'     => $this->user->id,
            'active' => 0,
        ]);

        $this->user->activate();

        // Refresh user
        $this->user = model(UserModel::class)->find($this->user->id);

        $this->assertTrue($this->user->active);
        $this->seeInDatabase($this->tables['users'], [
            'id'     => $this->user->id,
            'active' => 1,
        ]);
    }

    public function testDeactivate(): void
    {
        $this->user->active = true;
        model(UserModel::class)->save($this->user);

        $this->seeInDatabase($this->tables['users'], [
            'id'     => $this->user->id,
            'active' => 1,
        ]);

        $this->user->deactivate();

        // Refresh user
        $this->user = model(UserModel::class)->find($this->user->id);

        $this->assertFalse($this->user->active);
        $this->seeInDatabase($this->tables['users'], [
            'id'     => $this->user->id,
            'active' => 0,
        ]);
    }

    public function testIsActivatedSuccessWhenNotRequired(): void
    {
        $this->user->active = false;
        model(UserModel::class)->save($this->user);

        setting('Auth.actions', ['register' => null]);

        $this->assertTrue($this->user->isActivated());
    }

    public function testIsActivatedWhenRequired(): void
    {
        setting('Auth.actions', ['register' => '\Daycry\Auth\Authentication\Actions\EmailActivator']);
        $user = $this->user;

        $user->deactivate();
        /** @var User $user */
        $user = model(UserModel::class)->find($user->id);

        $this->assertTrue($user->isNotActivated());
        $this->assertFalse($user->isActivated());

        $user->activate();
        /** @var User $user */
        $user = model(UserModel::class)->find($user->id);

        $this->assertTrue($user->isActivated());
    }

    public function testIsNotActivated(): void
    {
        setting('Auth.actions', ['register' => '\CodeIgniter\Shield\Authentication\Actions\EmailActivator']);
        $user = $this->user;

        $user->active = false;
        model(UserModel::class)->save($user);

        /** @var User $user */
        $user = model(UserModel::class)->find($user->id);

        $this->assertFalse($user->isActivated());
    }

    public function testGetUserGroups(): void
    {
        $groupFoo = fake(GroupModel::class, ['name' => 'foo']);
        $groupBar = fake(GroupModel::class, ['name' => 'bar']);

        fake(GroupUserModel::class, ['group_id' => $groupFoo->id, 'user_id' => $this->user->id, 'until_at' => null]);
        fake(GroupUserModel::class, ['group_id' => $groupBar->id, 'user_id' => $this->user->id, 'until_at' => Time::yesterday()]);

        $groups = $this->user->getGroups();

        $this->assertCount(1, $groups);
        $this->assertSame('foo', $groups[0]);
    }

    public function testAddUserGroups(): void
    {
        fake(GroupModel::class, ['name' => 'foo']);

        $groups = $this->user->getGroups();
        $this->assertCount(0, $groups);

        $this->user->addGroup('foo');

        $groups = $this->user->getGroups();

        $this->assertCount(1, $groups);
        $this->assertSame('foo', $groups[0]);
        $this->assertTrue($this->user->inGroup('foo'));
    }

    public function testAddErrorUserGroups(): void
    {
        $this->expectException(AuthorizationException::class);

        $this->user->addGroup('foo');
    }

    public function testUserAlreadyGroups(): void
    {
        $groupFoo = fake(GroupModel::class, ['name' => 'foo']);

        fake(GroupUserModel::class, ['group_id' => $groupFoo->id, 'user_id' => $this->user->id, 'until_at' => null]);

        $groups = $this->user->getGroups();
        $this->assertCount(1, $groups);

        $this->user->addGroup('foo');

        $groups = $this->user->getGroups();

        $this->assertCount(1, $groups);
    }

    public function testUserNotInGroups(): void
    {
        fake(GroupModel::class, ['name' => 'foo']);

        $groups = $this->user->getGroups();
        $this->assertCount(0, $groups);

        $this->assertFalse($this->user->inGroup('foo'));
    }

    public function testRemoveUserGroups(): void
    {
        $groupFoo = fake(GroupModel::class, ['name' => 'foo']);
        $groupBar = fake(GroupModel::class, ['name' => 'bar']);

        fake(GroupUserModel::class, ['group_id' => $groupFoo->id, 'user_id' => $this->user->id, 'until_at' => null]);
        fake(GroupUserModel::class, ['group_id' => $groupBar->id, 'user_id' => $this->user->id, 'until_at' => null]);

        $groups = $this->user->getGroups();
        $this->assertCount(2, $groups);

        $this->user->removeGroup('foo');

        $groups = $this->user->getGroups();

        $this->assertCount(1, $groups);
        $this->assertSame('bar', $groups[0]);

        $criteria = [
            'group_id' => $groupFoo->id,
            'user_id'  => $this->user->id,
        ];

        $this->dontSeeInDatabase($this->tables['groups_users'], $criteria);
    }

    public function testSyncUserGroups(): void
    {
        $groupFoo = fake(GroupModel::class, ['name' => 'foo']);
        $groupBar = fake(GroupModel::class, ['name' => 'bar']);

        fake(GroupUserModel::class, ['group_id' => $groupFoo->id, 'user_id' => $this->user->id, 'until_at' => null]);
        fake(GroupUserModel::class, ['group_id' => $groupBar->id, 'user_id' => $this->user->id, 'until_at' => null]);

        $groups = $this->user->getGroups();
        $this->assertCount(2, $groups);

        $this->user->syncGroups('foo');

        $groups = $this->user->getGroups();

        $this->assertCount(1, $groups);
        $this->assertSame('foo', $groups[0]);
    }

    public function testErrorSyncUserGroups(): void
    {
        $this->expectException(AuthorizationException::class);

        $groupFoo = fake(GroupModel::class, ['name' => 'foo']);

        fake(GroupUserModel::class, ['group_id' => $groupFoo->id, 'user_id' => $this->user->id, 'until_at' => null]);

        $this->user->syncGroups('bar');
    }

    public function testGetUserPermissions(): void
    {
        $permissionFoo = fake(PermissionModel::class, ['name' => 'foo']);
        $permissionBar = fake(PermissionModel::class, ['name' => 'bar']);

        fake(PermissionUserModel::class, ['permission_id' => $permissionFoo->id, 'user_id' => $this->user->id, 'until_at' => null]);
        fake(PermissionUserModel::class, ['permission_id' => $permissionBar->id, 'user_id' => $this->user->id, 'until_at' => Time::yesterday()]);

        $permissions = $this->user->getPermissions();

        $this->assertCount(1, $permissions);
        $this->assertSame('foo', $permissions[0]);
    }

    public function testAddUserPermissions(): void
    {
        fake(PermissionModel::class, ['name' => 'foo']);

        $permissions = $this->user->getPermissions();
        $this->assertCount(0, $permissions);

        $this->user->addPermission('foo');

        $permissions = $this->user->getPermissions();

        $this->assertCount(1, $permissions);
        $this->assertSame('foo', $permissions[0]);
        $this->assertTrue($this->user->hasPermission('foo'));
    }

    public function testAddUserAlreadyPermissions(): void
    {
        $permissionFoo = fake(PermissionModel::class, ['name' => 'foo']);

        fake(PermissionUserModel::class, ['permission_id' => $permissionFoo->id, 'user_id' => $this->user->id, 'until_at' => null]);

        $permissions = $this->user->getPermissions();

        $this->assertCount(1, $permissions);
        $this->assertSame('foo', $permissions[0]);

        $this->user->addPermission('foo');

        $permissions = $this->user->getPermissions();

        $this->assertCount(1, $permissions);
        $this->assertSame('foo', $permissions[0]);
    }

    public function testAddErrorUserPermissions(): void
    {
        $this->expectException(AuthorizationException::class);

        $this->user->addPermission('foo');
    }

    public function testRemoveUserPermissions(): void
    {
        $permissionFoo = fake(PermissionModel::class, ['name' => 'foo']);
        $permissionBar = fake(PermissionModel::class, ['name' => 'bar']);

        fake(PermissionUserModel::class, ['permission_id' => $permissionFoo->id, 'user_id' => $this->user->id, 'until_at' => null]);
        fake(PermissionUserModel::class, ['permission_id' => $permissionBar->id, 'user_id' => $this->user->id, 'until_at' => Time::yesterday()]);

        $permissions = $this->user->getPermissions();

        $this->assertCount(1, $permissions);

        $this->user->removePermission('foo');

        $permissions = $this->user->getPermissions();

        $this->assertCount(0, $permissions);

        $criteria = [
            'permission_id' => $permissionFoo->id,
            'user_id'       => $this->user->id,
        ];

        $this->dontSeeInDatabase($this->tables['permissions_users'], $criteria);
    }

    public function testSyncUserPermissions(): void
    {
        $permissionFoo = fake(PermissionModel::class, ['name' => 'foo']);
        fake(PermissionModel::class, ['name' => 'bar']);

        fake(PermissionUserModel::class, ['permission_id' => $permissionFoo->id, 'user_id' => $this->user->id, 'until_at' => null]);

        $permissions = $this->user->getPermissions();
        $this->assertCount(1, $permissions);

        $this->user->syncPermissions('bar');

        $permissions = $this->user->getPermissions();

        $this->assertCount(1, $permissions);
        $this->assertSame('bar', $permissions[0]);

        $criteria = [
            'permission_id' => $permissionFoo->id,
            'user_id'       => $this->user->id,
        ];

        $this->dontSeeInDatabase($this->tables['permissions_users'], $criteria);
    }

    public function testErrorSyncUserPermissions(): void
    {
        $this->expectException(AuthorizationException::class);

        $permissionFoo = fake(PermissionModel::class, ['name' => 'foo']);

        fake(PermissionUserModel::class, ['permission_id' => $permissionFoo->id, 'user_id' => $this->user->id, 'until_at' => null]);

        $permissions = $this->user->getPermissions();
        $this->assertCount(1, $permissions);

        $this->user->syncPermissions('bar');
    }

    public function testErrorCanUserPermissionsGroup(): void
    {
        $this->expectException(LogicException::class);

        $permissionFoo = fake(PermissionModel::class, ['name' => 'foo']);
        $groupFoo      = fake(GroupModel::class, ['name' => 'foo']);

        fake(PermissionGroupModel::class, ['permission_id' => $permissionFoo->id, 'group_id' => $groupFoo->id, 'until_at' => null]);

        $permissions = $this->user->getPermissions();

        $this->assertCount(0, $permissions);

        $this->assertTrue($this->user->can('foo'));
    }

    public function testCanUserPermissionsGroup(): void
    {
        $permissionFoo = fake(PermissionModel::class, ['name' => 'foo.read']);
        $permissionBar = fake(PermissionModel::class, ['name' => 'bar.*']);
        $groupFoo      = fake(GroupModel::class, ['name' => 'foo']);

        fake(PermissionGroupModel::class, ['permission_id' => $permissionFoo->id, 'group_id' => $groupFoo->id, 'until_at' => null]);
        fake(PermissionGroupModel::class, ['permission_id' => $permissionBar->id, 'group_id' => $groupFoo->id, 'until_at' => null]);
        fake(GroupUserModel::class, ['group_id' => $groupFoo->id, 'user_id' => $this->user->id, 'until_at' => null]);

        $permissions = $this->user->getPermissions();

        $this->assertCount(0, $permissions);

        $this->assertTrue($this->user->can('foo.read'));
        $this->assertFalse($this->user->can('foo.write'));
        $this->assertTrue($this->user->can('bar.read'));
    }

    public function testCanUserPermissionsUser(): void
    {
        $permissionFoo = fake(PermissionModel::class, ['name' => 'foo.read']);
        $permissionBar = fake(PermissionModel::class, ['name' => 'bar.*']);
        $groupFoo      = fake(GroupModel::class, ['name' => 'foo']);

        fake(PermissionGroupModel::class, ['permission_id' => $permissionBar->id, 'group_id' => $groupFoo->id, 'until_at' => null]);
        fake(PermissionUserModel::class, ['permission_id' => $permissionFoo->id, 'user_id' => $this->user->id, 'until_at' => null]);
        fake(GroupUserModel::class, ['group_id' => $groupFoo->id, 'user_id' => $this->user->id, 'until_at' => null]);

        $permissions = $this->user->getPermissions();

        $this->assertCount(1, $permissions);

        $this->assertTrue($this->user->can('foo.read'));
        $this->assertFalse($this->user->can('foo.write'));
        $this->assertTrue($this->user->can('bar.read'));
    }

    public function testCannotUserPermissionsUserWithoutGroup(): void
    {
        $permissionFoo = fake(PermissionModel::class, ['name' => 'foo.read']);

        fake(PermissionUserModel::class, ['permission_id' => $permissionFoo->id, 'user_id' => $this->user->id, 'until_at' => null]);

        $permissions = $this->user->getPermissions();

        $this->assertCount(1, $permissions);

        $this->assertFalse($this->user->can('foo.write'));
    }
}
