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
use CodeIgniter\Test\Mock\MockEvents;
use Config\Services;
use Daycry\Auth\Authentication\Actions\Totp2FA;
use Daycry\Auth\Authentication\Authentication;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Config\Auth;
use Daycry\Auth\Enums\IdentityType;
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

        // Set up the session authenticator
        $config = new Auth();
        $auth   = new Authentication($config);
        $auth->setProvider(model(UserModel::class));

        /** @var Session $authenticator */
        $authenticator       = $auth->factory('session');
        $this->authenticator = $authenticator;

        $events = new MockEvents();
        Services::injectMock('events', $events);

        // Set a known email on the user
        $this->user->email = 'foo@example.com';
        model(UserModel::class)->save($this->user);

        // Truncate identities to avoid unique constraint conflicts
        $this->db->table($this->tables['identities'])->truncate();

        // Create email identity for the user
        model(UserIdentityModel::class)->createEmailIdentity($this->user, [
            'email'    => 'foo@example.com',
            'password' => 'secret123',
        ]);
    }

    // -----------------------------------------------------------------------
    // Unit: createIdentity()
    // -----------------------------------------------------------------------

    public function testCreateIdentityReturnsEmptyForUserWithoutTotp(): void
    {
        $action = new Totp2FA();
        $result = $action->createIdentity($this->user);

        $this->assertSame('', $result, 'createIdentity() must return empty string when user has no TOTP configured');
    }

    public function testCreateIdentityReturnsTotpTypeForUserWithTotp(): void
    {
        // Configure TOTP for the user
        $this->user->enableTotp('TestApp');

        $action = new Totp2FA();
        $result = $action->createIdentity($this->user);

        $this->assertSame('totp', $result, 'createIdentity() must return "totp" when user has TOTP configured');
    }

    public function testCreateIdentityInsertsMarkerForUserWithTotp(): void
    {
        $this->user->enableTotp('TestApp');

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

    public function testCreateIdentityDeletesStaleMarker(): void
    {
        // Insert a stale marker manually
        model(UserIdentityModel::class)->insert([
            'user_id' => $this->user->id,
            'type'    => IdentityType::TOTP->value,
            'name'    => 'totp_pending',
            'secret'  => 'totp',
        ]);

        // User has no TOTP secret configured
        $action = new Totp2FA();
        $action->createIdentity($this->user);

        // Stale marker should be gone and no new one inserted
        $this->dontSeeInDatabase($this->tables['identities'], [
            'user_id' => $this->user->id,
            'type'    => IdentityType::TOTP->value,
        ]);
    }

    // -----------------------------------------------------------------------
    // Login flow: user WITHOUT TOTP, Totp2FA set as login action
    // -----------------------------------------------------------------------

    public function testLoginFlowWithoutTotpCompletesLogin(): void
    {
        // Enable Totp2FA action
        /** @var Auth $config */
        $config          = config('Auth');
        $config->actions = ['login' => Totp2FA::class, 'register' => null];
        Factories::injectMock('config', 'Auth', $config);

        // Attempt login — user has NO TOTP configured
        $result = $this->authenticator->attempt([
            'email'    => $this->user->email,
            'password' => 'secret123',
        ]);

        $this->assertTrue($result->isOK(), 'Attempt should succeed');

        // User should be FULLY logged in (not pending)
        $this->assertTrue($this->authenticator->loggedIn(), 'User should be logged in');
        $this->assertFalse($this->authenticator->isPending(), 'User should NOT be in pending state');
    }

    public function testLoginFlowWithoutTotpAndStaleMarkerCompletesLogin(): void
    {
        // Enable Totp2FA action
        /** @var Auth $config */
        $config          = config('Auth');
        $config->actions = ['login' => Totp2FA::class, 'register' => null];
        Factories::injectMock('config', 'Auth', $config);

        // Insert a stale totp pending marker (simulates abandoned previous login)
        model(UserIdentityModel::class)->insert([
            'user_id' => $this->user->id,
            'type'    => IdentityType::TOTP->value,
            'name'    => 'totp_pending',
            'secret'  => 'totp',
            'extra'   => 'You need to enter your TOTP code.',
        ]);

        // Attempt login — user has NO TOTP configured, but stale DB marker exists
        $result = $this->authenticator->attempt([
            'email'    => $this->user->email,
            'password' => 'secret123',
        ]);

        $this->assertTrue($result->isOK(), 'Attempt should succeed');

        // User should be FULLY logged in — stale marker must be cleaned up
        $this->assertTrue($this->authenticator->loggedIn(), 'User should be logged in despite stale marker');
        $this->assertFalse($this->authenticator->isPending(), 'User should NOT be pending');

        // Stale marker must be deleted from DB
        $this->dontSeeInDatabase($this->tables['identities'], [
            'user_id' => $this->user->id,
            'type'    => IdentityType::TOTP->value,
        ]);
    }

    // -----------------------------------------------------------------------
    // Login flow: user WITH TOTP, Totp2FA set as login action
    // -----------------------------------------------------------------------

    public function testLoginFlowWithTotpCreatesPendingState(): void
    {
        // Configure TOTP for the user
        $this->user->enableTotp('TestApp');

        // Enable Totp2FA action
        /** @var Auth $config */
        $config          = config('Auth');
        $config->actions = ['login' => Totp2FA::class, 'register' => null];
        Factories::injectMock('config', 'Auth', $config);

        $result = $this->authenticator->attempt([
            'email'    => $this->user->email,
            'password' => 'secret123',
        ]);

        $this->assertTrue($result->isOK(), 'Attempt should succeed');

        // User should be in PENDING state (TOTP code required)
        $this->assertTrue($this->authenticator->isPending(), 'User should be in pending state');
        $this->assertFalse($this->authenticator->loggedIn(), 'User should NOT be logged in yet');
    }

    // -----------------------------------------------------------------------
    // Verify flow
    // -----------------------------------------------------------------------

    public function testCompleteTotpLoginFlowFromAttempt(): void
    {
        // Configure TOTP for the user
        $this->user->enableTotp('TestApp');
        $secret = $this->user->getTotpSecret();
        $this->assertNotNull($secret);

        // Enable Totp2FA action
        /** @var Auth $config */
        $config          = config('Auth');
        $config->actions = ['login' => Totp2FA::class, 'register' => null];
        Factories::injectMock('config', 'Auth', $config);

        // Step 1: attempt login → should be pending
        $result = $this->authenticator->attempt([
            'email'    => $this->user->email,
            'password' => 'secret123',
        ]);

        $this->assertTrue($result->isOK());
        $this->assertTrue($this->authenticator->isPending());

        // Step 2: simulate successful TOTP verification by replicating verify() logic
        // (delete pending marker, remove session action keys, completeLogin)
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identityModel->deleteIdentitiesByType($this->user, IdentityType::TOTP->value);

        // completeLogin() must clear auth_action from session and mark user as logged in
        $this->authenticator->completeLogin($this->user);

        // Step 3: verify state on a fresh authenticator instance (simulates next request)
        $freshConfig = new Auth();
        $freshAuth   = new Authentication($freshConfig);
        $freshAuth->setProvider(model(UserModel::class));

        /** @var Session $fresh */
        $fresh = $freshAuth->factory('session');

        $this->assertTrue($fresh->loggedIn(), 'Fresh instance should see user as logged in after TOTP verify');
        $this->assertFalse($fresh->isPending(), 'Fresh instance should not see pending state');
    }

    public function testCompleteLoginClearsAuthActionFromSession(): void
    {
        // Set up pending session state manually
        $_SESSION['user'] = [
            'id'                  => $this->user->id,
            'auth_action'         => Totp2FA::class,
            'auth_action_message' => 'Enter your TOTP code.',
        ];

        // completeLogin() should clear auth_action so the next request sees LOGGED_IN
        $this->authenticator->completeLogin($this->user);

        // Session field should no longer contain auth_action
        $sessionData = $_SESSION['user'];
        $this->assertArrayNotHasKey('auth_action', $sessionData, 'auth_action must be removed from session by completeLogin()');
        $this->assertArrayNotHasKey('auth_action_message', $sessionData, 'auth_action_message must be removed from session by completeLogin()');
    }

    public function testGetTypeReturnsTotp(): void
    {
        $action = new Totp2FA();
        $this->assertSame('totp', $action->getType());
    }
}
