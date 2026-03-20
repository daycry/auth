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

use CodeIgniter\Exceptions\LogicException;

class ProfileResolverFactory
{
    /**
     * Map of provider alias to resolver class.
     *
     * @var array<string, class-string<ProfileResolverInterface>>
     */
    private static array $resolverMap = [
        'azure' => AzureProfileResolver::class,
    ];

    /**
     * Create the appropriate profile resolver for a provider.
     *
     * Resolution order:
     *   1. $providerConfig['profileResolver'] (custom class — must implement ProfileResolverInterface)
     *   2. Static map (azure → AzureProfileResolver)
     *   3. Fallback → GenericProfileResolver
     *
     * @param array<string, mixed> $providerConfig Provider configuration from AuthOAuth
     */
    public static function create(string $providerName, array $providerConfig = []): ProfileResolverInterface
    {
        // 1. Config-based custom resolver
        if (isset($providerConfig['profileResolver'])) {
            $class = $providerConfig['profileResolver'];

            if (! is_subclass_of($class, ProfileResolverInterface::class)) {
                throw new LogicException(
                    sprintf('Profile resolver "%s" must implement %s.', $class, ProfileResolverInterface::class),
                );
            }

            return new $class();
        }

        // 2. Built-in map
        $class = self::$resolverMap[$providerName] ?? GenericProfileResolver::class;

        return new $class();
    }
}
