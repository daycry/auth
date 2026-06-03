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
use Daycry\Auth\Exceptions\WebAuthnException;
use Daycry\Auth\Models\WebAuthnCredentialRepository;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Orchestrates the WebAuthn ceremonies (registration here; login/2FA added
 * later) using web-auth/webauthn-lib v5. Mirrors Libraries/Oauth/OauthManager.
 */
class WebAuthnManager
{
    public function __construct(
        private readonly WebAuthnCredentialRepository $repository,
        private readonly ChallengeManager $challenges,
        private readonly SerializerInterface $serializer,
        private readonly AuthenticatorAttestationResponseValidator $attestationValidator,
        private readonly AuthenticatorAssertionResponseValidator $assertionValidator,
    ) {
    }

    private function rpId(): string
    {
        $id = setting('AuthSecurity.webauthnRelyingPartyId');

        return is_string($id) && $id !== '' ? $id : (parse_url((string) base_url(), PHP_URL_HOST) ?: 'localhost');
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
        } catch (WebAuthnException $e) {
            throw $e;
        } catch (Throwable $e) {
            log_message('warning', 'WebAuthn registration failed: {m}', ['m' => $e->getMessage()]);

            throw new WebAuthnException(lang('Auth.webauthnVerificationFailed'));
        }

        return $this->repository->store($user->id, $record, $this->challenges->pullLabel());
    }
}
