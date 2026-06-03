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

namespace Daycry\Auth\Models;

use CodeIgniter\I18n\Time;
use Daycry\Auth\Entities\WebAuthnCredential;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredentialDescriptor;

/**
 * Persistence seam mapping auth_webauthn_credentials rows to/from the
 * web-auth/webauthn-lib CredentialRecord. Implements no library interface
 * (v5 pure-PHP needs none); mirrors OAuthTokenRepository.
 */
class WebAuthnCredentialRepository
{
    public function __construct(
        private readonly WebAuthnCredentialModel $model,
        private readonly SerializerInterface $serializer,
    ) {
    }

    private function b64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public function findEntityByCredentialId(string $credentialIdBase64Url): ?WebAuthnCredential
    {
        return $this->model->firstActiveByCredentialId($credentialIdBase64Url);
    }

    public function findRecordByCredentialId(string $credentialIdBase64Url): ?CredentialRecord
    {
        $row = $this->model->firstActiveByCredentialId($credentialIdBase64Url);
        if ($row === null) {
            return null;
        }

        return $this->serializer->deserialize($row->credential, CredentialRecord::class, 'json');
    }

    public function userIdForCredentialId(string $credentialIdBase64Url): int|string|null
    {
        $row = $this->model->firstActiveByCredentialId($credentialIdBase64Url);

        return $row?->user_id;
    }

    /**
     * @return list<PublicKeyCredentialDescriptor>
     */
    public function descriptorsForUser(int|string $userId): array
    {
        $descriptors = [];

        foreach ($this->model->activeForUser($userId) as $row) {
            $record        = $this->serializer->deserialize($row->credential, CredentialRecord::class, 'json');
            $descriptors[] = $record->getPublicKeyCredentialDescriptor();
        }

        return $descriptors;
    }

    public function countActiveForUser(int|string $userId): int
    {
        return $this->model->countActiveForUser($userId);
    }

    public function store(int|string $userId, CredentialRecord $record, ?string $name): WebAuthnCredential
    {
        $credentialId = $this->b64url($record->publicKeyCredentialId);

        $id = $this->model->insert([
            'user_id'       => $userId,
            'credential_id' => $credentialId,
            'credential'    => $this->serializer->serialize($record, 'json'),
            'user_handle'   => $record->userHandle,
            'name'          => $name,
            'sign_count'    => $record->counter,
            'transports'    => json_encode($record->transports),
            'aaguid'        => $record->aaguid->toRfc4122(),
        ], true);

        /** @var WebAuthnCredential $row */
        $row = $this->model->find($id);

        return $row;
    }

    public function updateCounter(CredentialRecord $record): void
    {
        $credentialId = $this->b64url($record->publicKeyCredentialId);

        $this->model->where('credential_id', $credentialId)->set([
            'credential'   => $this->serializer->serialize($record, 'json'),
            'sign_count'   => $record->counter,
            'last_used_at' => Time::now()->format('Y-m-d H:i:s'),
        ])->update();
    }

    public function revokeByUuid(int|string $userId, string $uuid): bool
    {
        $row = $this->model->where('user_id', $userId)->where('uuid', $uuid)->where('revoked_at')->first();
        if ($row === null) {
            return false;
        }

        $this->model->where('id', $row->id)->set(['revoked_at' => Time::now()->format('Y-m-d H:i:s')])->update();

        return true;
    }
}
