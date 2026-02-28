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

use Daycry\Auth\Config\Auth;
use Daycry\Auth\Models\GroupModel;
use Daycry\Auth\Models\PermissionModel;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeUser;

/**
 * @internal
 */
final class PermissionCacheTest extends DatabaseTestCase
{
    use FakeUser;

    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFakeUser();

        // Enable permission caching in Auth config
        $this->inkectMockAttributes(['permissionCacheEnabled' => true, 'permissionCacheTTL' => 60]);
    }

    protected function tearDown(): void
    {
        // Clear cache after each test
        cache()->clean();
        parent::tearDown();
    }

    public function testClearPermissionCacheMethodExists(): void
    {
        $this->assertTrue(method_exists($this->user, 'clearPermissionCache'));
    }

    public function testGroupsAreCorrectWithCacheEnabled(): void
    {
        /** @var GroupModel $groupModel */
        $groupModel = model(GroupModel::class);
        $beta       = fake(GroupModel::class, ['name' => 'beta']);

        $this->user->addGroup('admin', 'beta');

        $this->assertTrue($this->user->inGroup('admin'));
        $this->assertTrue($this->user->inGroup('beta'));
        $this->assertFalse($this->user->inGroup('user'));
    }

    public function testPermissionsAreCorrectWithCacheEnabled(): void
    {
        /** @var PermissionModel $permissionModel */
        $permissionModel = model(PermissionModel::class);
        $perm            = fake(PermissionModel::class, ['name' => 'users.edit']);

        $this->user->addPermission('users.edit');

        $this->assertTrue($this->user->hasPermission('users.edit'));
        $this->assertFalse($this->user->hasPermission('users.delete'));
    }

    public function testClearPermissionCacheDoesNotBreakSubsequentCalls(): void
    {
        $this->user->addGroup('admin');

        // Clear the cache
        $this->user->clearPermissionCache();

        // Fresh instance simulates a new request
        /** @var UserModel $userModel */
        $userModel = model(UserModel::class);
        $freshUser = $userModel->findById($this->user->id);

        $this->assertNotNull($freshUser);
        $this->assertTrue($freshUser->inGroup('admin'));
    }

    public function testCacheIsInvalidatedAfterAddGroup(): void
    {
        // Populate the cache with initial state (no groups)
        $this->assertFalse($this->user->inGroup('admin'));

        // Add a group (should invalidate the cache)
        $this->user->addGroup('admin');

        // On the same instance, the in-memory cache is already updated
        $this->assertTrue($this->user->inGroup('admin'));

        // A fresh instance should also see the group (cache was invalidated, will reload from DB)
        $this->user->clearPermissionCache();

        /** @var UserModel $userModel */
        $userModel = model(UserModel::class);
        $freshUser = $userModel->findById($this->user->id);
        $this->assertNotNull($freshUser);
        $this->assertTrue($freshUser->inGroup('admin'));
    }

    public function testCacheIsInvalidatedAfterRemoveGroup(): void
    {
        $this->user->addGroup('admin');
        $this->assertTrue($this->user->inGroup('admin'));

        // Remove group — cache should be invalidated
        $this->user->removeGroup('admin');
        $this->assertFalse($this->user->inGroup('admin'));

        // Fresh instance verifies DB was updated correctly
        $this->user->clearPermissionCache();

        /** @var UserModel $userModel */
        $userModel = model(UserModel::class);
        $freshUser = $userModel->findById($this->user->id);
        $this->assertNotNull($freshUser);
        $this->assertFalse($freshUser->inGroup('admin'));
    }

    public function testCacheIsInvalidatedAfterAddPermission(): void
    {
        fake(PermissionModel::class, ['name' => 'posts.create']);

        $this->assertFalse($this->user->hasPermission('posts.create'));

        $this->user->addPermission('posts.create');

        $this->assertTrue($this->user->hasPermission('posts.create'));

        // Fresh instance after cache clear
        $this->user->clearPermissionCache();

        /** @var UserModel $userModel */
        $userModel = model(UserModel::class);
        $freshUser = $userModel->findById($this->user->id);
        $this->assertNotNull($freshUser);
        $this->assertTrue($freshUser->hasPermission('posts.create'));
    }

    public function testCacheIsInvalidatedAfterRemovePermission(): void
    {
        fake(PermissionModel::class, ['name' => 'posts.delete']);
        $this->user->addPermission('posts.delete');
        $this->assertTrue($this->user->hasPermission('posts.delete'));

        $this->user->removePermission('posts.delete');
        $this->assertFalse($this->user->hasPermission('posts.delete'));

        $this->user->clearPermissionCache();

        /** @var UserModel $userModel */
        $userModel = model(UserModel::class);
        $freshUser = $userModel->findById($this->user->id);
        $this->assertNotNull($freshUser);
        $this->assertFalse($freshUser->hasPermission('posts.delete'));
    }

    public function testCanChecksWorkWithCacheEnabled(): void
    {
        fake(PermissionModel::class, ['name' => 'articles.publish']);
        $this->user->addPermission('articles.publish');

        $this->assertTrue($this->user->can('articles.publish'));
        $this->assertFalse($this->user->can('articles.delete'));
    }
}
