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

use Config\Services;
use Daycry\Auth\Filters\PasswordConfirmFilter;
use Daycry\Auth\Models\UserModel;
use Tests\Support\FilterTestCase;

/**
 * @internal
 */
final class PasswordConfirmFilterTest extends FilterTestCase
{
    protected string $alias       = 'password-confirm';
    protected string $classname   = PasswordConfirmFilter::class;
    protected string $routeFilter = 'password-confirm';

    protected function setUp(): void
    {
        Services::reset(true);

        parent::setUp();

        $_SESSION = [];

        // Add the password-confirm route the redirect points at.
        $routes = service('routes');
        $routes->get('auth/confirm-password', static function (): void {
            echo 'ConfirmForm';
        }, ['as' => 'password-confirm-show']);
        Services::injectMock('routes', $routes);
    }

    public function testAnonymousRequestSkipsFilter(): void
    {
        // Filter no-ops on anonymous requests; pairs with `session`/`auth`
        // which would handle the redirect to login.
        $result = $this->call('GET', 'protected-route');

        $result->assertStatus(200);
        $result->assertSee('Protected');
    }

    public function testLoggedInWithRecentConfirmationAllowsRequest(): void
    {
        $user = fake(UserModel::class);

        $result = $this->withSession([
            'user'                  => ['id' => $user->id],
            'password_confirmed_at' => time(),
        ])->get('protected-route');

        $result->assertStatus(200);
        $result->assertSee('Protected');
    }

    public function testLoggedInWithoutConfirmationRedirects(): void
    {
        $user = fake(UserModel::class);

        $result = $this->withSession([
            'user' => ['id' => $user->id],
        ])->get('protected-route');

        $result->assertStatus(302);
        $result->assertRedirectTo(site_url('auth/confirm-password'));
    }

    public function testLoggedInWithExpiredConfirmationRedirects(): void
    {
        $this->injectMockAttributesSecurity(['passwordConfirmationLifetime' => 60]);

        $user = fake(UserModel::class);

        $result = $this->withSession([
            'user'                  => ['id' => $user->id],
            'password_confirmed_at' => time() - 120, // expired
        ])->get('protected-route');

        $result->assertStatus(302);
    }

    public function testZeroLifetimeAlwaysRequiresConfirmation(): void
    {
        $this->injectMockAttributesSecurity(['passwordConfirmationLifetime' => 0]);

        $user = fake(UserModel::class);

        $result = $this->withSession([
            'user'                  => ['id' => $user->id],
            'password_confirmed_at' => time(), // even fresh, lifetime=0 rejects
        ])->get('protected-route');

        $result->assertStatus(302);
    }
}
