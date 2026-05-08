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

use CodeIgniter\I18n\Time;
use Config\Services;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Filters\PasswordAgeFilter;
use Daycry\Auth\Models\UserModel;
use Tests\Support\FilterTestCase;

/**
 * @internal
 */
final class PasswordAgeFilterTest extends FilterTestCase
{
    protected string $alias       = 'password-age';
    protected string $classname   = PasswordAgeFilter::class;
    protected string $routeFilter = 'password-age';

    protected function setUp(): void
    {
        Services::reset(true);

        parent::setUp();

        $_SESSION = [];

        // Stub the routes the redirect points at.
        $routes = service('routes');
        $routes->get('auth/force-reset', static function (): void {
            echo 'ForceReset';
        }, ['as' => 'auth-force-reset']);
        Services::injectMock('routes', $routes);
    }

    public function testNoOpWhenFeatureDisabled(): void
    {
        // passwordMaxAge defaults to 0 → filter no-ops.
        $user = fake(UserModel::class);

        $result = $this->withSession(['user' => ['id' => $user->id]])
            ->get('protected-route');

        $result->assertStatus(200);
    }

    public function testNoOpForAnonymousRequest(): void
    {
        $this->injectMockAttributesSecurity(['passwordMaxAge' => 60]);

        $result = $this->call('GET', 'protected-route');

        // Anonymous: filter does not redirect; only the auth filter
        // upstream would. With no auth filter wired, request passes.
        $result->assertStatus(200);
    }

    public function testNoOpWhenPasswordChangedAtIsNull(): void
    {
        $this->injectMockAttributesSecurity(['passwordMaxAge' => 60]);

        $user = fake(UserModel::class);
        // Default fake users have password_changed_at = null → grandfathered.

        $result = $this->withSession(['user' => ['id' => $user->id]])
            ->get('protected-route');

        $result->assertStatus(200);
    }

    public function testRedirectsWhenPasswordIsExpired(): void
    {
        $this->injectMockAttributesSecurity(['passwordMaxAge' => 60]);

        // The filter calls $user->forcePasswordReset() which walks the
        // email_password identity — so the user must have one.
        $user = $this->createUserWithEmailIdentity();

        $this->stampPasswordChangedAt(
            (int) $user->id,
            Time::now()->subDays(7)->toDateTimeString(),
        );

        $result = $this->withSession(['user' => ['id' => $user->id]])
            ->get('protected-route');

        $result->assertStatus(302);
    }

    public function testAllowsWhenPasswordIsRecent(): void
    {
        $this->injectMockAttributesSecurity(['passwordMaxAge' => 30 * DAY]);

        $user = fake(UserModel::class);

        $this->stampPasswordChangedAt(
            (int) $user->id,
            Time::now()->subDays(1)->toDateTimeString(),
        );

        $result = $this->withSession(['user' => ['id' => $user->id]])
            ->get('protected-route');

        $result->assertStatus(200);
    }

    private function stampPasswordChangedAt(int $userId, string $when): void
    {
        $userModel = model(UserModel::class);
        $userModel->builder()
            ->where('id', $userId)
            ->update(['password_changed_at' => $when]);
    }

    private function createUserWithEmailIdentity(): User
    {
        $userModel   = model(UserModel::class);
        $user        = new User(['username' => 'alice_' . uniqid(), 'active' => true]);
        $user->email = 'alice_' . uniqid() . '@example.com';
        $user->setPassword('secret-pwd-42');
        $userModel->save($user);

        return $userModel->findById($userModel->getInsertID());
    }
}
