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
     * Generates a new personal access token for this user.
     *
     * @param string       $name   Token name
     * @param list<string> $scopes Permissions the token grants
     */
    public function generateAccessToken(string $name, array $scopes = ['*']): AccessToken
    {
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        return $identityModel->generateAccessToken($this, $name, $scopes);
    }

    /**
     * Delete any access tokens for the given raw token.
     */
    public function revokeAccessToken(string $rawToken): void
    {
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        $identityModel->revokeAccessToken($this, $rawToken);
    }

    /**
     * Delete any access tokens for the given secret token.
     */
    public function revokeAccessTokenBySecret(string $secretToken): void
    {
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        $identityModel->revokeAccessTokenBySecret($this, $secretToken);
    }

    /**
     * Revokes all access tokens for this user.
     */
    public function revokeAllAccessTokens(): void
    {
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        $identityModel->revokeAllAccessTokens($this);
    }

    /**
     * Retrieves all personal access tokens for this user.
     *
     * @return list<AccessToken>
     */
    public function accessTokens(): array
    {
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        return $identityModel->getAllAccessTokens($this);
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

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        return $identityModel->getAccessToken($this, $rawToken);
    }

    /**
     * Given the ID, returns the given access token.
     */
    public function getAccessTokenById(int $id): ?AccessToken
    {
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        return $identityModel->getAccessTokenById($id, $this);
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
