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

use Daycry\Auth\Entities\UserIdentity;

/**
 * Application contract for replacing JwtTokenRepository without inheritance.
 */
interface JwtTokenRepositoryInterface
{
    /**
     * Stores a new JWT refresh token for the given user.
     *
     * @param int    $userId    User primary key
     * @param string $rawToken  The raw (unhashed) token to store
     * @param string $expiresAt Datetime string 'Y-m-d H:i:s'
     */
    public function createRefreshToken(int $userId, string $rawToken, string $expiresAt, bool $mfaVerified = false): void;

    /**
     * Consume and replace in one transaction. A conditional UPDATE grants
     * exactly one request the rotation; a replay invalidates the family.
     * Replays are looked up even after revocation, until expired rows are purged.
     */
    public function rotateRefreshToken(int $userId, string $rawToken, string $replacement, string $expiresAt): bool;

    /**
     * Finds a valid (non-expired, non-revoked) JWT refresh token.
     *
     * @param int    $userId   User primary key
     * @param string $rawToken The raw (unhashed) token
     */
    public function getRefreshToken(int $userId, string $rawToken): ?UserIdentity;

    /**
     * Revokes a JWT refresh token (hard-delete).
     *
     * @param int $identityId The identity record primary key
     */
    public function revokeRefreshToken(int $identityId): void;

    /**
     * Soft-revokes a JWT refresh token by setting revoked_at.
     *
     * @param int         $identityId The identity record primary key
     * @param int|null    $userId     User id for audit (when known by the caller)
     * @param string|null $reason     Optional reason recorded in the audit log
     */
    public function softRevokeRefreshToken(int $identityId, ?int $userId = null, ?string $reason = null): void;

    /**
     * Soft-revokes every outstanding JWT refresh token of a user, so none of
     * them can mint new access tokens (ban, password change).
     *
     * @param int         $userId User primary key
     * @param string|null $reason Optional reason recorded in the audit log
     */
    public function softRevokeAllRefreshTokens(int $userId, ?string $reason = null): void;
}
