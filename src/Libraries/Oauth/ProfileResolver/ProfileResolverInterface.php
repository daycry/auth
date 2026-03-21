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

namespace Daycry\Auth\Libraries\Oauth\ProfileResolver;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessTokenInterface;

interface ProfileResolverInterface
{
    /**
     * Fetch additional profile fields from the provider's API.
     *
     * @param list<string>         $fields The field names to retrieve
     * @param array<string, mixed> $config Provider configuration from AuthOAuth
     *
     * @return array<string, mixed> The resolved field values
     */
    public function fetchFields(
        AbstractProvider $provider,
        AccessTokenInterface $token,
        ResourceOwnerInterface $resourceOwner,
        array $fields,
        array $config = [],
    ): array;
}
