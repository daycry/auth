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
}
