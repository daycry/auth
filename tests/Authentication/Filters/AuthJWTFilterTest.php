<?php

declare(strict_types=1);

namespace Tests\Authentication\Filters;

use CodeIgniter\Config\Factories;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Filters\GroupFilter;
use Daycry\Auth\Models\UserModel;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Daycry\Auth\Authentication\Authenticators\JWT;
use Daycry\Auth\Entities\Group;
use Daycry\Auth\Models\GroupModel;
use Tests\Support\FilterTestCase;
use Daycry\Auth\Config\Auth;
use Daycry\Auth\Filters\AuthFilter;

/**
 * @internal
 */
final class AuthJWTFilterTest extends FilterTestCase
{
    use FeatureTestTrait;

    protected $namespace;

    protected function setUp(): void
    {
        Services::reset(true);

        $this->alias = 'auth:jwt';
        $this->classname = AuthFilter::class;
        
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

        $jwt = service('settings')->get('Auth.jwtAdapter');
        $token = (new $jwt)->encode($user->id);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('protected-route');

        $result->assertStatus(200);
        $result->assertSee('Protected');

        $this->assertSame($user->id, auth('jwt')->id());
        $this->assertSame($user->id, auth('jwt')->user()->id);
    }

    public function testFilterBanned(): void
    {
        /** @var User $user */
        $user = fake(UserModel::class);
        $user->ban('banned');

        $jwt = service('settings')->get('Auth.jwtAdapter');
        $token = (new $jwt)->encode($user->id);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('protected-route');

        $result->assertStatus(401);

        $this->assertNull(auth('jwt')->id());
    }
}