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
 * Application contract for replacing OAuthTokenRepository without inheritance.
 */
interface OAuthTokenRepositoryInterface
{
    /**
     * Find an OAuth identity for a given user and provider.
     */
    public function findByUserAndProvider(int $userId, string $provider): ?UserIdentity;

    /**
     * Find an OAuth identity by provider and social ID (the 'secret' column).
     */
    public function findByProviderAndSocialId(string $provider, string $socialId): ?UserIdentity;

    /**
     * Create a new OAuth identity row.
     *
     * @param array<string, mixed> $data Must include: name, secret, secret2, extra, expires (nullable)
     */
    public function createOAuthIdentity(int $userId, string $provider, array $data): void;

    /**
     * Update an existing OAuth identity (token refresh, re-login, etc.).
     */
    public function updateOAuthIdentity(UserIdentity $identity): void;

    /**
     * Get the stored profile data for a user's OAuth identity.
     *
     * @return array<string, mixed>
     */
    public function getProfileData(int $userId, string $provider): array;

    /**
     * Parse the extra field from an OAuth identity.
     *
     * Handles backward compatibility: legacy format stored the refresh token
     * as a plain string, new format uses JSON.
     *
     * @return array<string, mixed>
     */
    public function parseExtra(?string $extra): array;
}
