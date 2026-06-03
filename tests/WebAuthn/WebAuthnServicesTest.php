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

use Symfony\Component\Serializer\SerializerInterface;
use Tests\Support\TestCase;
use Tests\Support\WebAuthn\SuppressesWebauthnDeprecations;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * @internal
 */
final class WebAuthnServicesTest extends TestCase
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

    public function testSerializerResolvesAndRoundTripsOptions(): void
    {
        $serializer = service('webAuthnSerializer');
        $this->assertInstanceOf(SerializerInterface::class, $serializer);

        $options = PublicKeyCredentialCreationOptions::create(
            PublicKeyCredentialRpEntity::create('Test', 'example.com'),
            PublicKeyCredentialUserEntity::create('joe', 'handle-bytes', 'Joe'),
            random_bytes(16),
        );

        $json = $serializer->serialize($options, 'json');
        $this->assertJson($json);

        $back = $serializer->deserialize($json, PublicKeyCredentialCreationOptions::class, 'json');
        $this->assertInstanceOf(PublicKeyCredentialCreationOptions::class, $back);
    }

    public function testValidatorsResolve(): void
    {
        $this->assertInstanceOf(AuthenticatorAttestationResponseValidator::class, service('webAuthnAttestationValidator'));
        $this->assertInstanceOf(AuthenticatorAssertionResponseValidator::class, service('webAuthnAssertionValidator'));
    }
}
