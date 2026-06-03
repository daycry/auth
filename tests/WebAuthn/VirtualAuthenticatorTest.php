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

use Cose\Algorithms;
use Tests\Support\TestCase;
use Tests\Support\WebAuthn\SuppressesWebauthnDeprecations;
use Tests\Support\WebAuthn\VirtualAuthenticator;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Oracle test: the genuine web-auth/webauthn-lib v5 validators must accept the
 * byte structures hand-built by VirtualAuthenticator. Correctness of the helper
 * is *defined* by these two methods passing.
 *
 * @internal
 */
final class VirtualAuthenticatorTest extends TestCase
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

    public function testRegistrationResponseIsAcceptedByTheRealValidator(): void
    {
        setting('AuthSecurity.webauthnAllowedOrigins', ['https://example.com']);

        $rpId       = 'example.com';
        $serializer = service('webAuthnSerializer');

        $options = PublicKeyCredentialCreationOptions::create(
            PublicKeyCredentialRpEntity::create('Test', $rpId),
            PublicKeyCredentialUserEntity::create('joe', random_bytes(16), 'Joe'),
            random_bytes(32),
            [PublicKeyCredentialParameters::create('public-key', Algorithms::COSE_ALGORITHM_ES256)],
        );

        $authn = new VirtualAuthenticator($rpId, 'https://example.com');
        $json  = $authn->register($serializer->serialize($options, 'json'));

        /** @var PublicKeyCredential $credential */
        $credential = $serializer->deserialize($json, PublicKeyCredential::class, 'json');
        $this->assertInstanceOf(AuthenticatorAttestationResponse::class, $credential->response);

        $record = service('webAuthnAttestationValidator')->check(
            $credential->response,
            $options,
            $rpId,
        );

        $this->assertInstanceOf(CredentialRecord::class, $record);
    }

    public function testAssertionResponseIsAcceptedByTheRealValidator(): void
    {
        setting('AuthSecurity.webauthnAllowedOrigins', ['https://example.com']);

        $rpId       = 'example.com';
        $serializer = service('webAuthnSerializer');
        $userHandle = random_bytes(16);

        // --- Registration: produce a CredentialRecord the assertion will verify against.
        $creationOptions = PublicKeyCredentialCreationOptions::create(
            PublicKeyCredentialRpEntity::create('Test', $rpId),
            PublicKeyCredentialUserEntity::create('joe', $userHandle, 'Joe'),
            random_bytes(32),
            [PublicKeyCredentialParameters::create('public-key', Algorithms::COSE_ALGORITHM_ES256)],
        );

        $authn        = new VirtualAuthenticator($rpId, 'https://example.com');
        $registerJson = $authn->register($serializer->serialize($creationOptions, 'json'));

        /** @var PublicKeyCredential $regCredential */
        $regCredential = $serializer->deserialize($registerJson, PublicKeyCredential::class, 'json');

        $record = service('webAuthnAttestationValidator')->check(
            $regCredential->response,
            $creationOptions,
            $rpId,
        );
        $this->assertInstanceOf(CredentialRecord::class, $record);
        $this->assertSame(0, $record->counter);

        // --- Assertion: sign a fresh challenge and verify through the real validator.
        $requestOptions = PublicKeyCredentialRequestOptions::create(
            random_bytes(32),
            $rpId,
        );

        $loginJson = $authn->login($serializer->serialize($requestOptions, 'json'), $userHandle);

        /** @var PublicKeyCredential $loginCredential */
        $loginCredential = $serializer->deserialize($loginJson, PublicKeyCredential::class, 'json');
        $this->assertInstanceOf(AuthenticatorAssertionResponse::class, $loginCredential->response);

        $updatedRecord = service('webAuthnAssertionValidator')->check(
            $record,
            $loginCredential->response,
            $requestOptions,
            $rpId,
            $userHandle,
        );

        $this->assertInstanceOf(CredentialRecord::class, $updatedRecord);
        // The virtual authenticator increments its signature counter on every login.
        $this->assertGreaterThan(0, $updatedRecord->counter);
    }
}
