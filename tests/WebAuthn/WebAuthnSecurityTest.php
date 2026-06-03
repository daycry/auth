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
use Daycry\Auth\Libraries\WebAuthn\WebAuthnManager;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;
use Tests\Support\WebAuthn\SuppressesWebauthnDeprecations;
use Tests\Support\WebAuthn\VirtualAuthenticator;

/**
 * Adversarial, security-invariant negative tests. Each method tampers with one
 * input of a WebAuthn ceremony and asserts the manager rejects it for the
 * intended reason (origin, single-use/expiry of the challenge, credential
 * ownership, unknown credential, anti-enumeration of login options).
 *
 * @internal
 */
final class WebAuthnSecurityTest extends DatabaseTestCase
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

    private function manager(): WebAuthnManager
    {
        return service('webAuthnManager');
    }

    private function enrol(User $user, VirtualAuthenticator $authn): void
    {
        $options = $this->manager()->startRegistration($user, 'Key');
        $this->manager()->finishRegistration($user, $authn->register(json_encode($options, JSON_THROW_ON_ERROR)));
    }

    /**
     * Invariant: the relying party only accepts assertions whose clientDataJSON
     * origin is in the allow-list. A credential created at a phishing origin
     * must be rejected at attestation time (origin binding).
     */
    public function testTamperedOriginIsRejected(): void
    {
        $user  = fake(UserModel::class);
        $authn = new VirtualAuthenticator('example.com', 'https://evil.example.net');

        $options = $this->manager()->startRegistration($user, 'Key');

        $this->expectException(WebAuthnException::class);
        $this->manager()->finishRegistration($user, $authn->register(json_encode($options, JSON_THROW_ON_ERROR)));
    }

    /**
     * Invariant: a challenge is single-use. A valid assertion replayed against
     * an already-consumed challenge must be rejected (the challenge entry was
     * removed on the first pull).
     */
    public function testReplayedChallengeIsRejected(): void
    {
        $user  = fake(UserModel::class);
        $authn = new VirtualAuthenticator('example.com', 'https://example.com');
        $this->enrol($user, $authn);

        $options       = $this->manager()->startLogin(null);
        $assertionJson = $authn->login(json_encode($options, JSON_THROW_ON_ERROR), (string) $user->uuid);

        // First use succeeds: proves the assertion itself is valid, so the
        // replay below fails because of single-use, not a malformed assertion.
        $resolved = $this->manager()->finishLogin($assertionJson);
        $this->assertSame((string) $user->id, (string) $resolved->id);

        // Replay of the very same assertion: the challenge was already pulled.
        $this->expectException(WebAuthnException::class);
        $this->manager()->finishLogin($assertionJson);
    }

    /**
     * Invariant: a challenge is time-bounded. With the TTL reduced to 0 after
     * issuance, a freshly-built (otherwise valid) assertion must be rejected as
     * expired.
     */
    public function testExpiredChallengeIsRejected(): void
    {
        $user  = fake(UserModel::class);
        $authn = new VirtualAuthenticator('example.com', 'https://example.com');
        $this->enrol($user, $authn);

        $options = $this->manager()->startLogin(null);
        // Expire the challenge AFTER it was issued so the only thing wrong with
        // the assertion is its age.
        setting('AuthSecurity.webauthnChallengeTtl', 0);

        $this->expectException(WebAuthnException::class);
        $this->manager()->finishLogin($authn->login(json_encode($options, JSON_THROW_ON_ERROR), (string) $user->uuid));
    }

    /**
     * Invariant: in a 2FA ceremony the asserted credential must belong to the
     * pending user. Here the assertion is cryptographically valid but the
     * credential belongs to a different user, so ownership enforcement must
     * make finishTwoFactor() return false.
     */
    public function testWrongUserCredentialIn2faIsRejected(): void
    {
        $owner = fake(UserModel::class);
        $other = fake(UserModel::class);
        $authn = new VirtualAuthenticator('example.com', 'https://example.com');
        $this->enrol($owner, $authn);

        // 'other' has no credential; a 2FA attempt for 'other' using the owner's
        // (valid) assertion must fail the ownership check.
        $options       = $this->manager()->startTwoFactor($other);
        $assertionJson = $authn->login(json_encode($options, JSON_THROW_ON_ERROR), (string) $owner->uuid);

        $this->assertFalse($this->manager()->finishTwoFactor($other, $assertionJson));

        // Sanity: the same assertion IS accepted when the pending user is the
        // real owner, proving the rejection above is ownership, not a bad
        // assertion. A fresh ceremony is required (the challenge is single-use).
        $ownerOptions   = $this->manager()->startTwoFactor($owner);
        $ownerAssertion = $authn->login(json_encode($ownerOptions, JSON_THROW_ON_ERROR), (string) $owner->uuid);
        $this->assertTrue($this->manager()->finishTwoFactor($owner, $ownerAssertion));
    }

    /**
     * Invariant: an assertion from a credential the relying party never
     * registered must be rejected (no enrolment for this rawId).
     */
    public function testUnknownCredentialIsRejected(): void
    {
        // This authenticator is never enrolled, so its credential id is unknown.
        $authn   = new VirtualAuthenticator('example.com', 'https://example.com');
        $options = $this->manager()->startLogin(null);

        $this->expectException(WebAuthnException::class);
        $this->manager()->finishLogin($authn->login(json_encode($options, JSON_THROW_ON_ERROR), 'unknown-handle'));
    }

    /**
     * Invariant (anti-enumeration): login options for an existing user and a
     * non-existent user are structurally indistinguishable — both carry a
     * challenge and an (empty, since no passkeys are enrolled) allowCredentials
     * list, so an attacker cannot probe for account existence.
     */
    public function testLoginOptionsDoNotRevealUserExistence(): void
    {
        $known        = fake(UserModel::class);
        $known->email = 'known@example.com';
        model(UserModel::class)->save($known);

        $a = $this->manager()->startLogin('known@example.com');
        $b = $this->manager()->startLogin('does-not-exist@example.com');

        // Both return well-formed options with a fresh challenge.
        $this->assertArrayHasKey('challenge', $a);
        $this->assertArrayHasKey('challenge', $b);

        // The known user has no enrolled passkey, so its allowCredentials does
        // not leak existence: the shape is identical to the unknown user's. We
        // compare the *keys* of both option sets rather than relying on a
        // tautological merge.
        $this->assertSame(array_keys($a), array_keys($b));
        $this->assertSame(
            $a['allowCredentials'] ?? [],
            $b['allowCredentials'] ?? [],
        );
    }
}
