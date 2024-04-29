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

namespace Tests\Authentication\Filters;

use CodeIgniter\Test\DatabaseTestTrait;
use Daycry\Auth\Config\Auth;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Filters\GroupFilter;
use Daycry\Auth\Models\GroupModel;
use Daycry\Auth\Models\UserModel;
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

    public function testFilterNotAuthorizedSession(): void
    {
        $this->inkectMockAttributes(['defaultAuthenticator' => 'session']);
        $result = $this->call('get', 'protected-route');

        $result->assertRedirectTo('/login');

        $result = $this->get('open-route');
        $result->assertStatus(200);
        $result->assertSee('Open');
    }

    public function testFilterNotAuthorizedJWT(): void
    {
        $this->inkectMockAttributes(['defaultAuthenticator' => 'jwt']);
        $result = $this->call('get', 'protected-route');

        $result->assertStatus(401);
    }

    public function testFilterNotAuthorizedStoresRedirectToEntranceUrlIntoSession(): void
    {
        $this->inkectMockAttributes(['defaultAuthenticator' => 'session']);
        $result = $this->call('get', 'protected-route');

        $result->assertRedirectTo('/login');

        $this->assertNotEmpty(session()->getTempdata('beforeLoginUrl'));
        $this->assertSame(site_url('protected-route'), session()->getTempdata('beforeLoginUrl'));
    }

    public function testFilterSuccessSession(): void
    {
        $this->inkectMockAttributes(['defaultAuthenticator' => 'session']);
        // fake(GroupModel::class, ['name' => 'admin']);

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

    public function testFilterIncorrectGroupNoPreviousSession(): void
    {
        $this->inkectMockAttributes(['defaultAuthenticator' => 'session']);
        fake(GroupModel::class, ['name' => 'beta']);

        /** @var User $user */
        $user = fake(UserModel::class);
        $user->createEmailIdentity(['email' => 'test', 'password' => 'test']);
        $user->addGroup('beta');

        $result = $this
            ->actingAs($user)
            ->get('protected-route');

        // Should redirect to home page since previous_url is not set
        /** @var Auth $config */
        $config = config('Auth');
        $result->assertRedirectTo($config->groupDeniedRedirect());
        // Should have error message
        $result->assertSessionHas('error', lang('Auth.notEnoughPrivilege'));
    }

    public function testFilterIncorrectGroupNoPreviousJWT(): void
    {
        $this->inkectMockAttributes(['defaultAuthenticator' => 'jwt']);
        fake(GroupModel::class, ['name' => 'beta']);

        /** @var User $user */
        $user = fake(UserModel::class);
        $user->createEmailIdentity(['email' => 'test', 'password' => 'test']);
        $user->addGroup('beta');

        $jwt   = service('settings')->get('Auth.jwtAdapter');
        $token = (new $jwt())->encode($user->id);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])->get('protected-route');

        $result->assertStatus(401);
    }
}
