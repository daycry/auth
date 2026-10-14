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

use CodeIgniter\I18n\Time;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Enums\IdentityType;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Services\AuditLogger;
use Tests\Support\CapturingEmail;
use Tests\Support\SelfServiceTestCase;

/**
 * @internal
 */
final class ChangeEmailTest extends SelfServiceTestCase
{
    public function testRequestSendsTheLinkToTheNewAddressAndWarnsTheCurrentOne(): void
    {
        $user = $this->userWithPassword('member@example.com');

        $this->signedInAs($user)->post('account/security/email', [
            'new_email'        => 'New@Example.com',
            'current_password' => self::PASSWORD,
        ])->assertRedirect();

        $this->assertSame(lang('Auth.changeEmailSent'), $this->flash('message'), $this->errorHint());

        $toNew = CapturingEmail::sentTo('new@example.com');
        $this->assertCount(1, $toNew);
        $this->assertSame(lang('Auth.changeEmailSubject'), $toNew[0]['subject']);
        $this->assertStringContainsString('email/confirm?token=', $toNew[0]['body']);

        $this->assertSame([lang('Auth.securityEmailChangeRequestedSubject')], array_column(CapturingEmail::sentTo('member@example.com'), 'subject'));
        $this->seeInDatabase($this->tables['audit_logs'], ['user_id' => $user->id, 'event_type' => AuditLogger::EVENT_EMAIL_CHANGE_REQUEST]);

        // Nothing changes until the new address is confirmed.
        $this->assertSame('member@example.com', model(UserModel::class)->findById($user->id)->email);
    }

    public function testFullFlowChangesTheLoginEmail(): void
    {
        $user  = $this->userWithPassword('member@example.com');
        $token = $this->requestChange($user, 'new@example.com');

        // GET only renders the confirmation page (safe against link scanners).
        $page = $this->get('account/security/email/confirm?token=' . $token);
        $page->assertStatus(200);
        $page->assertSee('new@example.com');
        $this->assertSame('member@example.com', model(UserModel::class)->findById($user->id)->email);

        $this->post('account/security/email/confirm', ['token' => $token])->assertRedirect();
        $this->assertSame(lang('Auth.changeEmailSuccess'), $this->flash('message'), $this->errorHint());

        // The login identity itself moved (entity save, not a raw update).
        $fresh = model(UserModel::class)->findById($user->id);
        $this->assertSame('new@example.com', $fresh->email);
        $this->assertSame((int) $user->id, (int) model(UserModel::class)->findByCredentials(['email' => 'new@example.com'])->id);
        $this->assertNull(model(UserModel::class)->findByCredentials(['email' => 'member@example.com']));

        // The previous address is told the change happened.
        $this->assertContains(lang('Auth.securityEmailChangedSubject'), array_column(CapturingEmail::sentTo('member@example.com'), 'subject'));
        $this->seeInDatabase($this->tables['audit_logs'], ['user_id' => $user->id, 'event_type' => AuditLogger::EVENT_EMAIL_CHANGED]);

        // Single use.
        $this->post('account/security/email/confirm', ['token' => $token])->assertRedirect();
        $this->assertSame(lang('Auth.changeEmailTokenInvalid'), $this->flash('error'));
    }

    public function testRejectsAWrongCurrentPassword(): void
    {
        $user = $this->userWithPassword();

        $this->signedInAs($user)->post('account/security/email', [
            'new_email'        => 'new@example.com',
            'current_password' => 'nope',
        ])->assertRedirect();

        $this->assertSame(lang('Auth.changePasswordCurrentInvalid'), $this->flash('error'));
        $this->assertSame([], CapturingEmail::$sent);
    }

    public function testRejectsTheCurrentAddressAnInvalidOneAndOneInUse(): void
    {
        $this->userWithPassword('taken@example.com');
        $user = $this->userWithPassword('member@example.com');
        $this->signedInAs($user);

        foreach ([
            'MEMBER@example.com' => 'Auth.changeEmailUnchanged',
            'not-an-email'       => 'Auth.changeEmailInvalid',
            'Taken@Example.com'  => 'Auth.changeEmailAlreadyUsed',
        ] as $address => $key) {
            $this->post('account/security/email', ['new_email' => $address, 'current_password' => self::PASSWORD])->assertRedirect();
            $this->assertSame(lang($key), $this->flash('error'), $address);
        }

        $this->assertSame([], CapturingEmail::$sent);
    }

    public function testConfirmRefusesAnAddressTakenInTheMeantime(): void
    {
        $user  = $this->userWithPassword('member@example.com');
        $token = $this->requestChange($user, 'new@example.com');

        $this->userWithPassword('new@example.com');

        $this->post('account/security/email/confirm', ['token' => $token])->assertRedirect();
        $this->assertSame(lang('Auth.changeEmailAlreadyUsed'), $this->flash('error'));
        $this->assertSame('member@example.com', model(UserModel::class)->findById($user->id)->email);
    }

    public function testExpiredAndForeignTokensAreRefused(): void
    {
        $user  = $this->userWithPassword('member@example.com');
        $token = $this->requestChange($user, 'new@example.com');

        model(UserIdentityModel::class)->where('user_id', $user->id)->where('type', IdentityType::EMAIL_CHANGE->value)
            ->set('expires', Time::now()->subHours(2)->format('Y-m-d H:i:s'))->update();

        $this->get('account/security/email/confirm?token=' . $token)->assertRedirect();
        $this->assertSame(lang('Auth.changeEmailTokenExpired'), $this->flash('error'));

        // A token belonging to another account is useless in this session.
        $other      = $this->userWithPassword('other@example.com');
        $otherToken = $this->requestChange($other, 'other-new@example.com');
        $this->signedInAs($user);

        $this->post('account/security/email/confirm', ['token' => $otherToken])->assertRedirect();
        $this->assertSame(lang('Auth.changeEmailTokenInvalid'), $this->flash('error'));
        $this->assertSame('other@example.com', model(UserModel::class)->findById($other->id)->email);
    }

    public function testPasswordlessAccountNeedsNoPasswordButARecentSignIn(): void
    {
        $user = $this->oauthOnlyUser();
        $this->signedInAs($user);
        $this->ageSignIn(4 * HOUR);

        $this->post('account/security/email', ['new_email' => 'social@example.com'])->assertRedirect();
        $this->assertSame(lang('Auth.reauthRequired'), $this->flash('error'));

        $this->signedInAs($user);
        $this->post('account/security/email', ['new_email' => 'social@example.com'])->assertRedirect();
        $this->assertSame(lang('Auth.changeEmailSent'), $this->flash('message'), $this->errorHint());

        $token = $this->tokenFromLastEmail('social@example.com');
        $this->post('account/security/email/confirm', ['token' => $token])->assertRedirect();

        $fresh = model(UserModel::class)->findById($user->id);
        $this->assertSame('social@example.com', $fresh->email);
    }

    /**
     * Requests an e-mail change as $user and returns the emailed raw token.
     */
    private function requestChange(User $user, string $newEmail): string
    {
        $this->signedInAs($user)->post('account/security/email', [
            'new_email'        => $newEmail,
            'current_password' => self::PASSWORD,
        ])->assertRedirect();

        return $this->tokenFromLastEmail($newEmail);
    }

    private function tokenFromLastEmail(string $to): string
    {
        $messages = CapturingEmail::sentTo($to);
        $this->assertNotSame([], $messages, 'No e-mail sent to ' . $to);
        $this->assertSame(1, preg_match('/token=([A-Za-z0-9%]+)/', end($messages)['body'], $m));

        return urldecode($m[1]);
    }
}
