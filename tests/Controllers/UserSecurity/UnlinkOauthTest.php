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

use Daycry\Auth\Entities\User;
use Daycry\Auth\Enums\IdentityType;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Models\WebAuthnCredentialModel;
use Daycry\Auth\Services\AuditLogger;
use Tests\Support\SelfServiceTestCase;

/**
 * @internal
 */
final class UnlinkOauthTest extends SelfServiceTestCase
{
    public function testUnlinksAProviderFromAPasswordAccount(): void
    {
        $user = $this->userWithPassword();
        $this->link($user, 'github');

        $this->signedInAs($user)->post('account/security/oauth/github/unlink')->assertRedirect();

        $this->assertSame(lang('Auth.unlinkOauthSuccess', ['github']), $this->flash('message'), (string) $this->flash('error'));
        $this->assertFalse($this->isLinked($user, 'github'));
        $this->seeInDatabase($this->tables['audit_logs'], ['user_id' => $user->id, 'event_type' => AuditLogger::EVENT_OAUTH_UNLINKED]);
    }

    public function testReportsAProviderThatIsNotLinked(): void
    {
        $this->signedInAs($this->userWithPassword())->post('account/security/oauth/github/unlink')->assertRedirect();

        $this->assertSame(lang('Auth.unlinkOauthNotFound', ['github']), $this->flash('error'));
    }

    public function testRefusesToRemoveTheLastSignInMethod(): void
    {
        $user = $this->oauthOnlyUser('github');

        $this->signedInAs($user)->post('account/security/oauth/github/unlink')->assertRedirect();

        $this->assertSame(lang('Auth.unlinkOauthLastMethod'), $this->flash('error'));
        $this->assertTrue($this->isLinked($user, 'github'));
    }

    public function testAnEmailIdentityWithoutAUsablePasswordIsNotASignInMethod(): void
    {
        $user = $this->oauthOnlyUser('github');
        model(UserIdentityModel::class)->createEmailIdentity($user, ['email' => 'social@example.com', 'password' => '']);

        $this->signedInAs($this->fresh($user))->post('account/security/oauth/github/unlink')->assertRedirect();

        $this->assertSame(lang('Auth.unlinkOauthLastMethod'), $this->flash('error'));
        $this->assertTrue($this->isLinked($user, 'github'));
    }

    public function testAnotherProviderOrAPasskeyKeepsTheAccountReachable(): void
    {
        $twoProviders = $this->oauthOnlyUser('github');
        $this->link($twoProviders, 'google');

        $this->signedInAs($twoProviders)->post('account/security/oauth/github/unlink')->assertRedirect();
        $this->assertFalse($this->isLinked($twoProviders, 'github'), (string) $this->flash('error'));

        $withPasskey = $this->oauthOnlyUser('github');
        model(WebAuthnCredentialModel::class)->insert([
            'user_id'       => $withPasskey->id,
            'credential_id' => 'cred-' . bin2hex(random_bytes(4)),
            'credential'    => '{}',
            'user_handle'   => 'handle',
            'name'          => 'Laptop',
            'sign_count'    => 0,
        ]);

        $this->signedInAs($withPasskey)->post('account/security/oauth/github/unlink')->assertRedirect();
        $this->assertFalse($this->isLinked($withPasskey, 'github'), (string) $this->flash('error'));
    }

    private function link(User $user, string $provider): void
    {
        model(UserIdentityModel::class)->insert([
            'user_id' => $user->id,
            'type'    => IdentityType::oauthProvider($provider),
            'secret'  => $provider . '-' . bin2hex(random_bytes(4)),
            'name'    => 'Social',
        ]);
    }

    private function isLinked(User $user, string $provider): bool
    {
        return model(UserIdentityModel::class)->getIdentityByType($user, IdentityType::oauthProvider($provider)) !== null;
    }

    private function fresh(User $user): User
    {
        return model(UserModel::class)->findById($user->id);
    }
}
