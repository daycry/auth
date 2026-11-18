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

namespace Daycry\Auth\Libraries\WebAuthn;

use Cose\Algorithms;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Entities\WebAuthnCredential;
use Daycry\Auth\Exceptions\WebAuthnDuplicateCredentialException;
use Daycry\Auth\Exceptions\WebAuthnException;
use Daycry\Auth\Interfaces\WebAuthnCredentialRepositoryInterface;
use Daycry\Auth\Interfaces\WebAuthnManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Orchestrates the WebAuthn ceremonies (registration here; login/2FA added
 * later) using web-auth/webauthn-lib v5. Mirrors Libraries/Oauth/OauthManager.
 */
class WebAuthnManager implements WebAuthnManagerInterface
{
    public function __construct(
        private readonly WebAuthnCredentialRepositoryInterface $repository,
        private readonly ChallengeManager $challenges,
        private readonly SerializerInterface $serializer,
        private readonly AuthenticatorAttestationResponseValidator $attestationValidator,
        private readonly AuthenticatorAssertionResponseValidator $assertionValidator,
    ) {
    }

    private function rpId(): string
    {
        $id = setting('AuthSecurity.webauthnRelyingPartyId');

        return is_string($id) && $id !== '' ? $id : (parse_url(base_url(), PHP_URL_HOST) ?: 'localhost');
    }

    /**
     * @return array<string, mixed> creation options ready for JSON
     */
    public function startRegistration(User $user, ?string $label = null): array
    {
        $max = (int) (setting('AuthSecurity.webauthnMaxCredentialsPerUser') ?? 10);
        if ($this->repository->countActiveForUser($user->id) >= $max) {
            throw new WebAuthnException(lang('Auth.webauthnMaxCredentials'));
        }

        $rp         = PublicKeyCredentialRpEntity::create((string) setting('AuthSecurity.webauthnRelyingPartyName'), $this->rpId());
        $userEntity = PublicKeyCredentialUserEntity::create(
            (string) ($user->username ?? $user->email ?? (string) $user->id),
            (string) $user->uuid,
            (string) ($user->username ?? $user->email ?? (string) $user->id),
        );

        $attachment = setting('AuthSecurity.webauthnAuthenticatorAttachment');
        $selection  = AuthenticatorSelectionCriteria::create(
            authenticatorAttachment: is_string($attachment) ? $attachment : null,
            userVerification: (string) setting('AuthSecurity.webauthnUserVerification'),
            residentKey: (string) setting('AuthSecurity.webauthnResidentKey'),
        );

        $options = PublicKeyCredentialCreationOptions::create(
            $rp,
            $userEntity,
            random_bytes(32),
            [
                PublicKeyCredentialParameters::create('public-key', Algorithms::COSE_ALGORITHM_ES256),
                PublicKeyCredentialParameters::create('public-key', Algorithms::COSE_ALGORITHM_RS256),
            ],
            $selection,
            (string) setting('AuthSecurity.webauthnAttestationConveyance'),
            $this->repository->descriptorsForUser($user->id),
            (int) setting('AuthSecurity.webauthnTimeout'),
        );

        $json = $this->serializer->serialize($options, 'json');
        $this->challenges->store('register', $json, $user->id);
        $this->challenges->stashLabel($label);

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    public function finishRegistration(User $user, string $browserJson): WebAuthnCredential
    {
        $entry = $this->challenges->pull('register', $user->id);
        if ($entry === null) {
            throw new WebAuthnException(lang('Auth.webauthnChallengeExpired'));
        }

        try {
            /** @var PublicKeyCredentialCreationOptions $options */
            $options    = $this->serializer->deserialize($entry['options'], PublicKeyCredentialCreationOptions::class, 'json');
            $credential = $this->serializer->deserialize($browserJson, PublicKeyCredential::class, 'json');

            if (! $credential->response instanceof AuthenticatorAttestationResponse) {
                throw new WebAuthnException(lang('Auth.webauthnVerificationFailed'));
            }

            $record = $this->attestationValidator->check($credential->response, $options, $this->rpId());

            // Reject a credential that is already stored before attempting the
            // insert, so the caller gets a clean conflict instead of a raw
            // UNIQUE-constraint DatabaseException leaking out. The UNIQUE index
            // on credential_id ignores revoked_at, so this checks all rows.
            if ($this->repository->existsByCredentialId($record)) {
                throw new WebAuthnDuplicateCredentialException(lang('Auth.webauthnDuplicate'));
            }
        } catch (WebAuthnException $e) {
            throw $e;
        } catch (Throwable $e) {
            log_message('warning', 'WebAuthn registration failed: {m}', ['m' => $e->getMessage()]);

            throw new WebAuthnException(lang('Auth.webauthnVerificationFailed'));
        }

        return $this->repository->store($user->id, $record, $this->challenges->pullLabel());
    }

    /**
     * @return array<string, mixed> request options ready for JSON
     */
    public function startLogin(?string $email): array
    {
        $allow = [];
        if ($email !== null && $email !== '') {
            $user  = auth()->getProvider()->findByCredentials(['email' => $email]);
            $allow = $user !== null ? $this->repository->descriptorsForUser($user->id) : [];

            // Unknown e-mail, or an account without passkeys: answer with a
            // stable decoy so the response does not reveal whether the
            // account exists or which credentials it has (enumeration oracle).
            if ($allow === []) {
                $allow = [$this->decoyDescriptor($email)];
            }
        }

        $options = PublicKeyCredentialRequestOptions::create(
            random_bytes(32),
            $this->rpId(),
            $allow,
            (string) setting('AuthSecurity.webauthnUserVerification'),
            (int) setting('AuthSecurity.webauthnTimeout'),
        );

        $json = $this->serializer->serialize($options, 'json');
        $this->challenges->store('login', $json);

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    public function finishLogin(string $browserJson): User
    {
        $entry = $this->challenges->pull('login');
        if ($entry === null) {
            throw new WebAuthnException(lang('Auth.webauthnChallengeExpired'));
        }

        return $this->verifyAssertion($entry['options'], $browserJson, null);
    }

    /**
     * @return array<string, mixed> request options scoped to the pending user
     */
    public function startTwoFactor(User $pendingUser): array
    {
        $options = PublicKeyCredentialRequestOptions::create(
            random_bytes(32),
            $this->rpId(),
            $this->repository->descriptorsForUser($pendingUser->id),
            (string) setting('AuthSecurity.webauthnUserVerification'),
            (int) setting('AuthSecurity.webauthnTimeout'),
        );

        $json = $this->serializer->serialize($options, 'json');
        $this->challenges->store('2fa', $json, $pendingUser->id);

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    public function finishTwoFactor(User $pendingUser, string $browserJson): bool
    {
        $entry = $this->challenges->pull('2fa', $pendingUser->id);
        if ($entry === null) {
            return false;
        }

        try {
            $resolved = $this->verifyAssertion($entry['options'], $browserJson, $pendingUser->id);

            return (string) $resolved->id === (string) $pendingUser->id;
        } catch (WebAuthnException) {
            return false;
        }
    }

    /**
     * @return array<string, mixed> Single-use assertion scoped to the signed-in user.
     */
    public function startStepUp(User $user): array
    {
        $options = PublicKeyCredentialRequestOptions::create(
            random_bytes(32),
            $this->rpId(),
            $this->repository->descriptorsForUser($user->id),
            'required',
            (int) setting('AuthSecurity.webauthnTimeout'),
        );
        $json = $this->serializer->serialize($options, 'json');
        $this->challenges->store('step-up', $json, $user->id);

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    public function finishStepUp(User $user, string $browserJson): bool
    {
        $entry = $this->challenges->pull('step-up', $user->id);
        if ($entry === null) {
            return false;
        }

        try {
            return (string) $this->verifyAssertion($entry['options'], $browserJson, $user->id)->id === (string) $user->id;
        } catch (WebAuthnException) {
            return false;
        }
    }

    /**
     * Only call for the authenticated user: credential IDs must not be public.
     * userId is the base64url form of user.id from creation options.
     *
     * @return array{rpId: string, userId: string, allAcceptedCredentialIds: list<string>}
     */
    public function credentialSignals(User $user): array
    {
        $ids = [];

        foreach ($user->webAuthnCredentials() as $credential) {
            $ids[] = (string) $credential->credential_id;
        }

        return [
            'rpId'                     => $this->rpId(),
            'userId'                   => rtrim(strtr(base64_encode((string) $user->uuid), '+/', '-_'), '='),
            'allAcceptedCredentialIds' => $ids,
        ];
    }

    /**
     * Shared assertion verification. Looks up the credential by rawId, runs the
     * library check (signature, challenge, origin, rpIdHash, UV, counter),
     * persists the advanced counter, and returns the owning user.
     *
     * @param int|string|null $requireUserId when set, the credential must belong to this user
     */
    /**
     * Builds a fake credential descriptor that is deterministic per e-mail
     * (HMAC keyed with the application encryption key), so repeated requests
     * for the same address get the same answer — exactly like a real account.
     * Its id length and transports mimic real authenticators. It can never
     * match a stored credential.
     */
    private function decoyDescriptor(string $email): PublicKeyCredentialDescriptor
    {
        $key = (string) config('Encryption')->key;

        if ($key === '') {
            log_message('warning', 'WebAuthn decoy credentials need Config\Encryption::$key; without it they can be told apart from real ones.');
        }

        $seed = hash_hmac('sha512', 'webauthn-login-decoy|' . strtolower(trim($email)), $key, true);

        // Real descriptors vary: some authenticators report no transports at
        // all (the key is then omitted), so the decoy must vary the same way.
        $lengths    = [16, 20, 32, 64];
        $transports = [['internal', 'hybrid'], ['hybrid', 'internal'], ['usb', 'nfc'], ['internal'], []];

        return PublicKeyCredentialDescriptor::create(
            PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
            substr($seed, 0, $lengths[ord($seed[0]) % count($lengths)]),
            $transports[ord($seed[1]) % count($transports)],
        );
    }

    private function verifyAssertion(string $optionsJson, string $browserJson, int|string|null $requireUserId): User
    {
        try {
            /** @var PublicKeyCredentialRequestOptions $options */
            $options    = $this->serializer->deserialize($optionsJson, PublicKeyCredentialRequestOptions::class, 'json');
            $credential = $this->serializer->deserialize($browserJson, PublicKeyCredential::class, 'json');

            if (! $credential->response instanceof AuthenticatorAssertionResponse) {
                throw new WebAuthnException(lang('Auth.webauthnVerificationFailed'));
            }

            $credentialId = rtrim(strtr(base64_encode($credential->rawId), '+/', '-_'), '=');
            $userId       = $this->repository->userIdForCredentialId($credentialId);

            if ($userId === null || ($requireUserId !== null && (string) $userId !== (string) $requireUserId)) {
                throw new WebAuthnException(lang('Auth.webauthnVerificationFailed'));
            }

            $record = $this->repository->findRecordByCredentialId($credentialId);
            if ($record === null) {
                throw new WebAuthnException(lang('Auth.webauthnVerificationFailed'));
            }

            $updated = $this->assertionValidator->check(
                $record,
                $credential->response,
                $options,
                $this->rpId(),
                $record->userHandle,
            );
        } catch (WebAuthnException $e) {
            throw $e;
        } catch (Throwable $e) {
            log_message('warning', 'WebAuthn assertion failed: {m}', ['m' => $e->getMessage()]);

            throw new WebAuthnException(lang('Auth.webauthnVerificationFailed'));
        }

        $this->repository->updateCounter($updated);

        $user = auth()->getProvider()->findById($userId);
        if ($user === null) {
            throw new WebAuthnException(lang('Auth.webauthnVerificationFailed'));
        }

        return $user;
    }
}
