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
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Libraries\TOTP;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Services\MfaPolicy;
use Tests\Support\SelfServiceTestCase;

/**
 * @internal
 */
final class MfaSecurityTest extends SelfServiceTestCase
{
    public function testMandatoryMfaWithoutLoginActionCanUseARecoveryCodeOnce(): void
    {
        setting('AuthSecurity.mfaRequired', true);
        $user = $this->userWithPassword();
        $user->enableTotp();
        $user->confirmTotp();
        $codes = $user->generateBackupCodes(2);
        $this->signedInAs($user);
        $result = $this->withBodyFormat('json')->post('auth/confirm-password', ['factor' => 'recovery', 'token' => $codes[0]]);
        $result->assertStatus(200);
        $this->assertTrue(service('mfaPolicy')->verified($user));
        $this->assertSame(1, $user->backupCodesRemaining());
        $this->withSession();
        $this->withBodyFormat('json')->post('auth/confirm-password', ['factor' => 'recovery', 'token' => $codes[0]])->assertStatus(422);
        $this->assertSame(1, $user->backupCodesRemaining());
    }

    protected function setUp(): void
    {
        parent::setUp();
        setting('AuthSecurity.mfaRequired', false);
        setting('AuthSecurity.mfaRequiredGroups', []);
        setting('AuthSecurity.mfaExemptGroups', []);
        setting('AuthSecurity.mfaGracePeriod', 0);
        setting('AuthSecurity.mfaEnforcementStartedAt', 0);
        Services::injectMock('mfaPolicy', new MfaPolicy());
        $routes = service('routes');
        $c      = '\\Daycry\\Auth\\Controllers\\UserSecurityController::';
        $routes->get('auth/confirm-password', $c . 'confirmPasswordView', ['as' => 'password-confirm-show', 'filter' => 'auth:session']);
        $routes->post('auth/confirm-password', $c . 'confirmPasswordAction', ['as' => 'password-confirm', 'filter' => 'auth:session']);
        $routes->post('auth/confirm-password/options', $c . 'passwordConfirmOptions', ['as' => 'password-confirm-options', 'filter' => 'auth:session']);
        $routes->get('account/security/recovery-codes', $c . 'recoveryCodesView', ['as' => 'security-recovery-codes', 'filter' => 'auth:session']);
        $routes->post('account/security/recovery-codes', $c . 'regenerateRecoveryCodes', ['as' => 'security-regenerate-recovery-codes', 'filter' => 'auth:session']);
        $routes->get('mfa-protected', static fn (): string => 'Sensitive application', ['filter' => 'auth:session']);
        Services::injectMock('routes', $routes);
    }

    public function testMandatoryEnrollmentBlocksApplicationButAllowsSetup(): void
    {
        setting('AuthSecurity.mfaRequired', true);
        $this->signedInAs($this->userWithPassword());
        $this->get('mfa-protected')->assertRedirectTo(site_url('account/security'));
        $this->withSession();
        $this->get('account/security/totp/setup')->assertStatus(200);
    }

    public function testEnrolledUserMustProveFactorAndPasswordCannotSatisfyMfa(): void
    {
        setting('AuthSecurity.mfaRequired', true);
        $user = $this->userWithPassword();
        $user->enableTotp();
        $user->confirmTotp();
        $this->signedInAs($user);
        $this->get('mfa-protected')->assertRedirectTo(site_url('auth/confirm-password'));
        $this->withSession();
        $this->post('auth/confirm-password', ['password' => self::PASSWORD]);
        $this->assertFalse((new MfaPolicy())->verified($user));
        $this->withSession();
        $this->get('mfa-protected')->assertStatus(302);
    }

    public function testTotpProofUnlocksProtectedApplicationAndIsStamped(): void
    {
        setting('AuthSecurity.mfaRequired', true);
        $user = $this->userWithPassword();
        $user->enableTotp();
        $user->confirmTotp();
        $code = TOTP::getCode($user->getTotpSecret());
        $this->signedInAs($user);
        $this->post('auth/confirm-password', ['factor' => 'totp', 'token' => $code])->assertStatus(302);
        $this->assertTrue((new MfaPolicy())->verified($user));
        $this->assertGreaterThan(0, (int) session('password_confirmed_at'));
        $this->withSession();
        $this->get('mfa-protected')->assertStatus(200);
    }

    public function testFailedStepUpUsesLockoutAndDoesNotStampProof(): void
    {
        setting('AuthSecurity.userMaxAttempts', 1);
        $user = $this->userWithPassword();
        $user->enableTotp();
        $user->confirmTotp();
        $this->signedInAs($user);
        $this->post('auth/confirm-password', ['factor' => 'totp', 'token' => 'bad-code']);
        $this->assertNull(session('password_confirmed_at'));
        $this->assertFalse((new MfaPolicy())->verified($user));
        $fresh = model(UserModel::class)->findById($user->id);
        $this->assertNotNull($fresh->locked_until);
    }

    public function testRecoveryRegenerationRequiresRecentUserBoundProof(): void
    {
        $user = $this->userWithPassword();
        $user->enableTotp();
        $user->confirmTotp();
        $old = $user->generateBackupCodes();
        $this->signedInAs($user);
        $this->post('account/security/recovery-codes')->assertStatus(302);
        $this->assertSame(10, $user->backupCodesRemaining());
        session()->set('password_confirmed_at', time());
        session()->set('auth_step_up_user', (string) $user->id);
        $this->withSession();
        $this->post('account/security/recovery-codes')->assertStatus(200);
        $this->assertFalse($user->consumeBackupCode($old[0]));
        $this->assertSame(10, $user->backupCodesRemaining());
    }

    public function testUnverifiedEnrolledUserCannotEnrollAnotherFactor(): void
    {
        setting('AuthSecurity.mfaRequired', true);
        $user = $this->userWithPassword();
        $user->enableTotp();
        $user->confirmTotp();
        $this->signedInAs($user);

        $this->get('account/security/totp/setup')->assertRedirectTo(site_url('auth/confirm-password'));
    }

    public function testMissingEnrollmentIsAllowedOnlyDuringFixedGrace(): void
    {
        setting('AuthSecurity.mfaRequired', true);
        setting('AuthSecurity.mfaGracePeriod', 600);
        setting('AuthSecurity.mfaEnforcementStartedAt', time());
        $this->signedInAs($this->userWithPassword());

        $this->get('mfa-protected')->assertStatus(200);
    }

    public function testMandatoryMfaRejectsJsonRequestsWithMachineReadableCode(): void
    {
        setting('AuthSecurity.mfaRequired', true);
        $this->signedInAs($this->userWithPassword());
        $result = $this->withHeaders(['Accept' => 'application/json'])->get('mfa-protected');
        $result->assertStatus(403);
        $this->assertSame('mfa_enrollment_required', json_decode($result->getJSON(), true)['code']);
    }

    public function testConfirmingFirstTotpEnrollmentSuppliesFactorProof(): void
    {
        setting('AuthSecurity.mfaRequired', true);
        $user = $this->userWithPassword();
        $this->signedInAs($user);
        $this->get('account/security/totp/setup')->assertStatus(200);
        $secret = auth()->user()->getTotpSecret();
        $this->withSession();
        $this->post('account/security/totp/setup/confirm', ['token' => TOTP::getCode($secret)])->assertStatus(200);
        $this->assertTrue((new MfaPolicy())->verified($user));
    }

    public function testJsonTotpConfirmationAcceptsJsonBodyAndReturnsConfirmed(): void
    {
        $user = $this->userWithPassword();
        $user->enableTotp();
        $user->confirmTotp();
        $this->signedInAs($user);
        $result = $this->withHeaders(['Content-Type' => 'application/json', 'Accept' => 'application/json'])
            ->withBody(json_encode(['factor' => 'totp', 'token' => TOTP::getCode($user->getTotpSecret())]))
            ->post('auth/confirm-password');
        $result->assertStatus(200);
        $this->assertTrue(json_decode($result->getJSON(), true)['confirmed']);
        $this->assertTrue((new MfaPolicy())->verified($user));
    }

    public function testRememberedSessionCannotEnrollFirstMandatoryFactorWithoutStepUp(): void
    {
        setting('AuthSecurity.mfaRequired', true);
        $this->signedInAs($this->userWithPassword());
        session()->remove(Session::AUTHENTICATED_AT_KEY);
        $this->withSession();

        $this->get('account/security/totp/setup')->assertRedirectTo(site_url('auth/confirm-password'));
    }

    public function testRequiredLastTotpFactorCannotBeDisabled(): void
    {
        setting('AuthSecurity.mfaRequired', true);
        $user = $this->userWithPassword();
        $user->enableTotp();
        $user->confirmTotp();
        $this->signedInAs($user);
        (new MfaPolicy())->markVerified($user);
        $this->withSession();
        $this->post('account/security/totp/disable', ['token' => TOTP::getCode($user->getTotpSecret())]);

        $this->assertTrue($user->hasTotpEnabled());
        $this->assertSame(lang('Auth.mfaLastFactorRequired'), session('error'));
    }

    public function testMandatoryMfaAlsoProtectsStandaloneGroupFilter(): void
    {
        setting('AuthSecurity.mfaRequired', true);
        $user = $this->userWithPassword();
        $user->addGroup('admin');
        $routes = service('routes');
        $routes->get('group-only', static fn (): string => 'Administration', ['filter' => 'group:admin']);
        Services::injectMock('routes', $routes);
        $this->signedInAs($user);

        $this->get('group-only')->assertRedirectTo(site_url('account/security'));
    }

    public function testMandatoryMfaAlsoProtectsChainSessionFilter(): void
    {
        setting('AuthSecurity.mfaRequired', true);
        $routes = service('routes');
        $routes->get('chain-only', static fn (): string => 'Application', ['filter' => 'chain']);
        Services::injectMock('routes', $routes);
        $this->signedInAs($this->userWithPassword());

        $this->get('chain-only')->assertRedirectTo(site_url('account/security'));
    }
}
