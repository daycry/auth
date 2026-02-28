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
use Daycry\Auth\Authentication\Actions\Email2FA;
use Daycry\Auth\Authentication\Authentication;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Config\Auth;
use Daycry\Auth\Entities\UserIdentity;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeUser;

/**
 * @internal
 */
final class Email2FATest extends DatabaseTestCase
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

    public function testGetTypeReturnsEmail2FAType(): void
    {
        $action = new Email2FA();

        $this->assertSame(Session::ID_TYPE_EMAIL_2FA, $action->getType());
    }

    public function testCreateIdentityGeneratesSixDigitCode(): void
    {
        $action = new Email2FA();
        $code   = $action->createIdentity($this->user);

        $this->assertMatchesRegularExpression('/^[1-9]{6}$/', $code);
    }

    public function testCreateIdentityStoresIdentityInDatabase(): void
    {
        $action = new Email2FA();
        $action->createIdentity($this->user);

        $this->seeInDatabase($this->tables['identities'], [
            'user_id' => $this->user->id,
            'type'    => Session::ID_TYPE_EMAIL_2FA,
            'name'    => 'login',
        ]);
    }

    public function testCreateIdentityDeletesPreviousIdentity(): void
    {
        $action = new Email2FA();

        // Create first identity
        $code1 = $action->createIdentity($this->user);

        // Create second identity (should replace first)
        $code2 = $action->createIdentity($this->user);

        // Should only have one identity of this type
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identity      = $identityModel->getIdentityByType($this->user, Session::ID_TYPE_EMAIL_2FA);

        $this->assertInstanceOf(UserIdentity::class, $identity);
        $this->assertSame($code2, $identity->secret);
    }

    public function testCreateIdentityGeneratesUniqueCodes(): void
    {
        $action = new Email2FA();

        $codes = [];

        for ($i = 0; $i < 10; $i++) {
            $codes[] = $action->createIdentity($this->user);
        }

        // At least some should be different (statistically near-certain)
        $unique = array_unique($codes);
        $this->assertGreaterThan(1, count($unique));
    }

    public function testVerifyWithCorrectTokenSucceeds(): void
    {
        // Enable Email2FA action
        /** @var Auth $config */
        $config          = config('Auth');
        $config->actions = ['login' => Email2FA::class, 'register' => null];
        Factories::injectMock('config', 'Auth', $config);

        // Create the 2FA identity
        $action = new Email2FA();
        $code   = $action->createIdentity($this->user);

        // Set up pending state: user id + auth_action in session
        $_SESSION['user'] = [
            'id'                  => $this->user->id,
            'auth_action'         => Email2FA::class,
            'auth_action_message' => lang('Auth.need2FA'),
        ];

        // Verify the authenticator recognizes the pending state
        $this->assertTrue($this->authenticator->isPending());
        $pendingUser = $this->authenticator->getPendingUser();
        $this->assertNotNull($pendingUser);

        // Get the identity
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identity      = $identityModel->getIdentityByType($this->user, Session::ID_TYPE_EMAIL_2FA);

        $this->assertInstanceOf(UserIdentity::class, $identity);

        // Check action with correct token
        $result = $this->authenticator->checkAction($identity, $code);

        $this->assertTrue($result);

        // Identity should be deleted after successful verification
        $deletedIdentity = $identityModel->getIdentityByType($this->user, Session::ID_TYPE_EMAIL_2FA);
        $this->assertNull($deletedIdentity);
    }

    public function testVerifyWithWrongTokenFails(): void
    {
        // Enable Email2FA action
        /** @var Auth $config */
        $config          = config('Auth');
        $config->actions = ['login' => Email2FA::class, 'register' => null];
        Factories::injectMock('config', 'Auth', $config);

        // Create the 2FA identity
        $action = new Email2FA();
        $action->createIdentity($this->user);

        // Set up pending state
        $_SESSION['user'] = [
            'id'                  => $this->user->id,
            'auth_action'         => Email2FA::class,
            'auth_action_message' => lang('Auth.need2FA'),
        ];

        // Get the identity
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identity      = $identityModel->getIdentityByType($this->user, Session::ID_TYPE_EMAIL_2FA);

        $this->assertInstanceOf(UserIdentity::class, $identity);

        // Check action with wrong token
        $result = $this->authenticator->checkAction($identity, '000000');

        $this->assertFalse($result);

        // Identity should still exist after failed verification
        $stillExists = $identityModel->getIdentityByType($this->user, Session::ID_TYPE_EMAIL_2FA);
        $this->assertInstanceOf(UserIdentity::class, $stillExists);
    }

    public function testVerifyWithEmptyTokenFails(): void
    {
        // Enable Email2FA action
        /** @var Auth $config */
        $config          = config('Auth');
        $config->actions = ['login' => Email2FA::class, 'register' => null];
        Factories::injectMock('config', 'Auth', $config);

        // Create the 2FA identity
        $action = new Email2FA();
        $action->createIdentity($this->user);

        // Set up pending state
        $_SESSION['user'] = [
            'id'                  => $this->user->id,
            'auth_action'         => Email2FA::class,
            'auth_action_message' => lang('Auth.need2FA'),
        ];

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identity      = $identityModel->getIdentityByType($this->user, Session::ID_TYPE_EMAIL_2FA);

        $this->assertInstanceOf(UserIdentity::class, $identity);

        $result = $this->authenticator->checkAction($identity, '');

        $this->assertFalse($result);
    }

    public function testLoginFlowWithEmail2FACreatesPendingState(): void
    {
        // Enable Email2FA action
        /** @var Auth $config */
        $config          = config('Auth');
        $config->actions = ['login' => Email2FA::class, 'register' => null];
        Factories::injectMock('config', 'Auth', $config);

        // Attempt login which should trigger the 2FA action
        $result = $this->authenticator->attempt([
            'email'    => $this->user->email,
            'password' => 'secret123',
        ]);

        $this->assertTrue($result->isOK());

        // User should be in pending state
        $this->assertTrue($this->authenticator->isPending());
        $this->assertFalse($this->authenticator->loggedIn());

        // A 2FA identity should exist
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identity      = $identityModel->getIdentityByType($this->user, Session::ID_TYPE_EMAIL_2FA);

        $this->assertInstanceOf(UserIdentity::class, $identity);
        $this->assertMatchesRegularExpression('/^[1-9]{6}$/', $identity->secret);
    }

    public function testFullLoginFlowWithEmail2FA(): void
    {
        // Enable Email2FA action
        /** @var Auth $config */
        $config          = config('Auth');
        $config->actions = ['login' => Email2FA::class, 'register' => null];
        Factories::injectMock('config', 'Auth', $config);

        // Step 1: Attempt login
        $result = $this->authenticator->attempt([
            'email'    => $this->user->email,
            'password' => 'secret123',
        ]);

        $this->assertTrue($result->isOK());
        $this->assertTrue($this->authenticator->isPending());

        // Step 2: Get the 2FA code from the identity
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identity      = $identityModel->getIdentityByType($this->user, Session::ID_TYPE_EMAIL_2FA);

        $this->assertInstanceOf(UserIdentity::class, $identity);
        $code = $identity->secret;

        // Step 3: Verify the code through the authenticator
        $verified = $this->authenticator->checkAction($identity, $code);

        $this->assertTrue($verified);

        // Step 4: User should now be fully logged in
        $this->assertTrue($this->authenticator->loggedIn());
        $this->assertFalse($this->authenticator->isPending());
    }
}
