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

namespace Tests\Models;

use Daycry\Auth\Entities\WebAuthnCredential;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Models\WebAuthnCredentialModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class WebAuthnCredentialModelTest extends DatabaseTestCase
{
    private function seedCredential(int $userId, string $credentialId, ?string $revokedAt = null): WebAuthnCredential
    {
        $model = model(WebAuthnCredentialModel::class);
        $id    = $model->insert([
            'user_id'       => $userId,
            'credential_id' => $credentialId,
            'credential'    => '{"x":1}',
            'user_handle'   => 'handle-' . $userId,
            'sign_count'    => 0,
            'revoked_at'    => $revokedAt,
        ], true);

        return $model->find($id);
    }

    public function testInsertGeneratesUuidAndReturnsEntity(): void
    {
        $user = fake(UserModel::class);
        $row  = $this->seedCredential((int) $user->id, 'cred-aaa');

        $this->assertInstanceOf(WebAuthnCredential::class, $row);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            (string) $row->uuid,
        );
    }

    public function testFirstActiveByCredentialIdIgnoresRevoked(): void
    {
        $user  = fake(UserModel::class);
        $model = model(WebAuthnCredentialModel::class);

        $this->seedCredential((int) $user->id, 'cred-active');
        $this->seedCredential((int) $user->id, 'cred-revoked', '2020-01-01 00:00:00');

        $this->assertInstanceOf(WebAuthnCredential::class, $model->firstActiveByCredentialId('cred-active'));
        $this->assertNotInstanceOf(WebAuthnCredential::class, $model->firstActiveByCredentialId('cred-revoked'));
    }

    public function testActiveForUserAndCount(): void
    {
        $user  = fake(UserModel::class);
        $model = model(WebAuthnCredentialModel::class);

        $this->seedCredential((int) $user->id, 'c1');
        $this->seedCredential((int) $user->id, 'c2');
        $this->seedCredential((int) $user->id, 'c3', '2020-01-01 00:00:00');

        $this->assertCount(2, $model->activeForUser((int) $user->id));
        $this->assertSame(2, $model->countActiveForUser((int) $user->id));
    }
}
