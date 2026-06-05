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
use Daycry\Auth\Services\AuditLogger;

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
     *
     * The query lives here (not in UserIdentityModel) because token CRUD
     * is the repository's responsibility — the legacy method on the
     * identity model is kept only as a deprecated thin wrapper.
     */
    public function getAccessTokenByRawToken(string $rawToken): ?AccessTokenIdentity
    {
        $result = $this->identityModel
            ->where('type', AccessToken::ID_TYPE_ACCESS_TOKEN)
            ->where('secret', hash('sha256', $rawToken))
            ->where('revoked_at')
            ->asObject(AccessTokenIdentity::class)
            ->first();

        return $result instanceof AccessTokenIdentity ? $result : null;
    }

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
    public function getAccessTokenByRawTokenWithUser(string $rawToken): ?AccessTokenIdentity
    {
        $tables          = setting('Auth.tables');
        $identitiesTable = $tables['identities'];
        $usersTable      = $tables['users'];

        // Every users column, aliased u_<col>, so the JOIN row carries both the
        // identity (identities.*) and the full user record in one fetch.
        $userColumns = [
            'id',
            'uuid',
            'username',
            'status',
            'status_message',
            'active',
            'last_active',
            'failed_login_count',
            'locked_until',
            'password_changed_at',
            'token_version',
            'created_at',
            'updated_at',
            'deleted_at',
        ];

        $select = $identitiesTable . '.*';

        foreach ($userColumns as $column) {
            $select .= sprintf(', %1$s.%2$s as u_%2$s', $usersTable, $column);
        }

        /** @var array<string, mixed>|null $row */
        $row = $this->identityModel
            ->select($select)
            ->join($usersTable, sprintf('%1$s.user_id = %2$s.id', $identitiesTable, $usersTable))
            ->where($identitiesTable . '.type', AccessToken::ID_TYPE_ACCESS_TOKEN)
            ->where($identitiesTable . '.secret', hash('sha256', $rawToken))
            ->where($identitiesTable . '.revoked_at')
            ->asArray()
            ->first();

        if ($row === null) {
            return null;
        }

        // Split the joined row: u_*-prefixed keys hydrate the User, everything
        // else is the identity record.
        $userData     = [];
        $identityData = [];

        foreach ($row as $key => $value) {
            if (str_starts_with((string) $key, 'u_')) {
                $userData[substr((string) $key, 2)] = $value;
            } else {
                $identityData[$key] = $value;
            }
        }

        // Build the User from the joined columns. Leaving its identities null
        // (DO NOT call setIdentities([])) keeps lazy-loading behaviour identical
        // to a findById() User.
        $user = new User($userData);
        $user->syncOriginal();

        // Hydrate the AccessToken identity exactly as the model's asObject()
        // path would: inject the raw DB row (no cast-on-set, so the serialized
        // `extra` column is preserved) and sync original — the entity is a real
        // AccessToken with its id, so a later save() is an UPDATE.
        $token = (new AccessTokenIdentity())->injectRawData($identityData);
        $token->setUser($user);

        return $token;
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

            (new AuditLogger())->record(AuditLogger::EVENT_TOKEN_REVOKED, (int) $user->id, [
                'identity_id' => (int) $token->id,
                'token_name'  => $token->name,
            ]);
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

        (new AuditLogger())->record(AuditLogger::EVENT_TOKEN_REVOKED, (int) $user->id, [
            'scope' => 'all_access_tokens',
        ]);
    }
}
