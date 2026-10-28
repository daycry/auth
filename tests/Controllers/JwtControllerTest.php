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
use Daycry\Auth\Entities\User;
use Daycry\Auth\Libraries\TOTP;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Services\PasswordChangeRecorder;
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

        // Reuse of a consumed token revokes its successor too.
        $this->post('auth/jwt/refresh', [
            'user_id'       => $userId,
            'refresh_token' => $rotated['refresh_token'],
        ])->assertStatus(401);
    }

    public function testRefreshReplayDoesNotRevokeAnotherLoginFamily(): void
    {
        $first = $this->login();
        $other = $this->login();
        $this->post('auth/jwt/refresh', ['user_id' => $first['user_id'], 'refresh_token' => $first['refresh_token']])->assertStatus(200);
        $this->post('auth/jwt/refresh', ['user_id' => $first['user_id'], 'refresh_token' => $first['refresh_token']])->assertStatus(401);
        $this->post('auth/jwt/refresh', ['user_id' => $other['user_id'], 'refresh_token' => $other['refresh_token']])->assertStatus(200);
    }

    public function testLegacyRefreshTokenIsUpgradedToAFamily(): void
    {
        $tokens   = $this->login();
        $model    = model(UserIdentityModel::class);
        $identity = $model->where('type', 'jwt_refresh')->first();
        $model->update($identity->id, ['refresh_family' => null]);

        $first = $this->post('auth/jwt/refresh', ['user_id' => $tokens['user_id'], 'refresh_token' => $tokens['refresh_token']]);
        $first->assertStatus(200);
        $next = json_decode((string) $first->getJSON(), true);
        $this->post('auth/jwt/refresh', ['user_id' => $tokens['user_id'], 'refresh_token' => $tokens['refresh_token']])->assertStatus(401);
        $this->post('auth/jwt/refresh', ['user_id' => $tokens['user_id'], 'refresh_token' => $next['refresh_token']])->assertStatus(401);
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

    public function testPasswordChangeRevokesOutstandingRefreshTokens(): void
    {
        // An attacker holding a stolen refresh token must not survive the
        // victim's password change: token_version invalidates the access
        // token, and the refresh family must die with it — otherwise /refresh
        // mints a fresh access token carrying the *new* token_version.
        $tokens = $this->login();
        $user   = model(UserModel::class)->findById($tokens['user_id']);
        $this->assertInstanceOf(User::class, $user);

        (new PasswordChangeRecorder())->record($user, null);

        $this->post('auth/jwt/refresh', [
            'user_id'       => $tokens['user_id'],
            'refresh_token' => $tokens['refresh_token'],
        ])->assertStatus(401);
    }

    public function testBanRevokesOutstandingRefreshTokens(): void
    {
        $tokens = $this->login();
        $user   = model(UserModel::class)->findById($tokens['user_id']);
        $this->assertInstanceOf(User::class, $user);

        $user->ban('abuse');
        // Lifting the ban must not resurrect tokens issued before it.
        $user->unBan();

        $this->post('auth/jwt/refresh', [
            'user_id'       => $tokens['user_id'],
            'refresh_token' => $tokens['refresh_token'],
        ])->assertStatus(401);
    }

    public function testRefreshRejectsBannedUserEvenWithALiveToken(): void
    {
        // Defense in depth: a ban applied outside User::ban() (e.g. a direct
        // UPDATE) leaves the refresh token alive; refresh must still refuse.
        $tokens = $this->login();

        $this->db->table($this->tables['users'])
            ->where('id', $tokens['user_id'])
            ->update(['status' => 'banned']);

        $this->post('auth/jwt/refresh', [
            'user_id'       => $tokens['user_id'],
            'refresh_token' => $tokens['refresh_token'],
        ])->assertStatus(401);
    }

    public function testLoginWithWrongPasswordReturns401(): void
    {
        $this->post('auth/jwt/login', [
            'email'    => 'jwt@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }

    public function testMandatoryMfaCannotBeBypassedWithPasswordOnlyJwtLogin(): void
    {
        setting('AuthSecurity.mfaRequired', true);
        $this->post('auth/jwt/login', ['email' => 'jwt@example.com', 'password' => 'secret123'])->assertStatus(403);
        $this->assertSame(0, model(UserIdentityModel::class)->where('type', 'jwt_refresh')->countAllResults());
    }

    public function testMandatoryMfaAcceptsTotpAndPreservesAssuranceOnRotation(): void
    {
        $user = model(UserModel::class)->findByCredentials(['email' => 'jwt@example.com']);
        $this->assertInstanceOf(User::class, $user);
        $user->enableTotp();
        $user->confirmTotp();
        setting('AuthSecurity.mfaRequired', true);
        $code   = TOTP::getCode($user->getTotpSecret());
        $result = $this->post('auth/jwt/login', ['email' => 'jwt@example.com', 'password' => 'secret123', 'mfa_code' => $code]);
        $result->assertStatus(200);
        $tokens = json_decode((string) $result->getJSON(), true);
        $this->post('auth/jwt/refresh', ['user_id' => $tokens['user_id'], 'refresh_token' => $tokens['refresh_token']])->assertStatus(200);
    }

    public function testMfaFailuresAccumulateWithCorrectPasswordAndLockAccount(): void
    {
        $user = model(UserModel::class)->findByCredentials(['email' => 'jwt@example.com']);
        $this->assertInstanceOf(User::class, $user);
        $user->enableTotp();
        $user->confirmTotp();
        setting('AuthSecurity.mfaRequired', true);
        setting('AuthSecurity.userMaxAttempts', 2);
        $body = ['email' => 'jwt@example.com', 'password' => 'secret123', 'mfa_code' => 'wrong'];
        $this->post('auth/jwt/login', $body)->assertStatus(403);
        $this->post('auth/jwt/login', $body)->assertStatus(403);
        $this->post('auth/jwt/login', $body)->assertStatus(401);
        $this->assertSame(0, model(UserIdentityModel::class)->where('type', 'jwt_refresh')->countAllResults());
    }

    public function testPolicyChangeRefusesAnExistingPasswordOnlyRefreshFamily(): void
    {
        $tokens = $this->login();
        setting('AuthSecurity.mfaRequired', true);
        $this->post('auth/jwt/refresh', ['user_id' => $tokens['user_id'], 'refresh_token' => $tokens['refresh_token']])->assertStatus(403);
    }

    public function testBackupCodeGrantsMfaOnlyOnce(): void
    {
        $user = model(UserModel::class)->findByCredentials(['email' => 'jwt@example.com']);
        $this->assertInstanceOf(User::class, $user);
        $user->enableTotp();
        $user->confirmTotp();
        $backup = $user->generateBackupCodes()[0];
        setting('AuthSecurity.mfaRequired', true);
        $body = ['email' => 'jwt@example.com', 'password' => 'secret123', 'mfa_code' => $backup];
        $this->post('auth/jwt/login', $body)->assertStatus(200);
        $this->post('auth/jwt/login', $body)->assertStatus(403);
    }

    public function testMfaProofRequiresTheSameAuthenticatedSessionUser(): void
    {
        $user = model(UserModel::class)->findByCredentials(['email' => 'jwt@example.com']);
        setting('AuthSecurity.mfaRequired', true);
        // A stray proof without a live login is insufficient.
        $this->withSession(['auth_mfa_user' => (string) $user->id, 'auth_mfa_at' => time()])
            ->post('auth/jwt/login', ['email' => 'jwt@example.com', 'password' => 'secret123'])->assertStatus(403);
        // Neither can another account's proof be used for these credentials.
        setting('Auth.sessionConfig', array_replace(setting('Auth.sessionConfig'), ['trackDeviceSessions' => false]));
        $other = fake(UserModel::class);
        Services::resetSingle('auth');
        $this->withSession(['user' => ['id' => $other->id], 'auth_mfa_user' => (string) $other->id, 'auth_mfa_at' => time()])
            ->post('auth/jwt/login', ['email' => 'jwt@example.com', 'password' => 'secret123'])->assertStatus(403);
        Services::resetSingle('auth');
        $this->withSession(['user' => ['id' => $user->id], 'auth_mfa_user' => (string) $user->id, 'auth_mfa_at' => time()])
            ->post('auth/jwt/login', ['email' => 'jwt@example.com', 'password' => 'secret123'])->assertStatus(403);
        $this->assertInstanceOf(User::class, $user);
        $user->enableTotp();
        $user->confirmTotp();
        Services::resetSingle('auth');
        $this->withSession(['user' => ['id' => $user->id], 'auth_mfa_user' => (string) $user->id, 'auth_mfa_at' => time()])
            ->post('auth/jwt/login', ['email' => 'jwt@example.com', 'password' => 'secret123'])->assertStatus(200);
    }

    public function testJwtEndpointsAcceptJsonRequestBodies(): void
    {
        $result = $this->withHeaders(['Content-Type' => 'application/json'])->withBody(json_encode(['email' => 'jwt@example.com', 'password' => 'secret123']))->post('auth/jwt/login');
        $result->assertStatus(200);
        $tokens = json_decode((string) $result->getJSON(), true);
        $this->withHeaders(['Content-Type' => 'application/json'])->withBody(json_encode(['user_id' => $tokens['user_id'], 'refresh_token' => $tokens['refresh_token']]))->post('auth/jwt/refresh')->assertStatus(200);
    }
}
