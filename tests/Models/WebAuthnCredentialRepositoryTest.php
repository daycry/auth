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

use Cose\Algorithms;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;
use Tests\Support\WebAuthn\SuppressesWebauthnDeprecations;
use Tests\Support\WebAuthn\VirtualAuthenticator;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * @internal
 */
final class WebAuthnCredentialRepositoryTest extends DatabaseTestCase
{
    use SuppressesWebauthnDeprecations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->suppressWebauthnDeprecations();
    }

    protected function tearDown(): void
    {
        $this->restoreWebauthnDeprecations();
        parent::tearDown();
    }

    private function makeRecord(string $rpId, string $userHandle): CredentialRecord
    {
        setting('AuthSecurity.webauthnAllowedOrigins', ['https://example.com']);
        $serializer = service('webAuthnSerializer');

        $options = PublicKeyCredentialCreationOptions::create(
            PublicKeyCredentialRpEntity::create('Test', $rpId),
            PublicKeyCredentialUserEntity::create('joe', $userHandle, 'Joe'),
            random_bytes(32),
            [PublicKeyCredentialParameters::create('public-key', Algorithms::COSE_ALGORITHM_ES256)],
        );

        $authn      = new VirtualAuthenticator($rpId, 'https://example.com');
        $json       = $authn->register($serializer->serialize($options, 'json'));
        $credential = $serializer->deserialize($json, PublicKeyCredential::class, 'json');

        return service('webAuthnAttestationValidator')->check($credential->response, $options, $rpId);
    }

    public function testStoreAndFindRecord(): void
    {
        $user = fake(UserModel::class);
        $repo = service('webAuthnCredentialRepository');

        $record = $this->makeRecord('example.com', (string) $user->uuid);
        $entity = $repo->store((int) $user->id, $record, 'My Key');

        $this->assertNotEmpty($entity->credential_id);
        $this->assertSame('My Key', $entity->name);
        $this->assertSame(1, $repo->countActiveForUser((int) $user->id));

        $found = $repo->findRecordByCredentialId($entity->credential_id);
        $this->assertInstanceOf(CredentialRecord::class, $found);
        $this->assertSame((string) $user->id, (string) $repo->userIdForCredentialId($entity->credential_id));
    }

    public function testRevokeHidesCredential(): void
    {
        $user = fake(UserModel::class);
        $repo = service('webAuthnCredentialRepository');

        $record = $this->makeRecord('example.com', (string) $user->uuid);
        $entity = $repo->store((int) $user->id, $record, null);

        $this->assertTrue($repo->revokeByUuid((int) $user->id, (string) $entity->uuid));
        $this->assertNull($repo->findRecordByCredentialId($entity->credential_id));
        $this->assertSame(0, $repo->countActiveForUser((int) $user->id));
    }
}
