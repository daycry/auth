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

namespace Daycry\Auth\Interfaces;

use Daycry\Auth\Entities\WebAuthnCredential;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredentialDescriptor;

/**
 * Application contract for replacing WebAuthnCredentialRepository without inheritance.
 */
interface WebAuthnCredentialRepositoryInterface
{
    /**
     * findEntityByCredentialId contract.
     */
    public function findEntityByCredentialId(string $credentialIdBase64Url): ?WebAuthnCredential;

    /**
     * Whether the given credential record is already persisted (by its
     * credential id, computed exactly as {@see store()} does). Considers ALL
     * rows including revoked ones, mirroring the UNIQUE constraint which
     * ignores revoked_at.
     */
    public function existsByCredentialId(CredentialRecord $record): bool;

    /**
     * findRecordByCredentialId contract.
     */
    public function findRecordByCredentialId(string $credentialIdBase64Url): ?CredentialRecord;

    /**
     * userIdForCredentialId contract.
     */
    public function userIdForCredentialId(string $credentialIdBase64Url): int|string|null;

    /**
     * @return list<PublicKeyCredentialDescriptor>
     */
    public function descriptorsForUser(int|string $userId): array;

    /**
     * countActiveForUser contract.
     */
    public function countActiveForUser(int|string $userId): int;

    /**
     * @return list<WebAuthnCredential> Active credentials owned by this user.
     */
    public function activeForUser(int|string $userId): array;

    /**
     * store contract.
     */
    public function store(int|string $userId, CredentialRecord $record, ?string $name): WebAuthnCredential;

    /**
     * updateCounter contract.
     */
    public function updateCounter(CredentialRecord $record): void;

    /**
     * revokeByUuid contract.
     */
    public function revokeByUuid(int|string $userId, string $uuid): bool;
}
