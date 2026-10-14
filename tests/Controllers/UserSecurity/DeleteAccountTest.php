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

use CodeIgniter\Config\Factories;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Models\WebAuthnCredentialModel;
use Daycry\Auth\Services\AuditLogger;
use RuntimeException;
use Tests\Support\CapturingEmail;
use Tests\Support\SelfServiceTestCase;

/**
 * @internal
 */
final class DeleteAccountTest extends SelfServiceTestCase
{
    public function testPasswordAccountIsDeletedImmediatelyAfterReauth(): void
    {
        $user = $this->userWithPassword('leaving@example.com');

        $form = $this->signedInAs($user)->get('account/security/delete');
        $form->assertStatus(200);
        $form->assertSee('name="current_password"', null);

        $this->post('account/security/delete', ['current_password' => self::PASSWORD])->assertRedirect();

        $this->assertSame(lang('Auth.deleteAccountSuccess'), $this->flash('message'), (string) $this->flash('error'));
        $this->assertAnonymized($user);
        $this->assertFalse(auth()->loggedIn());

        // Farewell notice goes to the address the account had.
        $this->assertSame([lang('Auth.securityAccountDeletedSubject')], array_column(CapturingEmail::sentTo('leaving@example.com'), 'subject'));
    }

    public function testWrongPasswordDeletesNothing(): void
    {
        $user = $this->userWithPassword();

        $this->signedInAs($user)->post('account/security/delete', ['current_password' => 'nope'])->assertRedirect();

        $this->assertSame(lang('Auth.changePasswordCurrentInvalid'), $this->flash('error'));
        $this->assertSame($user->username, model(UserModel::class)->findById($user->id)->username);
        $this->assertTrue(auth()->loggedIn());
    }

    public function testPasswordlessAccountConfirmsByEmail(): void
    {
        $user = $this->oauthOnlyUser();
        model(UserIdentityModel::class)->createEmailIdentity($user, ['email' => 'social@example.com', 'password' => '']);
        $user = model(UserModel::class)->findById($user->id);

        $this->signedInAs($user)->post('account/security/delete')->assertRedirect();
        $this->assertSame(lang('Auth.deleteAccountEmailSent'), $this->flash('message'), (string) $this->flash('error'));
        $this->assertSame($user->username, model(UserModel::class)->findById($user->id)->username, 'Nothing is deleted before confirmation');

        $token = $this->tokenFrom(CapturingEmail::sentTo('social@example.com'));

        $page = $this->get('account/security/delete/confirm?token=' . $token);
        $page->assertStatus(200);
        $page->assertSee(lang('Auth.deleteAccountConfirmTitle'));

        $this->post('account/security/delete/confirm', ['token' => $token])->assertRedirect();
        $this->assertSame(lang('Auth.deleteAccountSuccess'), $this->flash('message'), (string) $this->flash('error'));
        $this->assertAnonymized($user);
    }

    public function testOauthAccountWithoutEmailIsAskedToAddOne(): void
    {
        $user = $this->oauthOnlyUser();

        $this->signedInAs($user)->post('account/security/delete')->assertRedirect();

        $this->assertSame(lang('Auth.deleteAccountNeedsEmail'), $this->flash('error'));
        $this->assertSame($user->username, model(UserModel::class)->findById($user->id)->username);
    }

    public function testAnotherAccountsTokenIsRefused(): void
    {
        $victim = $this->oauthOnlyUser();
        model(UserIdentityModel::class)->createEmailIdentity($victim, ['email' => 'victim@example.com', 'password' => '']);
        $this->signedInAs(model(UserModel::class)->findById($victim->id))->post('account/security/delete')->assertRedirect();
        $token = $this->tokenFrom(CapturingEmail::sentTo('victim@example.com'));

        $attacker = $this->userWithPassword('attacker@example.com');
        $this->signedInAs($attacker)->post('account/security/delete/confirm', ['token' => $token])->assertRedirect();

        $this->assertSame(lang('Auth.deleteAccountTokenInvalid'), $this->flash('error'));
        $this->assertSame($victim->username, model(UserModel::class)->findById($victim->id)->username);
        $this->assertSame($attacker->username, model(UserModel::class)->findById($attacker->id)->username);
    }

    public function testAFailedAnonymizationKeepsTheAccountAndSendsNoFarewell(): void
    {
        $user = $this->userWithPassword('stays@example.com');

        Factories::injectMock('models', WebAuthnCredentialModel::class, new class () extends WebAuthnCredentialModel {
            public function delete($id = null, bool $purge = false): void
            {
                throw new RuntimeException('disk full');
            }
        });

        try {
            $this->signedInAs($user)->post('account/security/delete', ['current_password' => self::PASSWORD])->assertRedirect();
        } finally {
            Factories::reset('models');
        }

        $this->assertSame(lang('Auth.deleteAccountFailed'), $this->flash('error'));
        $this->assertSame($user->username, model(UserModel::class)->findById($user->id)->username);
        $this->assertSame([], CapturingEmail::$sent);
        $this->assertTrue(auth()->loggedIn());
    }

    private function assertAnonymized(User $user): void
    {
        $this->seeInDatabase($this->tables['users'], ['id' => $user->id, 'username' => 'deleted_' . $user->id, 'active' => 0]);
        $this->dontSeeInDatabase($this->tables['identities'], ['user_id' => $user->id]);
        $this->seeInDatabase($this->tables['audit_logs'], [
            'user_id'    => $user->id,
            'event_type' => AuditLogger::EVENT_USER_ANONYMIZED,
            'metadata'   => json_encode(['initiator' => 'self-service']),
        ]);
    }

    /**
     * @param list<array{to: string, subject: string, body: string}> $messages
     */
    private function tokenFrom(array $messages): string
    {
        $this->assertNotSame([], $messages);
        $this->assertSame(1, preg_match('/token=([A-Za-z0-9%]+)/', end($messages)['body'], $m));

        return urldecode($m[1]);
    }
}
