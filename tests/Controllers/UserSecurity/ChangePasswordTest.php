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

use Daycry\Auth\Authentication\Passwords;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Models\RememberModel;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Services\AuditLogger;
use Tests\Support\CapturingEmail;
use Tests\Support\SelfServiceTestCase;

/**
 * @internal
 */
final class ChangePasswordTest extends SelfServiceTestCase
{
    private const NEW_PASSWORD = 'Brand-New-Secret-42';

    public function testFormAsksForTheCurrentPasswordOnAPasswordAccount(): void
    {
        $result = $this->signedInAs($this->userWithPassword())->get('account/security/password');

        $result->assertStatus(200);
        $result->assertSee('name="current_password"', null);
        $result->assertSee(lang('Auth.changePasswordTitle'));
    }

    public function testChangesThePasswordAndRevokesOtherSessions(): void
    {
        $user = $this->userWithPassword();
        model(RememberModel::class)->rememberUser($user, 'sel', hash('sha256', 'val'), date('Y-m-d H:i:s', time() + 3600));
        $tokenVersion = (int) $user->token_version;

        $result = $this->signedInAs($user)->post('account/security/password', [
            'current_password'     => self::PASSWORD,
            'new_password'         => self::NEW_PASSWORD,
            'new_password_confirm' => self::NEW_PASSWORD,
        ]);

        $result->assertRedirect();
        $this->assertSame(lang('Auth.changePasswordSuccess'), $this->flash('message'), (string) $this->flash('error'));

        $fresh = model(UserModel::class)->findById($user->id);
        $this->assertTrue($this->passwords()->verify(self::NEW_PASSWORD, $fresh->getPasswordHash()));
        $this->assertSame($tokenVersion + 1, (int) $fresh->token_version);
        $this->dontSeeInDatabase($this->tables['remember_tokens'], ['user_id' => $user->id]);
        $this->seeInDatabase($this->tables['audit_logs'], ['user_id' => $user->id, 'event_type' => AuditLogger::EVENT_PASSWORD_CHANGED]);
        $this->assertSame([lang('Auth.securityPasswordChangedSubject')], array_column(CapturingEmail::sentTo('member@example.com'), 'subject'));

        // The current session survives the change.
        $this->assertTrue(auth()->loggedIn());
    }

    public function testRejectsAWrongCurrentPassword(): void
    {
        $user = $this->userWithPassword();

        $this->signedInAs($user)->post('account/security/password', [
            'current_password'     => 'not-my-password',
            'new_password'         => self::NEW_PASSWORD,
            'new_password_confirm' => self::NEW_PASSWORD,
        ])->assertRedirect();

        $this->assertSame(lang('Auth.changePasswordCurrentInvalid'), $this->flash('error'));
        $this->assertUnchanged($user);
    }

    public function testRejectsAMismatchedConfirmation(): void
    {
        $user = $this->userWithPassword();

        $this->signedInAs($user)->post('account/security/password', [
            'current_password'     => self::PASSWORD,
            'new_password'         => self::NEW_PASSWORD,
            'new_password_confirm' => self::NEW_PASSWORD . 'x',
        ])->assertRedirect();

        $this->assertSame(lang('Auth.changePasswordMismatch'), $this->flash('error'));
        $this->assertUnchanged($user);
    }

    public function testRejectsAPasswordTheValidatorsRefuse(): void
    {
        $user = $this->userWithPassword();

        $this->signedInAs($user)->post('account/security/password', [
            'current_password'     => self::PASSWORD,
            'new_password'         => 'short',
            'new_password_confirm' => 'short',
        ])->assertRedirect();

        $this->assertNotNull($this->flash('error'));
        $this->assertUnchanged($user);
    }

    public function testNoNoticeWhenNotificationsAreOff(): void
    {
        $this->injectMockAttributesSecurity(['securityNotificationEmails' => false]);

        $this->signedInAs($this->userWithPassword())->post('account/security/password', [
            'current_password'     => self::PASSWORD,
            'new_password'         => self::NEW_PASSWORD,
            'new_password_confirm' => self::NEW_PASSWORD,
        ])->assertRedirect();

        $this->assertSame(lang('Auth.changePasswordSuccess'), $this->flash('message'), (string) $this->flash('error'));
        $this->assertSame([], CapturingEmail::$sent);
    }

    public function testPasswordlessAccountWithEmailSetsAPasswordWithoutTheCurrentOne(): void
    {
        $user = $this->passwordlessUserWithEmail();

        $form = $this->signedInAs($user)->get('account/security/password');
        $form->assertSee(lang('Auth.setPasswordTitle'));
        $form->assertDontSee('name="current_password"');

        $this->post('account/security/password', [
            'new_password'         => self::NEW_PASSWORD,
            'new_password_confirm' => self::NEW_PASSWORD,
        ])->assertRedirect();

        $this->assertSame(lang('Auth.setPasswordSuccess'), $this->flash('message'), (string) $this->flash('error'));
        $fresh = model(UserModel::class)->findById($user->id);
        $this->assertTrue($this->passwords()->verify(self::NEW_PASSWORD, $fresh->getPasswordHash()));
    }

    public function testPasswordlessAccountNeedsARecentSignIn(): void
    {
        // A stolen long-lived session must not be able to set a password and
        // keep the account.
        $user = $this->passwordlessUserWithEmail();
        $this->signedInAs($user);
        $this->ageSignIn(4 * HOUR);

        $this->post('account/security/password', [
            'new_password'         => self::NEW_PASSWORD,
            'new_password_confirm' => self::NEW_PASSWORD,
        ])->assertRedirect();

        $this->assertSame(lang('Auth.reauthRequired'), $this->flash('error'));
        $this->assertFalse($this->passwords()->verify(self::NEW_PASSWORD, (string) model(UserModel::class)->findById($user->id)->getPasswordHash()));
    }

    public function testOauthOnlyAccountWithoutEmailCannotSetAPassword(): void
    {
        $user = $this->oauthOnlyUser();

        $this->signedInAs($user)->post('account/security/password', [
            'new_password'         => self::NEW_PASSWORD,
            'new_password_confirm' => self::NEW_PASSWORD,
        ])->assertRedirect();

        $this->assertSame(lang('Auth.changePasswordNeedsEmail'), $this->flash('error'));
        $this->assertNull(model(UserIdentityModel::class)->getIdentityByType($user, 'email_password'));
    }

    /**
     * An account whose e-mail identity carries no usable password — what an
     * OAuth-created account gets once it confirms an e-mail address.
     */
    private function passwordlessUserWithEmail(): User
    {
        $user = $this->oauthOnlyUser();
        model(UserIdentityModel::class)->createEmailIdentity($user, ['email' => 'social@example.com', 'password' => '']);

        return model(UserModel::class)->findById($user->id);
    }

    private function assertUnchanged(User $user): void
    {
        $fresh = model(UserModel::class)->findById($user->id);
        $this->assertTrue($this->passwords()->verify(self::PASSWORD, $fresh->getPasswordHash()));
        $this->assertSame([], CapturingEmail::$sent);
    }

    private function passwords(): Passwords
    {
        return service('passwords');
    }
}
