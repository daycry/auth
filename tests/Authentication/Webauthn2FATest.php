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

namespace Tests\Authentication;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\Test\Mock\MockEvents;
use Config\Services;
use Daycry\Auth\Auth as AuthService;
use Daycry\Auth\Authentication\Actions\Webauthn2FA;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Models\WebAuthnCredentialModel;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeUser;

/**
 * @internal
 */
final class Webauthn2FATest extends DatabaseTestCase
{
    use FakeUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFakeUser();

        $events = new MockEvents();
        Services::injectMock('events', $events);

        // Register library routes so the failure-path view (which uses
        // url_to()/site_url()) renders inside verify().
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

    public function testCreateIdentitySkippedWithoutCredentials(): void
    {
        $action = new Webauthn2FA();

        $this->assertSame('', $action->createIdentity($this->user));
    }

    public function testCreateIdentityActivatesWithCredentials(): void
    {
        $this->registerPasskey();

        $action = new Webauthn2FA();
        $this->assertSame('webauthn', $action->createIdentity($this->user));

        $this->seeInDatabase($this->tables['identities'], [
            'user_id' => $this->user->id,
            'type'    => 'webauthn',
        ]);
    }

    /**
     * Invariant #11: the WebAuthn second factor is brute-force throttled by the
     * shared UserLockoutManager. Repeated FAILED passkey assertions must lock
     * the account once AuthSecurity.userMaxAttempts is reached, exactly like the
     * password and TOTP factors.
     */
    public function testRepeatedFailedAssertionsLockTheAccount(): void
    {
        setting('AuthSecurity.userMaxAttempts', 3);
        setting('AuthSecurity.userLockoutTime', 3600);

        $this->registerPasskey();
        $this->setUpPendingSession();

        // Each failed assertion increments the per-user failure counter. Each
        // iteration uses a fresh authenticator instance (resetSingle) to mirror
        // separate HTTP requests, so the lockout state is re-read from the DB.
        for ($i = 1; $i <= 3; $i++) {
            Services::resetSingle('auth');
            (new Webauthn2FA())->verify($this->postCredential('not-a-valid-assertion'));

            $this->seeInDatabase($this->tables['users'], [
                'id'                 => $this->user->id,
                'failed_login_count' => $i,
            ]);

            // Login must never complete on a bad assertion: the pending marker stays.
            $this->seeInDatabase($this->tables['identities'], [
                'user_id' => $this->user->id,
                'type'    => 'webauthn',
            ]);
        }

        // Reaching the threshold locks the account.
        $row = model(UserModel::class)->find($this->user->id);
        $this->assertNotNull($row->locked_until, 'Account must be locked after userMaxAttempts failures');

        // A subsequent attempt (fresh request) is short-circuited by the lockout
        // check — no further failures are recorded.
        Services::resetSingle('auth');
        (new Webauthn2FA())->verify($this->postCredential('still-bad'));
        $this->seeInDatabase($this->tables['users'], [
            'id'                 => $this->user->id,
            'failed_login_count' => 3,
        ]);
    }

    public function testEmptyCredentialRecordsFailedAttempt(): void
    {
        setting('AuthSecurity.userMaxAttempts', 5);

        $this->registerPasskey();
        $this->setUpPendingSession();

        $action = new Webauthn2FA();
        $action->verify($this->postCredential(''));

        // An empty credential is a failed attempt (counted), not a silent skip.
        $this->seeInDatabase($this->tables['users'], [
            'id'                 => $this->user->id,
            'failed_login_count' => 1,
        ]);

        // Login must NOT complete — the pending marker remains.
        $this->seeInDatabase($this->tables['identities'], [
            'user_id' => $this->user->id,
            'type'    => 'webauthn',
        ]);
    }

    /**
     * Inserts an (opaque) active passkey row so hasWebAuthnCredentials() is true.
     */
    private function registerPasskey(): void
    {
        model(WebAuthnCredentialModel::class)->insert([
            'user_id'       => $this->user->id,
            'credential_id' => 'c-1',
            'credential'    => '{"x":1}',
            'sign_count'    => 0,
        ]);
    }

    /**
     * Puts the session into the WebAuthn-pending state and inserts the pending
     * login marker, mirroring how the login Action pipeline sets things up.
     */
    private function setUpPendingSession(): void
    {
        model(UserIdentityModel::class)->insert([
            'user_id' => $this->user->id,
            'type'    => 'webauthn',
            'name'    => 'webauthn_pending',
            'secret'  => 'webauthn',
            'extra'   => lang('Auth.needWebauthn'),
        ]);

        $_SESSION['user'] = [
            'id'                  => $this->user->id,
            'auth_action'         => Webauthn2FA::class,
            'auth_action_message' => lang('Auth.needWebauthn'),
        ];
    }

    private function postCredential(string $credential): IncomingRequest
    {
        /** @var IncomingRequest $request */
        $request = service('request');
        $request->setGlobal('post', ['credential' => $credential]);

        return $request;
    }
}
