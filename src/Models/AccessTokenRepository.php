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

use Daycry\Auth\Authentication\Authenticators\AccessToken;
use Daycry\Auth\Entities\AccessToken as AccessTokenIdentity;
use Daycry\Auth\Entities\User;

/**
 * Repository for personal access token operations.
 *
 * Encapsulates all access token CRUD from UserIdentityModel
 * into a focused, single-responsibility class.
 */
class AccessTokenRepository
{
    public function __construct(
        private readonly UserIdentityModel $identityModel,
    ) {
    }

    /**
     * Generates a new personal access token for the user.
     *
     * @param string       $name   Token name
     * @param list<string> $scopes Permissions the token grants
     */
    public function generateAccessToken(User $user, string $name, array $scopes = ['*']): AccessTokenIdentity
    {
        return $this->identityModel->generateAccessToken($user, $name, $scopes);
    }

    /**
     * Finds an access token by its raw (unhashed) value.
     * Excludes revoked tokens.
     */
    public function getAccessTokenByRawToken(string $rawToken): ?AccessTokenIdentity
    {
        return $this->identityModel->getAccessTokenByRawToken($rawToken);
    }

    /**
     * Finds an access token for a specific user by raw token.
     */
    public function getAccessToken(User $user, string $rawToken): ?AccessTokenIdentity
    {
        return $this->identityModel->getAccessToken($user, $rawToken);
    }

    /**
     * Given the ID, returns the given access token.
     */
    public function getAccessTokenById(int|string $id, User $user): ?AccessTokenIdentity
    {
        return $this->identityModel->getAccessTokenById($id, $user);
    }

    /**
     * Returns all access tokens for a user.
     *
     * @return list<AccessTokenIdentity>
     */
    public function getAllAccessTokens(User $user): array
    {
        return $this->identityModel->getAllAccessToken($user);
    }

    /**
     * Hard-deletes an access token by raw token value.
     *
     * @deprecated Use softRevokeAccessToken() for soft-revocation via revoked_at.
     */
    public function deleteAccessToken(User $user, string $rawToken): void
    {
        $this->identityModel->revokeAccessToken($user, $rawToken);
    }

    /**
     * Hard-deletes an access token by its hashed secret.
     *
     * @deprecated Use softRevokeAccessTokenBySecret() for soft-revocation via revoked_at.
     */
    public function deleteAccessTokenBySecret(User $user, string $secretToken): void
    {
        $this->identityModel->revokeAccessTokenBySecret($user, $secretToken);
    }

    /**
     * Hard-deletes all access tokens for a user.
     *
     * @deprecated Use softRevokeAllAccessTokens() for soft-revocation via revoked_at.
     */
    public function deleteAllAccessTokens(User $user): void
    {
        $this->identityModel->revokeAllAccessToken($user);
    }

    /**
     * Soft-revokes an access token by setting revoked_at (by raw token).
     */
    public function softRevokeAccessToken(User $user, string $rawToken): void
    {
        $token = $this->identityModel->getAccessToken($user, $rawToken);

        if ($token !== null) {
            $this->identityModel->revokeIdentityById((int) $token->id);
        }
    }

    /**
     * Soft-revokes an access token by its hashed secret.
     */
    public function softRevokeAccessTokenBySecret(User $user, string $secretToken): void
    {
        $token = $this->identityModel
            ->where('user_id', $user->id)
            ->where('type', AccessToken::ID_TYPE_ACCESS_TOKEN)
            ->where('secret', $secretToken)
            ->asObject(AccessTokenIdentity::class)
            ->first();

        if ($token !== null) {
            $this->identityModel->revokeIdentityById((int) $token->id);
        }
    }

    /**
     * Soft-revokes all access tokens for a user.
     */
    public function softRevokeAllAccessTokens(User $user): void
    {
        $this->identityModel->revokeIdentitiesByUserAndType(
            (int) $user->id,
            AccessToken::ID_TYPE_ACCESS_TOKEN,
        );
    }
}
