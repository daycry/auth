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
use Daycry\Auth\Entities\User;
use Daycry\Auth\Filters\PermissionFilter;
use Daycry\Auth\Models\PermissionModel;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Tests\Support\FilterTestCase;

/**
 * @internal
 */
final class PermissionFilterTest extends FilterTestCase
{
    use DatabaseTestTrait;

    protected string $alias       = 'permission';
    protected string $classname   = PermissionFilter::class;
    protected string $routeFilter = 'permission:admin.access';

    public function testFilterNotAuthorizedSession(): void
    {
        $this->inkectMockAttributes(['defaultAuthenticator' => 'session']);
        $result = $this->call('GET', 'protected-route');

        $result->assertRedirectTo('/login');

        $this->assertNotEmpty(session()->getTempdata('beforeLoginUrl'));
        $this->assertSame(site_url('protected-route'), session()->getTempdata('beforeLoginUrl'));

        $result = $this->get('open-route');
        $result->assertStatus(200);
        $result->assertSee('Open');
    }

    public function testFilterNotAuthorizedJWT(): void
    {
        $this->inkectMockAttributes(['defaultAuthenticator' => 'jwt']);
        $result = $this->call('GET', 'protected-route');

        $result->assertStatus(401);
    }

    public function testFilterSuccessSession(): void
    {
        $this->inkectMockAttributes(['defaultAuthenticator' => 'session']);

        /** @var User $user */
        $user = fake(UserModel::class);
        model(UserIdentityModel::class)->createEmailIdentity($user, ['email' => 'foo@example.com', 'password' => 'secret']);

        fake(PermissionModel::class, ['name' => 'admin.access']);
        $user->addPermission('admin.access');

        $result = $this
            ->actingAs($user)
            ->get('protected-route');

        $result->assertStatus(200);
        $result->assertSee('Protected');

        $this->assertSame($user->id, auth('session')->id());
        $this->assertSame($user->id, auth('session')->user()->id);
    }

    public function testFilterAuthSessionNotAuthorizedSession(): void
    {
        $this->inkectMockAttributes(['defaultAuthenticator' => 'session']);

        /** @var User $user */
        $user = fake(UserModel::class);
        model(UserIdentityModel::class)->createEmailIdentity($user, ['email' => 'foo@example.com', 'password' => 'secret']);

        fake(PermissionModel::class, ['name' => 'admin.access']);
        fake(PermissionModel::class, ['name' => 'admin.read']);
        $user->addPermission('admin.read');

        $result = $this
            ->actingAs($user)
            ->get('protected-route');

        $result->assertRedirectTo(config('Auth')->permissionDeniedRedirect());
        $result->assertSessionHas('error', lang('Auth.notEnoughPrivilege'));
    }

    public function testFilterSuccessJWT(): void
    {
        $this->inkectMockAttributes(['defaultAuthenticator' => 'jwt']);
        /** @var User $user */
        $user = fake(UserModel::class);
        fake(PermissionModel::class, ['name' => 'admin.access']);
        $user->addPermission('admin.access');

        $jwt   = service('settings')->get('Auth.jwtAdapter');
        $token = (new $jwt())->encode($user->id);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('protected-route');

        $result->assertStatus(200);
        $result->assertSee('Protected');

        $this->assertSame($user->id, auth('jwt')->id());
        $this->assertSame($user->id, auth('jwt')->user()->id);
    }

    public function testFilterAuthJWTNotAuthorizedJWTJson(): void
    {
        $this->inkectMockAttributes(['defaultAuthenticator' => 'jwt']);

        /** @var User $user */
        $user = fake(UserModel::class);
        model(UserIdentityModel::class)->createEmailIdentity($user, ['email' => 'foo@example.com', 'password' => 'secret']);

        fake(PermissionModel::class, ['name' => 'admin.access']);
        fake(PermissionModel::class, ['name' => 'admin.read']);
        $user->addPermission('admin.read');

        $jwt   = service('settings')->get('Auth.jwtAdapter');
        $token = (new $jwt())->encode($user->id);

        $result = $this->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Bearer ' . $token])
            ->get('protected-route');

        $result->assertStatus(401);
    }

    public function testFilterAuthJWTNotAuthorizedJWT(): void
    {
        $this->inkectMockAttributes(['defaultAuthenticator' => 'jwt']);

        /** @var User $user */
        $user = fake(UserModel::class);
        model(UserIdentityModel::class)->createEmailIdentity($user, ['email' => 'foo@example.com', 'password' => 'secret']);

        fake(PermissionModel::class, ['name' => 'admin.access']);
        fake(PermissionModel::class, ['name' => 'admin.read']);
        $user->addPermission('admin.read');

        $jwt   = service('settings')->get('Auth.jwtAdapter');
        $token = (new $jwt())->encode($user->id);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('protected-route');

        $result->assertStatus(302);
    }
}
