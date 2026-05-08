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

namespace Daycry\Auth\Traits;

use Daycry\Auth\Entities\AccessToken;
use Daycry\Auth\Models\AccessTokenRepository;
use Daycry\Auth\Models\UserIdentityModel;

/**
 * Trait HasAccessTokens
 *
 * Provides functionality needed to generate, revoke,
 * and retrieve Personal Access Tokens.
 *
 * Intended to be used with User entities.
 */
trait HasAccessTokens
{
    /**
     * The current access token for the user.
     */
    private ?AccessToken $currentAccessToken = null;

    /**
     * Returns the AccessTokenRepository (the focused CRUD layer that wraps
     * UserIdentityModel for personal-access-token operations).
     */
    private function accessTokenRepository(): AccessTokenRepository
    {
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        return new AccessTokenRepository($identityModel);
    }

    /**
     * Generates a new personal access token for this user.
     *
     * @param string       $name   Token name
     * @param list<string> $scopes Permissions the token grants
     */
    public function generateAccessToken(string $name, array $scopes = ['*']): AccessToken
    {
        return $this->accessTokenRepository()->generateAccessToken($this, $name, $scopes);
    }

    /**
     * Soft-revokes an access token by raw value.
     */
    public function revokeAccessToken(string $rawToken): void
    {
        $this->accessTokenRepository()->softRevokeAccessToken($this, $rawToken);
    }

    /**
     * Soft-revokes an access token by hashed secret.
     */
    public function revokeAccessTokenBySecret(string $secretToken): void
    {
        $this->accessTokenRepository()->softRevokeAccessTokenBySecret($this, $secretToken);
    }

    /**
     * Soft-revokes every access token for this user.
     */
    public function revokeAllAccessTokens(): void
    {
        $this->accessTokenRepository()->softRevokeAllAccessTokens($this);
    }

    /**
     * Retrieves all personal access tokens for this user.
     *
     * @return list<AccessToken>
     */
    public function accessTokens(): array
    {
        return $this->accessTokenRepository()->getAllAccessTokens($this);
    }

    /**
     * Given a raw token, will hash it and attempt to
     * locate it within the system.
     */
    public function getAccessToken(?string $rawToken): ?AccessToken
    {
        if ($rawToken === null || $rawToken === '' || $rawToken === '0') {
            return null;
        }

        return $this->accessTokenRepository()->getAccessToken($this, $rawToken);
    }

    /**
     * Given the ID, returns the given access token.
     */
    public function getAccessTokenById(int $id): ?AccessToken
    {
        return $this->accessTokenRepository()->getAccessTokenById($id, $this);
    }

    /**
     * Determines whether the user's token grants permissions to $scope.
     * First checks against $this->activeToken, which is set during
     * authentication. If it hasn't been set, returns false.
     */
    public function tokenCan(string $scope): bool
    {
        if (! $this->currentAccessToken() instanceof AccessToken) {
            return false;
        }

        return $this->currentAccessToken()->can($scope);
    }

    /**
     * Determines whether the user's token does NOT grant permissions to $scope.
     * First checks against $this->activeToken, which is set during
     * authentication. If it hasn't been set, returns true.
     */
    public function tokenCant(string $scope): bool
    {
        if (! $this->currentAccessToken() instanceof AccessToken) {
            return true;
        }

        return $this->currentAccessToken()->cant($scope);
    }

    /**
     * Returns the current access token for the user.
     */
    public function currentAccessToken(): ?AccessToken
    {
        return $this->currentAccessToken;
    }

    /**
     * Sets the current active token for this user.
     *
     * @return $this
     */
    public function setAccessToken(?AccessToken $accessToken): self
    {
        $this->currentAccessToken = $accessToken;

        return $this;
    }
}
