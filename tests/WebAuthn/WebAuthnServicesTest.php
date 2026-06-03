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
    protected function setUp(): void
    {
        parent::setUp();

        // web-auth/webauthn-lib v5.3 deprecates the (still required) RP-entity
        // $name parameter. Under CODEIGNITER_SCREAM_DEPRECATIONS=1 CodeIgniter
        // promotes every E_USER_DEPRECATED to an ErrorException, so silence the
        // library's own internal deprecations for the duration of these tests.
        set_error_handler(
            static fn (int $severity, string $message, string $file = ''): bool => str_contains($file, 'web-auth' . DIRECTORY_SEPARATOR . 'webauthn-lib')
                || str_contains($message, 'web-auth/webauthn-lib'),
            E_USER_DEPRECATED,
        );
    }

    protected function tearDown(): void
    {
        restore_error_handler();

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
