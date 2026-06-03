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

namespace Tests\WebAuthn;

use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Models\WebAuthnCredentialModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class HasWebAuthnTest extends DatabaseTestCase
{
    public function testCredentialsAndRevoke(): void
    {
        $user = fake(UserModel::class);

        $this->assertFalse($user->hasWebAuthnCredentials());

        $id = model(WebAuthnCredentialModel::class)->insert([
            'user_id'       => $user->id,
            'credential_id' => 'cred-x',
            'credential'    => '{"x":1}',
            'name'          => 'Phone',
            'sign_count'    => 0,
        ], true);
        $row = model(WebAuthnCredentialModel::class)->find($id);

        $this->assertTrue($user->hasWebAuthnCredentials());
        $this->assertCount(1, $user->webAuthnCredentials());

        $this->assertTrue($user->revokeWebAuthnCredential((string) $row->uuid));
        $this->assertFalse($user->hasWebAuthnCredentials());
    }
}
