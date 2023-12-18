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

/**
 * @internal
 */
final class AuthJWTFilterTest extends FilterTestCase
{
    use FeatureTestTrait;

    protected $namespace;

    /*protected function setUp(): void
    {
        Services::reset(true);

        $this->alias = 'jwt';
        $this->classname = JWT::class;

        parent::setUp();

        $_SESSION = [];
        
        // Add a test route that we can visit to trigger.
        $routes = service('routes');
        $routes->group('/', ['filter' => 'auth:jwt'], static function ($routes): void {
            $routes->get('protected-route', static function (): void {
                echo 'Protected';
            });
        });
        $routes->get('open-route', static function (): void {
            echo 'Open';
        });
        $routes->get('login', 'AuthController::login', ['as' => 'login']);
        Services::injectMock('routes', $routes);
    }

    public function testFilterNotAuthorized(): void
    {
        $result = $this->call('get', 'protected-route');

        $result->assertStatus(401);

        $result = $this->get('open-route');

        $result->assertStatus(200);
        $result->assertSee('Open');
    }*/
}