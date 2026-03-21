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

namespace Tests\Libraries\Oauth\ProfileResolver;

use CodeIgniter\Exceptions\LogicException;
use Daycry\Auth\Libraries\Oauth\ProfileResolver\AzureProfileResolver;
use Daycry\Auth\Libraries\Oauth\ProfileResolver\GenericProfileResolver;
use Daycry\Auth\Libraries\Oauth\ProfileResolver\ProfileResolverFactory;
use stdClass;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class ProfileResolverFactoryTest extends TestCase
{
    public function testCreateAzureResolver(): void
    {
        $resolver = ProfileResolverFactory::create('azure');

        $this->assertInstanceOf(AzureProfileResolver::class, $resolver);
    }

    public function testCreateGenericFallback(): void
    {
        $resolver = ProfileResolverFactory::create('unknown_provider');

        $this->assertInstanceOf(GenericProfileResolver::class, $resolver);
    }

    public function testCreateConfigBasedResolver(): void
    {
        // Config-based resolver takes priority over static map
        $resolver = ProfileResolverFactory::create('azure', [
            'profileResolver' => GenericProfileResolver::class,
        ]);

        $this->assertInstanceOf(GenericProfileResolver::class, $resolver);
    }

    public function testCreateInvalidResolverThrows(): void
    {
        $this->expectException(LogicException::class);

        ProfileResolverFactory::create('test', [
            'profileResolver' => stdClass::class,
        ]);
    }
}
