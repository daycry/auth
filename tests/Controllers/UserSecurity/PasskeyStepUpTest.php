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

namespace Tests\Controllers\UserSecurity;

use Config\Services;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Services\MfaPolicy;
use Tests\Support\SelfServiceTestCase;
use Tests\Support\WebAuthn\SuppressesWebauthnDeprecations;
use Tests\Support\WebAuthn\VirtualAuthenticator;

/**
 * @internal
 */
final class PasskeyStepUpTest extends SelfServiceTestCase
{
    use SuppressesWebauthnDeprecations;

    protected function setUp(): void
    {
        parent::setUp();
        setting('AuthSecurity.webauthnEnabled', true);
        setting('AuthSecurity.webauthnRelyingPartyId', 'example.com');
        setting('AuthSecurity.webauthnAllowedOrigins', ['https://example.com']);
        setting('AuthSecurity.mfaRequired', false);
        $routes = service('routes');
        $c      = '\\Daycry\\Auth\\Controllers\\UserSecurityController::';
        $routes->post('auth/confirm-password/options', $c . 'passwordConfirmOptions', ['as' => 'password-confirm-options', 'filter' => 'auth:session']);
        $routes->post('auth/confirm-password', $c . 'confirmPasswordAction', ['as' => 'password-confirm', 'filter' => 'auth:session']);
        $routes->get('auth/confirm-password', $c . 'confirmPasswordView', ['as' => 'password-confirm-show', 'filter' => 'auth:session']);
        Services::injectMock('routes', $routes);
        $this->suppressWebauthnDeprecations();
    }

    protected function tearDown(): void
    {
        $this->restoreWebauthnDeprecations();
        parent::tearDown();
    }

    public function testRealPasskeyAssertionConfirmsCurrentUserAndCannotBeReplayed(): void
    {
        $user          = $this->userWithPassword();
        $authenticator = $this->enroll($user);
        $this->signedInAs($user);
        $optionsResponse = $this->post('auth/confirm-password/options');
        $optionsResponse->assertStatus(200);
        $options = json_decode($optionsResponse->getJSON(), true);
        $this->assertSame('required', $options['userVerification']);
        $assertion = $authenticator->login(json_encode($options), (string) $user->uuid);
        $this->withSession();
        $this->post('auth/confirm-password', ['factor' => 'passkey', 'credential' => $assertion])->assertStatus(302);
        $this->assertTrue((new MfaPolicy())->verified($user));
        $this->assertSame((string) $user->id, session('auth_step_up_user'));
        session()->remove('password_confirmed_at');
        $this->withSession();
        $this->post('auth/confirm-password', ['factor' => 'passkey', 'credential' => $assertion]);
        $this->assertNull(session('password_confirmed_at'));
    }

    public function testAnotherUsersChallengeCannotConfirmCurrentUser(): void
    {
        $first  = $this->userWithPassword('first@example.com');
        $second = $this->userWithPassword('second@example.com');
        $this->enroll($first);
        $otherAuthenticator = $this->enroll($second);
        $this->signedInAs($first);
        $options   = service('webAuthnManager')->startStepUp($second);
        $assertion = $otherAuthenticator->login(json_encode($options), (string) $second->uuid);
        $this->withSession();
        $this->post('auth/confirm-password', ['factor' => 'passkey', 'credential' => $assertion]);

        $this->assertNull(session('password_confirmed_at'));
        $this->assertFalse((new MfaPolicy())->verified($first));
    }

    private function enroll(User $user): VirtualAuthenticator
    {
        $authenticator = new VirtualAuthenticator('example.com', 'https://example.com');
        $manager       = service('webAuthnManager');
        $options       = $manager->startRegistration($user, 'Test key');
        $manager->finishRegistration($user, $authenticator->register(json_encode($options)));

        return $authenticator;
    }
}
