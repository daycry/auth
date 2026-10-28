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

use Daycry\Auth\Entities\AccessToken as AccessTokenIdentity;
use Daycry\Auth\Entities\User;

/**
 * Application contract for replacing AccessTokenRepository without inheritance.
 */
interface AccessTokenRepositoryInterface
{
    /**
     * Generates a new personal access token for the user.
     *
     * @param string       $name   Token name
     * @param list<string> $scopes Permissions the token grants
     */
    public function generateAccessToken(User $user, string $name, array $scopes = ['*']): AccessTokenIdentity;

    /**
     * Finds an access token by its raw (unhashed) value.
     * Excludes revoked tokens.
     *
     * The query lives here (not in UserIdentityModel) because token CRUD
     * is the repository's responsibility — the legacy method on the
     * identity model is kept only as a deprecated thin wrapper.
     */
    public function getAccessTokenByRawToken(string $rawToken): ?AccessTokenIdentity;

    /**
     * Finds an access token by its raw (unhashed) value AND eager-loads the
     * owning user in the SAME query via a JOIN — collapsing the two SELECTs
     * that access-token authentication would otherwise issue (token lookup +
     * lazy User::user()) into one.
     *
     * Behaviour is identical to getAccessTokenByRawToken() + $token->user():
     * the User is hydrated from the joined columns with its identities left
     * null, so they still lazy-load on first access exactly as a findById()
     * User would. The token entity is returned fully hydrated (id +
     * syncOriginal()) so a later last_used_at save() is an UPDATE.
     *
     * Excludes revoked tokens.
     */
    public function getAccessTokenByRawTokenWithUser(string $rawToken): ?AccessTokenIdentity;

    /**
     * Finds an access token for a specific user by raw token.
     */
    public function getAccessToken(User $user, string $rawToken): ?AccessTokenIdentity;

    /**
     * Given the ID, returns the given access token.
     */
    public function getAccessTokenById(int|string $id, User $user): ?AccessTokenIdentity;

    /**
     * Returns all access tokens for a user.
     *
     * @return list<AccessTokenIdentity>
     */
    public function getAllAccessTokens(User $user): array;

    /**
     * Hard-deletes an access token by raw token value.
     *
     * @deprecated Use softRevokeAccessToken() for soft-revocation via revoked_at.
     */
    public function deleteAccessToken(User $user, string $rawToken): void;

    /**
     * Hard-deletes an access token by its hashed secret.
     *
     * @deprecated Use softRevokeAccessTokenBySecret() for soft-revocation via revoked_at.
     */
    public function deleteAccessTokenBySecret(User $user, string $secretToken): void;

    /**
     * Hard-deletes all access tokens for a user.
     *
     * @deprecated Use softRevokeAllAccessTokens() for soft-revocation via revoked_at.
     */
    public function deleteAllAccessTokens(User $user): void;

    /**
     * Soft-revokes an access token by setting revoked_at (by raw token).
     */
    public function softRevokeAccessToken(User $user, string $rawToken): void;

    /**
     * Soft-revokes an access token by its hashed secret.
     */
    public function softRevokeAccessTokenBySecret(User $user, string $secretToken): void;

    /**
     * Soft-revokes all access tokens for a user.
     */
    public function softRevokeAllAccessTokens(User $user): void;
}
