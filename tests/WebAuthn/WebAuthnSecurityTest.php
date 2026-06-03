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
use Daycry\Auth\Entities\WebAuthnCredential;
use Daycry\Auth\Exceptions\WebAuthnException;
use Daycry\Auth\Libraries\WebAuthn\WebAuthnManager;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Models\WebAuthnCredentialModel;
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
     * Invariant (anti-clone): the stored signature counter is monotonic. If a
     * later assertion presents a counter that is not strictly greater than the
     * stored one, the authenticator may have been cloned, so the assertion must
     * be rejected.
     *
     * We enrol and log in once (advancing + persisting the counter), then force
     * the stored counter high. A subsequent, otherwise-valid assertion from the
     * real authenticator emits a lower counter and must be rejected — for the
     * COUNTER reason, not the challenge (the challenge is freshly issued AFTER
     * the mutation, and the same ceremony shape succeeded on the first login).
     */
    public function testCounterRegressionIsRejected(): void
    {
        $user  = fake(UserModel::class);
        $authn = new VirtualAuthenticator('example.com', 'https://example.com');
        $this->enrol($user, $authn);

        // First login: advances the authenticator's counter and persists it via
        // updateCounter() (both the sign_count column and the credential JSON).
        $first = $this->manager()->startLogin(null);
        $this->manager()->finishLogin($authn->login(json_encode($first, JSON_THROW_ON_ERROR), (string) $user->uuid));

        // Control: a second login from the SAME authenticator (its counter keeps
        // increasing) is accepted. This proves the ceremony/challenge wiring is
        // sound, so the rejection below can only be the counter regression.
        $control  = $this->manager()->startLogin(null);
        $resolved = $this->manager()->finishLogin($authn->login(json_encode($control, JSON_THROW_ON_ERROR), (string) $user->uuid));
        $this->assertSame((string) $user->id, (string) $resolved->id);

        // Force the stored counter high inside the serialized credential record
        // (the validator compares against the counter rebuilt from this JSON).
        $model = model(WebAuthnCredentialModel::class);
        $row   = $model->where('user_id', $user->id)->first();
        $this->assertInstanceOf(WebAuthnCredential::class, $row);

        $credential            = json_decode($row->credential, true, 512, JSON_THROW_ON_ERROR);
        $credential['counter'] = 100;
        $model->where('id', $row->id)->set([
            'credential' => json_encode($credential, JSON_THROW_ON_ERROR),
            'sign_count' => 100,
        ])->update();

        // Fresh challenge AFTER the mutation; the real authenticator emits its
        // own (now-lower-than-100) counter, so the only thing wrong is the
        // counter regression.
        $options       = $this->manager()->startLogin(null);
        $assertionJson = $authn->login(json_encode($options, JSON_THROW_ON_ERROR), (string) $user->uuid);

        $this->expectException(WebAuthnException::class);
        $this->manager()->finishLogin($assertionJson);
    }

    /**
     * Anti-enumeration — documenting reality precisely:
     *
     * The non-enumerable path is the USERNAMELESS one. startLogin(null) (and
     * any input that resolves to no user) always returns an EMPTY
     * allowCredentials, so an attacker learns nothing about account existence.
     *
     * By contrast, supplying the email of a user who HAS an enrolled passkey
     * returns a POPULATED allowCredentials by design (so the browser can scope
     * the assertion). That is an intentional trade-off, not a guarantee of
     * indistinguishability for the email-scoped path — which is why the
     * recommended/anti-enumerable flow is usernameless.
     */
    public function testLoginOptionsDoNotRevealUserExistence(): void
    {
        // Usernameless path: nothing to scope, so allowCredentials is empty
        // regardless of input — this is the non-enumerable guarantee.
        $usernameless = $this->manager()->startLogin(null);
        $this->assertArrayHasKey('challenge', $usernameless);
        $this->assertSame([], $usernameless['allowCredentials'] ?? []);

        // A user that exists but has NO passkey is indistinguishable from a
        // non-existent user: both yield well-formed options with an empty
        // allowCredentials list.
        $known        = fake(UserModel::class);
        $known->email = 'known@example.com';
        model(UserModel::class)->save($known);

        $a = $this->manager()->startLogin('known@example.com');
        $b = $this->manager()->startLogin('does-not-exist@example.com');

        $this->assertArrayHasKey('challenge', $a);
        $this->assertArrayHasKey('challenge', $b);
        $this->assertSame(array_keys($a), array_keys($b));
        $this->assertSame($a['allowCredentials'] ?? [], $b['allowCredentials'] ?? []);
        $this->assertSame([], $a['allowCredentials'] ?? []);

        // Reality check: once the known user enrols a passkey, the email-scoped
        // path DOES populate allowCredentials — by design — so it is NOT a
        // non-enumeration guarantee. Only the usernameless path is.
        $authn = new VirtualAuthenticator('example.com', 'https://example.com');
        $this->enrol($known, $authn);

        $withPasskey = $this->manager()->startLogin('known@example.com');
        $this->assertNotSame([], $withPasskey['allowCredentials'] ?? []);

        // The usernameless path remains empty even though a passkey now exists.
        $stillUsernameless = $this->manager()->startLogin(null);
        $this->assertSame([], $stillUsernameless['allowCredentials'] ?? []);
    }
}
