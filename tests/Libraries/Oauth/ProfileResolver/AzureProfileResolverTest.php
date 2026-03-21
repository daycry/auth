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

use Daycry\Auth\Libraries\Oauth\ProfileResolver\AzureProfileResolver;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\GenericResourceOwner;
use League\OAuth2\Client\Token\AccessToken;
use Mockery;
use RuntimeException;
use Tests\Support\TestCase;
use TheNetworg\OAuth2\Client\Provider\Azure;

/**
 * @internal
 */
final class AzureProfileResolverTest extends TestCase
{
    private AzureProfileResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new AzureProfileResolver();
    }

    public function testFetchFieldsFromGraph(): void
    {
        $token         = new AccessToken(['access_token' => 'test_token']);
        $resourceOwner = Mockery::mock(GenericResourceOwner::class);

        $provider = Mockery::mock(Azure::class);
        $provider->shouldReceive('get')
            ->withArgs(static fn (string $url, $tkn) => str_contains($url, 'graph.microsoft.com/v1.0/me')
                    && str_contains($url, '$select=department,jobTitle'))
            ->andReturn([
                '@odata.context' => 'https://graph.microsoft.com/v1.0/$metadata#users/$entity',
                'department'     => 'Engineering',
                'jobTitle'       => 'Developer',
                'extraField'     => 'should_be_ignored',
            ]);

        $result = $this->resolver->fetchFields(
            $provider,
            $token,
            $resourceOwner,
            ['department', 'jobTitle'],
        );

        $this->assertSame(['department' => 'Engineering', 'jobTitle' => 'Developer'], $result);
    }

    public function testFiltersOdataKeys(): void
    {
        $token         = new AccessToken(['access_token' => 'test_token']);
        $resourceOwner = Mockery::mock(GenericResourceOwner::class);

        $provider = Mockery::mock(Azure::class);
        $provider->shouldReceive('get')
            ->andReturn([
                '@odata.context'  => 'some_context',
                '@odata.nextLink' => 'some_link',
                'department'      => 'Sales',
            ]);

        $result = $this->resolver->fetchFields(
            $provider,
            $token,
            $resourceOwner,
            ['department'],
        );

        $this->assertArrayNotHasKey('@odata.context', $result);
        $this->assertArrayNotHasKey('@odata.nextLink', $result);
        $this->assertSame('Sales', $result['department']);
    }

    public function testReturnsEmptyForNonAzureProvider(): void
    {
        $token         = new AccessToken(['access_token' => 'test_token']);
        $resourceOwner = Mockery::mock(GenericResourceOwner::class);
        $provider      = Mockery::mock(AbstractProvider::class);

        $result = $this->resolver->fetchFields(
            $provider,
            $token,
            $resourceOwner,
            ['department'],
        );

        $this->assertSame([], $result);
    }

    public function testReturnsEmptyWhenGraphFails(): void
    {
        $token         = new AccessToken(['access_token' => 'test_token']);
        $resourceOwner = Mockery::mock(GenericResourceOwner::class);

        $provider = Mockery::mock(Azure::class);
        $provider->shouldReceive('get')
            ->andThrow(new RuntimeException('Graph API error'));

        $this->expectException(RuntimeException::class);

        $this->resolver->fetchFields(
            $provider,
            $token,
            $resourceOwner,
            ['department'],
        );
    }
}
