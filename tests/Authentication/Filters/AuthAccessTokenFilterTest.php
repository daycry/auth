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
use Daycry\Auth\Entities\User;
use Daycry\Auth\Filters\AuthFilter;
use Daycry\Auth\Models\UserModel;
use Tests\Support\FilterTestCase;

/**
 * @internal
 */
final class AuthAccessTokenFilterTest extends FilterTestCase
{
    protected $namespace;
    protected string $alias       = 'auth';
    protected string $classname   = AuthFilter::class;
    protected string $routeFilter = 'auth:access_token';

    protected function setUp(): void
    {
        Services::reset(true);

        parent::setUp();

        $_SESSION = [];
    }

    public function testFilterNotAuthorized(): void
    {
        $result = $this->call('get', 'protected-route');

        $result->assertStatus(401);

        $result = $this->get('open-route');

        $result->assertStatus(200);
        $result->assertSee('Open');
    }

    public function testFilterSuccess(): void
    {
        /** @var User $user */
        $user = fake(UserModel::class);

        $token = $user->generateAccessToken('foo');

        $result = $this->withHeaders(['X-API-KEY' => $token->raw_token])
            ->get('protected-route');

        $result->assertStatus(200);
        $result->assertSee('Protected');

        $this->assertSame($user->id, auth('access_token')->id());
        $this->assertSame($user->id, auth('access_token')->user()->id);
    }

    public function testFilterBanned(): void
    {
        /** @var User $user */
        $user = fake(UserModel::class);
        $user->ban('banned');

        $token = $user->generateAccessToken('foo');

        $result = $this->withHeaders(['X-API-KEY' => $token->raw_token])
            ->get('protected-route');

        $result->assertStatus(401);

        $this->assertNull(auth('access_token')->id());
    }
}
