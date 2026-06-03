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

use Daycry\Auth\Entities\WebAuthnCredential;
use Daycry\Auth\Exceptions\WebAuthnDuplicateCredentialException;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;
use Tests\Support\WebAuthn\SuppressesWebauthnDeprecations;
use Tests\Support\WebAuthn\VirtualAuthenticator;

/**
 * @internal
 */
final class WebAuthnManagerRegistrationTest extends DatabaseTestCase
{
    use SuppressesWebauthnDeprecations;

    protected function setUp(): void
    {
        parent::setUp();
        setting('AuthSecurity.webauthnEnabled', true);
        setting('AuthSecurity.webauthnRelyingPartyId', 'example.com');
        setting('AuthSecurity.webauthnAllowedOrigins', ['https://example.com']);
        $this->suppressWebauthnDeprecations();
    }

    protected function tearDown(): void
    {
        $this->restoreWebauthnDeprecations();
        parent::tearDown();
    }

    public function testRegistrationRoundTripPersistsCredential(): void
    {
        $user    = fake(UserModel::class);
        $manager = service('webAuthnManager');

        $options = $manager->startRegistration($user, 'My Laptop');
        $this->assertArrayHasKey('challenge', $options);

        $authn = new VirtualAuthenticator('example.com', 'https://example.com');
        $json  = $authn->register(json_encode($options, JSON_THROW_ON_ERROR));

        $entity = $manager->finishRegistration($user, $json);

        $this->assertInstanceOf(WebAuthnCredential::class, $entity);
        $this->assertSame('My Laptop', $entity->name);
        $this->seeInDatabase(config('Auth')->tables['webauthn_credentials'], [
            'user_id' => $user->id,
            'name'    => 'My Laptop',
        ]);
    }

    /**
     * Re-submitting an already-registered credential must raise a typed
     * WebAuthnDuplicateCredentialException (so the controller can map it to a
     * clean 409) — never a raw UNIQUE-constraint DatabaseException.
     */
    public function testDuplicateRegistrationThrowsTypedException(): void
    {
        $user    = fake(UserModel::class);
        $manager = service('webAuthnManager');
        $authn   = new VirtualAuthenticator('example.com', 'https://example.com');

        // First enrolment persists the credential.
        $options = $manager->startRegistration($user, 'Key');
        $manager->finishRegistration($user, $authn->register(json_encode($options, JSON_THROW_ON_ERROR)));

        // Second enrolment of the SAME credential id (same authenticator
        // instance) with a fresh challenge must be rejected as a duplicate.
        $options2 = $manager->startRegistration($user, 'Key again');

        $this->expectException(WebAuthnDuplicateCredentialException::class);
        $manager->finishRegistration($user, $authn->register(json_encode($options2, JSON_THROW_ON_ERROR)));
    }
}
