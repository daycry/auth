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

use Daycry\Auth\Entities\User;
use Daycry\Auth\Exceptions\WebAuthnException;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;
use Tests\Support\WebAuthn\SuppressesWebauthnDeprecations;
use Tests\Support\WebAuthn\VirtualAuthenticator;

/**
 * @internal
 */
final class WebAuthnManagerLoginTest extends DatabaseTestCase
{
    use SuppressesWebauthnDeprecations;

    private VirtualAuthenticator $authn;

    protected function setUp(): void
    {
        parent::setUp();
        setting('AuthSecurity.webauthnEnabled', true);
        setting('AuthSecurity.webauthnRelyingPartyId', 'example.com');
        setting('AuthSecurity.webauthnAllowedOrigins', ['https://example.com']);
        $this->authn = new VirtualAuthenticator('example.com', 'https://example.com');
        $this->suppressWebauthnDeprecations();
    }

    protected function tearDown(): void
    {
        $this->restoreWebauthnDeprecations();
        parent::tearDown();
    }

    private function enrol(User $user): void
    {
        $manager = service('webAuthnManager');
        $options = $manager->startRegistration($user, 'Key');
        $json    = $this->authn->register(json_encode($options, JSON_THROW_ON_ERROR));
        $manager->finishRegistration($user, $json);
    }

    public function testPasswordlessLoginReturnsTheUser(): void
    {
        $user = fake(UserModel::class);
        $this->enrol($user);

        $manager = service('webAuthnManager');
        $options = $manager->startLogin(null); // usernameless
        $json    = $this->authn->login(json_encode($options, JSON_THROW_ON_ERROR), (string) $user->uuid);

        $resolved = $manager->finishLogin($json);
        $this->assertSame((string) $user->id, (string) $resolved->id);
    }

    public function testAssertionFromUnknownCredentialIsRejected(): void
    {
        $user = fake(UserModel::class);
        $this->enrol($user);

        $manager = service('webAuthnManager');

        // A different authenticator → a credential id we never stored.
        $stranger = new VirtualAuthenticator('example.com', 'https://example.com');
        $options  = $manager->startLogin(null);

        $this->expectException(WebAuthnException::class);
        $manager->finishLogin($stranger->login(json_encode($options, JSON_THROW_ON_ERROR), (string) $user->uuid));
    }
}
