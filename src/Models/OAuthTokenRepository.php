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
use Daycry\Auth\Enums\IdentityType;

/**
 * Repository for OAuth identity operations.
 *
 * Encapsulates all OAuth token/identity CRUD from UserIdentityModel
 * into a focused, single-responsibility class.
 */
class OAuthTokenRepository
{
    public function __construct(
        private readonly UserIdentityModel $identityModel,
    ) {
    }

    /**
     * Find an OAuth identity for a given user and provider.
     */
    public function findByUserAndProvider(int $userId, string $provider): ?UserIdentity
    {
        /** @var UserIdentity|null */
        return $this->identityModel
            ->where('user_id', $userId)
            ->where('type', IdentityType::oauthProvider($provider))
            ->first();
    }

    /**
     * Find an OAuth identity by provider and social ID (the 'secret' column).
     */
    public function findByProviderAndSocialId(string $provider, string $socialId): ?UserIdentity
    {
        /** @var UserIdentity|null */
        return $this->identityModel
            ->where('type', IdentityType::oauthProvider($provider))
            ->where('secret', $socialId)
            ->first();
    }

    /**
     * Create a new OAuth identity row.
     *
     * @param array<string, mixed> $data Must include: name, secret, secret2, extra, expires (nullable)
     */
    public function createOAuthIdentity(int $userId, string $provider, array $data): void
    {
        $this->identityModel->insert(array_merge($data, [
            'user_id' => $userId,
            'type'    => IdentityType::oauthProvider($provider),
        ]));
    }

    /**
     * Update an existing OAuth identity (token refresh, re-login, etc.).
     */
    public function updateOAuthIdentity(UserIdentity $identity): void
    {
        $this->identityModel->save($identity);
    }

    /**
     * Get the stored profile data for a user's OAuth identity.
     *
     * @return array<string, mixed>
     */
    public function getProfileData(int $userId, string $provider): array
    {
        $identity = $this->findByUserAndProvider($userId, $provider);

        if ($identity === null || empty($identity->extra)) {
            return [];
        }

        $extraData = $this->parseExtra($identity->extra);

        return $extraData['profile'] ?? [];
    }

    /**
     * Parse the extra field from an OAuth identity.
     *
     * Handles backward compatibility: legacy format stored the refresh token
     * as a plain string, new format uses JSON.
     *
     * @return array<string, mixed>
     */
    public function parseExtra(?string $extra): array
    {
        if ($extra === null || $extra === '') {
            return [];
        }

        $decoded = json_decode($extra, true);

        if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Legacy: plain string is the refresh token
        return ['refresh_token' => $extra];
    }
}
