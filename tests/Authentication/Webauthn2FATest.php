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

use Daycry\Auth\Authentication\Actions\Webauthn2FA;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Models\WebAuthnCredentialModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class Webauthn2FATest extends DatabaseTestCase
{
    public function testCreateIdentitySkippedWithoutCredentials(): void
    {
        $user   = fake(UserModel::class);
        $action = new Webauthn2FA();

        $this->assertSame('', $action->createIdentity($user));
    }

    public function testCreateIdentityActivatesWithCredentials(): void
    {
        $user = fake(UserModel::class);
        model(WebAuthnCredentialModel::class)->insert([
            'user_id'       => $user->id,
            'credential_id' => 'c-1',
            'credential'    => '{"x":1}',
            'sign_count'    => 0,
        ]);

        $action = new Webauthn2FA();
        $this->assertSame('webauthn', $action->createIdentity($user));

        $this->seeInDatabase(config('Auth')->tables['identities'], [
            'user_id' => $user->id,
            'type'    => 'webauthn',
        ]);
    }
}
