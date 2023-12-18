<?php

declare(strict_types=1);

/**
 * This file is part of CodeIgniter Shield.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Tests\Authentication\Filters;

use Daycry\Auth\Entities\User;
use Daycry\Auth\Filters\GroupFilter;
use Daycry\Auth\Models\UserModel;
use CodeIgniter\Test\DatabaseTestTrait;
use Daycry\Auth\Entities\Group;
use Daycry\Auth\Models\GroupModel;
use Tests\Support\FilterTestCase;

/**
 * @internal
 */
final class GroupFilterTest extends FilterTestCase
{
    use DatabaseTestTrait;

    protected string $alias       = 'group';
    protected string $classname   = GroupFilter::class;
    protected string $routeFilter = 'group:admin';

    public function testFilterNotAuthorized(): void
    {
        $result = $this->call('get', 'protected-route');

        $result->assertRedirectTo('/login');

        $result = $this->get('open-route');
        $result->assertStatus(200);
        $result->assertSee('Open');
    }

    public function testFilterNotAuthorizedStoresRedirectToEntranceUrlIntoSession(): void
    {
        $result = $this->call('get', 'protected-route');

        $result->assertRedirectTo('/login');

        $this->assertNotEmpty(session()->getTempdata('beforeLoginUrl'));
        $this->assertSame(site_url('protected-route'), session()->getTempdata('beforeLoginUrl'));
    }

    public function testFilterSuccess(): void
    {
        fake(GroupModel::class,['name' => 'admin']);

        /** @var User $user */
        $user = fake(UserModel::class);
        $user->createEmailIdentity(['email' => 'test', 'password' => 'test']);
        $user->addGroup('admin');

        $result = $this
            ->actingAs($user)
            ->get('protected-route');

        $result->assertStatus(200);
        $result->assertSee('Protected');

        $this->assertSame($user->id, auth('session')->id());
        $this->assertSame($user->id, auth('session')->user()->id);
    }

    public function testFilterIncorrectGroupNoPrevious(): void
    {
        fake(GroupModel::class,['name' => 'beta']);

        /** @var User $user */
        $user = fake(UserModel::class);
        $user->createEmailIdentity(['email' => 'test', 'password' => 'test']);
        $user->addGroup('beta');

        $result = $this
            ->actingAs($user)
            ->get('protected-route');

        // Should redirect to home page since previous_url is not set
        $result->assertRedirectTo(config('Auth')->groupDeniedRedirect());
        // Should have error message
        $result->assertSessionHas('error', lang('Auth.notEnoughPrivilege'));
    }
}
