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

class GenericProfileResolver implements ProfileResolverInterface
{
    /**
     * Fetch fields from a generic provider.
     *
     * If the provider config contains a 'fieldsEndpoint', performs an
     * authenticated GET request to that URL. Otherwise, filters the
     * resource owner's toArray() data to the requested fields.
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
        $fieldsEndpoint = $config['fieldsEndpoint'] ?? null;

        if (is_string($fieldsEndpoint) && $fieldsEndpoint !== '') {
            return $this->fetchFromEndpoint($provider, $token, $fields, $fieldsEndpoint);
        }

        return $this->filterFromResourceOwner($resourceOwner, $fields);
    }

    /**
     * Fetch fields from a custom endpoint using an authenticated request.
     *
     * @param list<string> $fields
     *
     * @return array<string, mixed>
     */
    private function fetchFromEndpoint(
        AbstractProvider $provider,
        AccessTokenInterface $token,
        array $fields,
        string $endpoint,
    ): array {
        $request  = $provider->getAuthenticatedRequest('GET', $endpoint, $token);
        $response = $provider->getParsedResponse($request);

        if (! is_array($response)) {
            return [];
        }

        $result = [];

        foreach ($fields as $field) {
            if (array_key_exists($field, $response)) {
                $result[$field] = $response[$field];
            }
        }

        return $result;
    }

    /**
     * Filter the resource owner's data to the requested fields.
     *
     * @param list<string> $fields
     *
     * @return array<string, mixed>
     */
    private function filterFromResourceOwner(ResourceOwnerInterface $resourceOwner, array $fields): array
    {
        $data   = $resourceOwner->toArray();
        $result = [];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $result[$field] = $data[$field];
            }
        }

        return $result;
    }
}
