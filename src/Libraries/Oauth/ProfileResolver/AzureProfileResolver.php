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
use TheNetworg\OAuth2\Client\Provider\Azure;

class AzureProfileResolver implements ProfileResolverInterface
{
    /**
     * Fetch fields from Microsoft Graph API.
     *
     * Uses the Azure provider's get() method which handles token refresh
     * internally. Filters out @odata.* metadata keys from the response.
     *
     * @param list<string>         $fields
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function fetchFields(
        AbstractProvider $provider,
        AccessTokenInterface $token,
        ResourceOwnerInterface $resourceOwner,
        array $fields,
        array $config = [],
    ): array {
        if (! $provider instanceof Azure) {
            return [];
        }

        $select = implode(',', $fields);
        $url    = 'https://graph.microsoft.com/v1.0/me?$select=' . $select;

        /** @var array<string, mixed> $response */
        $response = $provider->get($url, $token);

        $result = [];

        foreach ($response as $key => $value) {
            // Skip OData metadata keys
            if (str_starts_with($key, '@odata.')) {
                continue;
            }

            if (in_array($key, $fields, true)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
