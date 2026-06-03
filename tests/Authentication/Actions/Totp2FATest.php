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

namespace Tests\Authentication\Actions;

use CodeIgniter\Config\Factories;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\Mock\MockEvents;
use Config\Services;
use Daycry\Auth\Auth as AuthService;
use Daycry\Auth\Authentication\Actions\Totp2FA;
use Daycry\Auth\Authentication\Authentication;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Config\Auth;
use Daycry\Auth\Enums\IdentityType;
use Daycry\Auth\Enums\TotpState;
use Daycry\Auth\Libraries\TOTP;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeUser;

/**
 * @internal
 */
final class Totp2FATest extends DatabaseTestCase
{
    use FakeUser;

    private Session $authenticator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFakeUser();

        $config = new Auth();
        $auth   = new Authentication($config);
        $auth->setProvider(model(UserModel::class));

        /** @var Session $authenticator */
        $authenticator       = $auth->factory('session');
        $this->authenticator = $authenticator;

        $events = new MockEvents();
        Services::injectMock('events', $events);

        // Register the library routes so views using url_to('auth-action-*') render.
        $routes = Services::routes();
        (new AuthService(config('Auth')))->routes($routes);
        Services::injectMock('routes', $routes);

        $this->user->email = 'foo@example.com';
        model(UserModel::class)->save($this->user);

        $this->db->table($this->tables['identities'])->truncate();

        model(UserIdentityModel::class)->createEmailIdentity($this->user, [
            'email'    => 'foo@example.com',
            'password' => 'secret123',
        ]);
    }

    // -----------------------------------------------------------------------
    // Two-phase enrollment: enableTotp() / hasTotpPending() / confirmTotp()
    // -----------------------------------------------------------------------

    public function testEnableTotpCreatesPendingSecret(): void
    {
        $this->user->enableTotp('TestApp');

        $this->seeInDatabase($this->tables['identities'], [
            'user_id' => $this->user->id,
            'type'    => IdentityType::TOTP_SECRET->value,
            'name'    => TotpState::PENDING->value,
        ]);
    }

    public function testHasTotpEnabledFalseForPendingSecret(): void
    {
        $this->user->enableTotp('TestApp');

        $this->assertFalse($this->user->hasTotpEnabled(), 'Pending secret must NOT be considered enabled');
    }

    public function testHasTotpPendingTrueAfterEnable(): void
    {
        $this->user->enableTotp('TestApp');

        $this->assertTrue($this->user->hasTotpPending(), 'hasTotpPending() must return true after enableTotp()');
    }

    public function testHasTotpPendingFalseWhenNoSecret(): void
    {
        $this->assertFalse($this->user->hasTotpPending());
    }

    public function testConfirmTotpActivatesSecret(): void
    {
        $this->user->enableTotp('TestApp');
        $this->assertFalse($this->user->hasTotpEnabled());

        $this->user->confirmTotp();

        $this->assertTrue($this->user->hasTotpEnabled(), 'hasTotpEnabled() must return true after confirmTotp()');
        $this->assertFalse($this->user->hasTotpPending(), 'hasTotpPending() must return false after confirmTotp()');

        $this->seeInDatabase($this->tables['identities'], [
            'user_id' => $this->user->id,
            'type'    => IdentityType::TOTP_SECRET->value,
            'name'    => TotpState::CONFIRMED->value,
        ]);
    }

    public function testConfirmTotpNoopWhenNoSecret(): void
    {
        // Should not throw when there is nothing to confirm
        $this->user->confirmTotp();
        $this->assertFalse($this->user->hasTotpEnabled());
    }

    public function testEnableTotpReplacesExistingPendingSecret(): void
    {
        $this->user->enableTotp('TestApp');
        $secretFirst = $this->user->getTotpSecret();

        $this->user->enableTotp('TestApp');
        $secretSecond = $this->user->getTotpSecret();

        // Only one totp_secret identity should exist
        $count = $this->db->table($this->tables['identities'])
            ->where('user_id', $this->user->id)
            ->where('type', IdentityType::TOTP_SECRET->value)
            ->countAllResults();

        $this->assertSame(1, $count);
        $this->assertNotSame($secretFirst, $secretSecond);
    }

    // -----------------------------------------------------------------------
    // Unit: Totp2FA::createIdentity()
    // -----------------------------------------------------------------------

    public function testCreateIdentityReturnsEmptyForUserWithoutTotp(): void
    {
        $action = new Totp2FA();
        $result = $action->createIdentity($this->user);

        $this->assertSame('', $result, 'No TOTP at all → must skip action');
    }

    public function testCreateIdentityReturnsEmptyForUserWithPendingTotp(): void
    {
        // Pending setup (not yet confirmed) must NOT trigger the login action
        $this->user->enableTotp('TestApp');

        $action = new Totp2FA();
        $result = $action->createIdentity($this->user);

        $this->assertSame('', $result, 'Pending (unconfirmed) TOTP must not trigger the action');
    }

    public function testCreateIdentityReturnsTotpTypeForUserWithConfirmedTotp(): void
    {
        $this->user->enableTotp('TestApp');
        $this->user->confirmTotp();

        $action = new Totp2FA();
        $result = $action->createIdentity($this->user);

        $this->assertSame('totp', $result, 'Confirmed TOTP must activate the login action');
    }

    public function testCreateIdentityInsertsLoginMarkerForConfirmedTotp(): void
    {
        $this->user->enableTotp('TestApp');
        $this->user->confirmTotp();

        $action = new Totp2FA();
        $action->createIdentity($this->user);

        $this->seeInDatabase($this->tables['identities'], [
            'user_id' => $this->user->id,
            'type'    => IdentityType::TOTP->value,
            'name'    => 'totp_pending',
        ]);
    }

    public function testCreateIdentityDoesNotInsertMarkerForUserWithoutTotp(): void
    {
        $action = new Totp2FA();
        $action->createIdentity($this->user);

        $this->dontSeeInDatabase($this->tables['identities'], [
            'user_id' => $this->user->id,
            'type'    => IdentityType::TOTP->value,
        ]);
    }

    public function testCreateIdentityDeletesStaleLoginMarker(): void
    {
        // Stale login marker with no confirmed TOTP secret
        model(UserIdentityModel::class)->insert([
            'user_id' => $this->user->id,
            'type'    => IdentityType::TOTP->value,
            'name'    => 'totp_pending',
            'secret'  => 'totp',
        ]);

        $action = new Totp2FA();
        $action->createIdentity($this->user);

        $this->dontSeeInDatabase($this->tables['identities'], [
            'user_id' => $this->user->id,
            'type'    => IdentityType::TOTP->value,
        ]);
    }

    // -----------------------------------------------------------------------
    // Login flow: user WITHOUT confirmed TOTP
    // -----------------------------------------------------------------------

    public function testLoginFlowWithoutTotpCompletesLogin(): void
    {
        /** @var Auth $config */
        $config          = config('Auth');
        $config->actions = ['login' => Totp2FA::class, 'register' => null];
        Factories::injectMock('config', 'Auth', $config);

        $result = $this->authenticator->attempt([
            'email'    => $this->user->email,
            'password' => 'secret123',
        ]);

        $this->assertTrue($result->isOK());
        $this->assertTrue($this->authenticator->loggedIn(), 'No TOTP → must complete login');
        $this->assertFalse($this->authenticator->isPending());
    }

    public function testLoginFlowWithPendingTotpCompletesLogin(): void
    {
        // User started setup but never confirmed — must not be blocked at login
        $this->user->enableTotp('TestApp');

        /** @var Auth $config */
        $config          = config('Auth');
        $config->actions = ['login' => Totp2FA::class, 'register' => null];
        Factories::injectMock('config', 'Auth', $config);

        $result = $this->authenticator->attempt([
            'email'    => $this->user->email,
            'password' => 'secret123',
        ]);

        $this->assertTrue($result->isOK());
        $this->assertTrue($this->authenticator->loggedIn(), 'Unconfirmed TOTP must not block login');
        $this->assertFalse($this->authenticator->isPending());
    }

    public function testLoginFlowWithStaleMarkerCompletesLogin(): void
    {
        /** @var Auth $config */
        $config          = config('Auth');
        $config->actions = ['login' => Totp2FA::class, 'register' => null];
        Factories::injectMock('config', 'Auth', $config);

        model(UserIdentityModel::class)->insert([
            'user_id' => $this->user->id,
            'type'    => IdentityType::TOTP->value,
            'name'    => 'totp_pending',
            'secret'  => 'totp',
            'extra'   => 'You need to enter your TOTP code.',
        ]);

        $result = $this->authenticator->attempt([
            'email'    => $this->user->email,
            'password' => 'secret123',
        ]);

        $this->assertTrue($result->isOK());
        $this->assertTrue($this->authenticator->loggedIn(), 'Stale login marker must be cleaned up');
        $this->assertFalse($this->authenticator->isPending());

        $this->dontSeeInDatabase($this->tables['identities'], [
            'user_id' => $this->user->id,
            'type'    => IdentityType::TOTP->value,
        ]);
    }

    // -----------------------------------------------------------------------
    // Login flow: user WITH confirmed TOTP
    // -----------------------------------------------------------------------

    public function testLoginFlowWithConfirmedTotpCreatesPendingState(): void
    {
        $this->user->enableTotp('TestApp');
        $this->user->confirmTotp();

        /** @var Auth $config */
        $config          = config('Auth');
        $config->actions = ['login' => Totp2FA::class, 'register' => null];
        Factories::injectMock('config', 'Auth', $config);

        $result = $this->authenticator->attempt([
            'email'    => $this->user->email,
            'password' => 'secret123',
        ]);

        $this->assertTrue($result->isOK());
        $this->assertTrue($this->authenticator->isPending(), 'Confirmed TOTP must put user in pending state');
        $this->assertFalse($this->authenticator->loggedIn());
    }

    // -----------------------------------------------------------------------
    // completeLogin() session cleanup
    // -----------------------------------------------------------------------

    public function testCompleteTotpLoginFlowFromAttempt(): void
    {
        $this->user->enableTotp('TestApp');
        $this->user->confirmTotp();

        /** @var Auth $config */
        $config          = config('Auth');
        $config->actions = ['login' => Totp2FA::class, 'register' => null];
        Factories::injectMock('config', 'Auth', $config);

        $result = $this->authenticator->attempt([
            'email'    => $this->user->email,
            'password' => 'secret123',
        ]);

        $this->assertTrue($result->isOK());
        $this->assertTrue($this->authenticator->isPending());

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identityModel->deleteIdentitiesByType($this->user, IdentityType::TOTP->value);

        $this->authenticator->completeLogin($this->user);

        // Simulate next request with a fresh authenticator instance
        $freshConfig = new Auth();
        $freshAuth   = new Authentication($freshConfig);
        $freshAuth->setProvider(model(UserModel::class));

        /** @var Session $fresh */
        $fresh = $freshAuth->factory('session');

        $this->assertTrue($fresh->loggedIn(), 'Fresh instance must see user as logged in');
        $this->assertFalse($fresh->isPending());
    }

    public function testCompleteLoginClearsAuthActionFromSession(): void
    {
        $_SESSION['user'] = [
            'id'                  => $this->user->id,
            'auth_action'         => Totp2FA::class,
            'auth_action_message' => 'Enter your TOTP code.',
        ];

        $this->authenticator->completeLogin($this->user);

        $sessionData = $_SESSION['user'];
        $this->assertArrayNotHasKey('auth_action', $sessionData);
        $this->assertArrayNotHasKey('auth_action_message', $sessionData);
    }

    public function testGetTypeReturnsTotp(): void
    {
        $action = new Totp2FA();
        $this->assertSame('totp', $action->getType());
    }

    // -----------------------------------------------------------------------
    // verify(): code checking, brute-force throttling, backup codes
    // -----------------------------------------------------------------------

    /**
     * Enrols + confirms TOTP for the user, inserts the pending login marker
     * and puts the session into the TOTP-pending state, then returns the raw
     * base32 secret so tests can generate valid codes.
     */
    private function setUpConfirmedTotpPendingSession(): string
    {
        $this->user->enableTotp('TestApp');
        $this->user->confirmTotp();

        model(UserIdentityModel::class)->insert([
            'user_id' => $this->user->id,
            'type'    => IdentityType::TOTP->value,
            'name'    => 'totp_pending',
            'secret'  => 'totp',
        ]);

        $_SESSION['user'] = [
            'id'                  => $this->user->id,
            'auth_action'         => Totp2FA::class,
            'auth_action_message' => lang('Auth.needTotp'),
        ];

        return (string) $this->user->getTotpSecret();
    }

    private function postToken(string $token): IncomingRequest
    {
        /** @var IncomingRequest $request */
        $request = service('request');
        $request->setGlobal('post', ['token' => $token]);

        return $request;
    }

    public function testVerifyWithWrongCodeRecordsFailedAttempt(): void
    {
        $this->setUpConfirmedTotpPendingSession();

        $action = new Totp2FA();
        $action->verify($this->postToken('000000'));

        // The failed second-factor attempt must be counted (brute-force throttle).
        $this->seeInDatabase($this->tables['users'], [
            'id'                 => $this->user->id,
            'failed_login_count' => 1,
        ]);

        // Login must NOT complete on a wrong code — the pending marker remains.
        $this->seeInDatabase($this->tables['identities'], [
            'user_id' => $this->user->id,
            'type'    => IdentityType::TOTP->value,
        ]);
    }

    public function testVerifyDoesNotCompleteLoginWhenAccountIsLockedOut(): void
    {
        $secret = $this->setUpConfirmedTotpPendingSession();

        // Simulate an account already locked by repeated failures.
        model(UserModel::class)->update($this->user->id, [
            'failed_login_count' => 5,
            'locked_until'       => Time::now()->addMinutes(30)->format('Y-m-d H:i:s'),
        ]);

        $action = new Totp2FA();
        $action->verify($this->postToken(TOTP::getCode($secret)));

        // Even a valid code must not log the user in while locked out.
        $this->seeInDatabase($this->tables['identities'], [
            'user_id' => $this->user->id,
            'type'    => IdentityType::TOTP->value,
        ]);

        // Lockout short-circuits before verification, so no extra failure is recorded.
        $this->seeInDatabase($this->tables['users'], [
            'id'                 => $this->user->id,
            'failed_login_count' => 5,
        ]);
    }

    public function testTotpCodeCannotBeReplayed(): void
    {
        $this->user->enableTotp('TestApp');
        $this->user->confirmTotp();

        $code = TOTP::getCode((string) $this->user->getTotpSecret());

        // First use is accepted...
        $this->assertTrue($this->user->verifyTotpCode($code));
        // ...the same code (same time-step) must be rejected on reuse.
        $this->assertFalse($this->user->verifyTotpCode($code), 'A consumed TOTP code must not be accepted again');
    }

    public function testVerifyWithCorrectCodeCompletesLoginAndResetsCounter(): void
    {
        $secret = $this->setUpConfirmedTotpPendingSession();

        // A couple of prior failed attempts that must be cleared on success.
        model(UserModel::class)->update($this->user->id, ['failed_login_count' => 2]);

        $action = new Totp2FA();
        $action->verify($this->postToken(TOTP::getCode($secret)));

        // Pending marker removed → login completed.
        $this->dontSeeInDatabase($this->tables['identities'], [
            'user_id' => $this->user->id,
            'type'    => IdentityType::TOTP->value,
        ]);

        // Failure counter reset on a successful second factor.
        $this->seeInDatabase($this->tables['users'], [
            'id'                 => $this->user->id,
            'failed_login_count' => 0,
        ]);
    }
}
