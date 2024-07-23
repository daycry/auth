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
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Filters\AuthFilter;
use Daycry\Auth\Models\UserModel;
use Tests\Support\FilterTestCase;

/**
 * @internal
 */
final class AuthSessionFilterTest extends FilterTestCase
{
    use FeatureTestTrait;

    protected $namespace;
    protected string $alias       = 'auth';
    protected string $classname   = AuthFilter::class;
    protected string $routeFilter = 'auth:session';

    protected function setUp(): void
    {
        Services::reset(true);

        parent::setUp();

        $_SESSION = [];
    }

    public function testFilterNotAuthorized(): void
    {
        $result = $this->call('GET', 'protected-route');

        $result->assertRedirectTo('/login');

        $result = $this->get('open-route');

        $result->assertStatus(200);
        $result->assertSee('Open');
    }

    public function testFilterSuccess(): void
    {
        /** @var User $user */
        $user                   = fake(UserModel::class);
        $_SESSION['user']['id'] = $user->id;

        $result = $this->withSession(['user' => ['id' => $user->id]])
            ->get('protected-route');

        $result->assertStatus(200);
        $result->assertSee('Protected');

        $this->assertSame($user->id, auth('session')->id());
        $this->assertSame($user->id, auth('session')->user()->id);

        // Last Active should have been updated
        $this->assertInstanceOf(Time::class, auth('session')->user()->last_active);
    }

    public function testFilterBanned(): void
    {
        /** @var User $user */
        $user                   = fake(UserModel::class);
        $_SESSION['user']['id'] = $user->id;

        $user->ban('banned');

        $result = $this->withSession(['user' => ['id' => $user->id]])
            ->get('protected-route');

        $result->assertStatus(302);

        $this->assertNull(auth('session')->id());
    }
}
