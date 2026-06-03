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

namespace Tests\Controllers;

use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;

/**
 * Covers the stateless JWT lifecycle: login, refresh-token rotation
 * (single-use), and revoke-on-logout. These are security-critical guarantees
 * that previously had no controller-level coverage.
 *
 * @internal
 */
final class JwtControllerTest extends DatabaseTestCase
{
    use FeatureTestTrait;

    protected $namespace = 'Daycry\Auth';

    protected function setUp(): void
    {
        parent::setUp();

        $routes = service('routes');
        $routes->post('auth/jwt/login', '\Daycry\Auth\Controllers\JwtController::login', ['as' => 'jwt-login']);
        $routes->post('auth/jwt/refresh', '\Daycry\Auth\Controllers\JwtController::refresh', ['as' => 'jwt-refresh']);
        $routes->post('auth/jwt/logout', '\Daycry\Auth\Controllers\JwtController::logout', ['as' => 'jwt-logout']);
        Services::injectMock('routes', $routes);

        $user        = fake(UserModel::class);
        $user->email = 'jwt@example.com';
        model(UserModel::class)->save($user);

        $this->db->table($this->tables['identities'])->truncate();
        model(UserIdentityModel::class)->createEmailIdentity($user, [
            'email'    => 'jwt@example.com',
            'password' => 'secret123',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function login(): array
    {
        $result = $this->post('auth/jwt/login', [
            'email'    => 'jwt@example.com',
            'password' => 'secret123',
        ]);

        $result->assertStatus(200);

        return json_decode((string) $result->getJSON(), true);
    }

    public function testLoginReturnsAccessAndRefreshTokens(): void
    {
        $tokens = $this->login();

        $this->assertArrayHasKey('access_token', $tokens);
        $this->assertArrayHasKey('refresh_token', $tokens);
        $this->assertSame('Bearer', $tokens['token_type']);
    }

    public function testRefreshRotatesTokenAndRejectsReuseOfOldToken(): void
    {
        $tokens     = $this->login();
        $userId     = $tokens['user_id'];
        $oldRefresh = $tokens['refresh_token'];

        // First refresh succeeds and returns a brand-new refresh token.
        $first = $this->post('auth/jwt/refresh', [
            'user_id'       => $userId,
            'refresh_token' => $oldRefresh,
        ]);
        $first->assertStatus(200);
        $rotated = json_decode((string) $first->getJSON(), true);
        $this->assertNotSame($oldRefresh, $rotated['refresh_token'], 'Refresh must rotate the token');

        // The OLD refresh token is now single-use-consumed and must be rejected.
        $reuse = $this->post('auth/jwt/refresh', [
            'user_id'       => $userId,
            'refresh_token' => $oldRefresh,
        ]);
        $reuse->assertStatus(401);

        // The NEW refresh token still works.
        $this->post('auth/jwt/refresh', [
            'user_id'       => $userId,
            'refresh_token' => $rotated['refresh_token'],
        ])->assertStatus(200);
    }

    public function testLogoutRevokesRefreshToken(): void
    {
        $tokens  = $this->login();
        $userId  = $tokens['user_id'];
        $refresh = $tokens['refresh_token'];

        $this->post('auth/jwt/logout', [
            'user_id'       => $userId,
            'refresh_token' => $refresh,
        ])->assertStatus(200);

        // After logout the refresh token must no longer be usable.
        $this->post('auth/jwt/refresh', [
            'user_id'       => $userId,
            'refresh_token' => $refresh,
        ])->assertStatus(401);
    }

    public function testRefreshRejectsTokenForWrongUser(): void
    {
        $tokens  = $this->login();
        $refresh = $tokens['refresh_token'];

        // A valid token presented with a different user_id must be rejected.
        $this->post('auth/jwt/refresh', [
            'user_id'       => 999999,
            'refresh_token' => $refresh,
        ])->assertStatus(401);
    }

    public function testLoginWithWrongPasswordReturns401(): void
    {
        $this->post('auth/jwt/login', [
            'email'    => 'jwt@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }
}
