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

use Daycry\Auth\Entities\UserIdentity;

/**
 * Repository for JWT refresh token operations.
 *
 * Encapsulates JWT refresh token CRUD from UserIdentityModel.
 */
class JwtTokenRepository
{
    public function __construct(
        private readonly UserIdentityModel $identityModel,
    ) {
    }

    /**
     * Stores a new JWT refresh token for the given user.
     *
     * @param int    $userId    User primary key
     * @param string $rawToken  The raw (unhashed) token to store
     * @param string $expiresAt Datetime string 'Y-m-d H:i:s'
     */
    public function createRefreshToken(int $userId, string $rawToken, string $expiresAt): void
    {
        $this->identityModel->createJwtRefreshToken($userId, $rawToken, $expiresAt);
    }

    /**
     * Finds a valid (non-expired, non-revoked) JWT refresh token.
     *
     * @param int    $userId   User primary key
     * @param string $rawToken The raw (unhashed) token
     */
    public function getRefreshToken(int $userId, string $rawToken): ?UserIdentity
    {
        return $this->identityModel->getJwtRefreshToken($userId, $rawToken);
    }

    /**
     * Revokes a JWT refresh token (hard-delete).
     *
     * @param int $identityId The identity record primary key
     */
    public function revokeRefreshToken(int $identityId): void
    {
        $this->identityModel->delete($identityId);
    }

    /**
     * Soft-revokes a JWT refresh token by setting revoked_at.
     *
     * @param int $identityId The identity record primary key
     */
    public function softRevokeRefreshToken(int $identityId): void
    {
        $this->identityModel->revokeIdentityById($identityId);
    }
}
