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
use Daycry\Auth\Authentication\Actions\Email2FA;
use Daycry\Auth\Authentication\Actions\EmailActivator;
use Daycry\Auth\Authentication\Actions\Totp2FA;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Libraries\TOTP;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class JsonAuthFormsTest extends DatabaseTestCase
{
    use FeatureTestTrait;

    protected $namespace = 'Daycry\Auth';

    protected function setUp(): void
    {
        parent::setUp();
        $routes     = service('routes');
        $controller = '\\Daycry\\Auth\\Controllers\\';
        $routes->post('login', $controller . 'LoginController::loginAction');
        $routes->post('register', $controller . 'RegisterController::registerAction');
        $routes->post('password-reset', $controller . 'PasswordResetController::requestAction');
        $routes->post('login/magic-link/code', $controller . 'MagicLinkController::verifyCode', ['as' => 'magic-link-code']);
        $routes->get('a/show', $controller . 'ActionController::show', ['as' => 'auth-action-show']);
        $routes->post('a/verify', $controller . 'ActionController::verify', ['as' => 'auth-action-verify']);
        $routes->get('account/security', $controller . 'UserSecurityController::index', ['as' => 'security']);
        $routes->get('auth/confirm-password', $controller . 'UserSecurityController::confirmPasswordView', ['as' => 'password-confirm-show']);
        Services::injectMock('routes', $routes);
    }

    private function user(): User
    {
        $user = fake(UserModel::class);
        $this->db->table($this->tables['identities'])->truncate();
        model(UserIdentityModel::class)->createEmailIdentity($user, ['email' => 'json@example.com', 'password' => 'Brand-New-Pass-123']);

        return model(UserModel::class)->findById($user->id);
    }

    public function testLoginValidationReturnsJson422(): void
    {
        $result = $this->withHeaders(['Accept' => 'application/json'])->post('login', []);
        $result->assertStatus(422);
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('email', $json['errors']);
        $this->assertSame('error', $json['status']);
    }

    public function testInvalidCredentialsReturnJson401(): void
    {
        $this->user();
        $result = $this->withHeaders(['Accept' => 'application/json'])->post('login', ['email' => 'json@example.com', 'password' => 'wrong']);
        $result->assertStatus(401);
        $this->assertSame('error', json_decode($result->getJSON(), true)['status']);
    }

    public function testJsonBodyLogsInWithoutARedirectResponse(): void
    {
        $this->user();
        $result = $this->withBodyFormat('json')->post('login', ['email' => 'json@example.com', 'password' => 'Brand-New-Pass-123']);
        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertTrue($json['authenticated']);
        $this->assertSame('success', $json['status']);
        $this->assertArrayNotHasKey('password_hash', $json);
        $this->assertArrayHasKey('hash', $json['token']);
    }

    public function testPendingMfaDoesNotClaimAuthentication(): void
    {
        $user = $this->user();
        $user->enableTotp();
        $user->confirmTotp();
        $this->injectMockAttributes(['actions' => ['login' => Totp2FA::class, 'register' => null]]);
        $result = $this->withBodyFormat('json')->post('login', ['email' => 'json@example.com', 'password' => 'Brand-New-Pass-123']);
        $result->assertStatus(202);
        $json = json_decode($result->getJSON(), true);
        $this->assertSame('action_required', $json['status']);
        $this->assertFalse($json['authenticated']);
    }

    public function testMalformedJsonIsRejectedBeforeAnyAccountIsCreated(): void
    {
        $result = $this->withHeaders(['Content-Type' => 'application/json', 'Accept' => 'application/json'])->withBody('{"email":')->post('register');
        $result->assertStatus(400);
        $this->assertSame('error', json_decode($result->getJSON(), true)['status']);
    }

    public function testJsonArrayBodyIsRejectedAsMalformedRequest(): void
    {
        $result = $this->withHeaders(['Content-Type' => 'application/json', 'Accept' => 'application/json'])->withBody('["email"]')->post('register');
        $result->assertStatus(400);
    }

    public function testEmptyJsonBodyUsesNormalValidation(): void
    {
        $result = $this->withHeaders(['Content-Type' => 'application/json', 'Accept' => 'application/json'])->withBody('')->post('login');
        $result->assertStatus(422);
        $this->assertArrayHasKey('email', json_decode($result->getJSON(), true)['errors']);
    }

    public function testRegistrationReturnsJson201(): void
    {
        setting('Auth.allowRegistration', true);
        $result = $this->withBodyFormat('json')->post('register', ['email' => 'new-json@example.com', 'username' => 'newjsonuser', 'password' => 'fJ8v!Q2r#L9x@T6z', 'password_confirm' => 'fJ8v!Q2r#L9x@T6z']);
        $this->assertSame(201, $result->response()->getStatusCode(), $result->getJSON());
        $this->assertSame('success', json_decode($result->getJSON(), true)['status']);
        $this->seeInDatabase($this->tables['identities'], ['secret' => 'new-json@example.com']);
    }

    public function testUnknownMagicCodeReturnsJson401(): void
    {
        $result = $this->withSession(['magicCodeEmail' => 'unknown@example.com'])->withBodyFormat('json')->post('login/magic-link/code', ['token' => '123456']);
        $result->assertStatus(401);
        $this->assertSame('error', json_decode($result->getJSON(), true)['status']);
    }

    public function testJsonFactorVerificationCompletesThePendingLogin(): void
    {
        $user = $this->user();
        $user->enableTotp();
        $user->confirmTotp();
        $this->injectMockAttributes(['actions' => ['login' => Totp2FA::class, 'register' => null]]);
        $this->withBodyFormat('json')->post('login', ['email' => 'json@example.com', 'password' => 'Brand-New-Pass-123']);

        $state = session()->get();
        Services::resetSingle('auth');
        $result = $this->withSession($state)->withBodyFormat('json')->post('a/verify', ['token' => TOTP::getCode($user->getTotpSecret())]);
        $result->assertStatus(200);
        $this->assertTrue(json_decode($result->getJSON(), true)['authenticated']);
    }

    public function testInvalidJsonFactorReturns422WithoutCompletingLogin(): void
    {
        $user = $this->user();
        $user->enableTotp();
        $user->confirmTotp();
        $this->injectMockAttributes(['actions' => ['login' => Totp2FA::class, 'register' => null]]);
        $this->withBodyFormat('json')->post('login', ['email' => 'json@example.com', 'password' => 'Brand-New-Pass-123']);

        $state = session()->get();
        Services::resetSingle('auth');
        $result = $this->withSession($state)->withBodyFormat('json')->post('a/verify', ['token' => 'invalid']);
        $result->assertStatus(422);
        $this->assertFalse(json_decode($result->getJSON(), true)['authenticated']);
    }

    public function testMandatoryMfaEnrollmentDoesNotClaimAuthentication(): void
    {
        $this->user();
        setting('AuthSecurity.mfaRequired', true);
        setting('AuthSecurity.mfaGracePeriod', 0);
        $result = $this->withBodyFormat('json')->post('login', ['email' => 'json@example.com', 'password' => 'Brand-New-Pass-123']);
        $result->assertStatus(202);
        $json = json_decode($result->getJSON(), true);
        $this->assertFalse($json['authenticated']);
        $this->assertSame('mfa_enrollment_required', $json['code']);
    }

    public function testMandatoryMfaWithoutActionRequiresSecondFactor(): void
    {
        $user = $this->user();
        $user->enableTotp();
        $user->confirmTotp();
        setting('AuthSecurity.mfaRequired', true);
        $result = $this->withBodyFormat('json')->post('login', ['email' => 'json@example.com', 'password' => 'Brand-New-Pass-123']);
        $result->assertStatus(202);
        $json = json_decode($result->getJSON(), true);
        $this->assertFalse($json['authenticated']);
        $this->assertSame('mfa_verification_required', $json['code']);
    }

    public function testEmailActivationStillRequiresMandatoryMfaEnrollment(): void
    {
        setting('Auth.allowRegistration', true);
        setting('AuthSecurity.mfaRequired', true);
        setting('AuthSecurity.mfaGracePeriod', 0);
        $this->injectMockAttributes(['actions' => ['login' => null, 'register' => EmailActivator::class]]);
        $this->withBodyFormat('json')->post('register', ['email' => 'activation@example.com', 'username' => 'activationuser', 'password' => 'fJ8v!Q2r#L9x@T6z', 'password_confirm' => 'fJ8v!Q2r#L9x@T6z']);

        $user = model(UserModel::class)->findByCredentials(['email' => 'activation@example.com']);
        $this->assertInstanceOf(User::class, $user);
        $identity = model(UserIdentityModel::class)->getIdentityByType($user, Session::ID_TYPE_EMAIL_ACTIVATE);
        $state    = session()->get();
        Services::resetSingle('auth');
        $result = $this->withSession($state)->withBodyFormat('json')->post('a/verify', ['token' => $identity->secret]);

        $result->assertStatus(202);
        $json = json_decode($result->getJSON(), true);
        $this->assertFalse($json['authenticated']);
        $this->assertSame('mfa_enrollment_required', $json['code']);
        $this->assertSame(url_to('security'), $json['redirect']);
    }

    public function testEmailSecondFactorDoesNotReplaceRequiredStrongFactor(): void
    {
        $user = $this->user();
        $user->enableTotp();
        $user->confirmTotp();
        setting('AuthSecurity.mfaRequired', true);
        $this->injectMockAttributes(['actions' => ['login' => Email2FA::class, 'register' => null]]);
        $this->withBodyFormat('json')->post('login', ['email' => 'json@example.com', 'password' => 'Brand-New-Pass-123']);

        $identity = model(UserIdentityModel::class)->getIdentityByType($user, Session::ID_TYPE_EMAIL_2FA);
        $state    = session()->get();
        Services::resetSingle('auth');
        $result = $this->withSession($state)->withBodyFormat('json')->post('a/verify', ['token' => $identity->secret]);

        $result->assertStatus(202);
        $json = json_decode($result->getJSON(), true);
        $this->assertFalse($json['authenticated']);
        $this->assertSame('mfa_verification_required', $json['code']);
        $this->assertSame(url_to('password-confirm-show'), $json['redirect']);
    }
}
