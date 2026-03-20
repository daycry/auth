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

use Daycry\Auth\Libraries\Oauth\ProfileResolver\GenericProfileResolver;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\GenericResourceOwner;
use League\OAuth2\Client\Token\AccessToken;
use Mockery;
use Psr\Http\Message\RequestInterface;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class GenericProfileResolverTest extends TestCase
{
    private GenericProfileResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new GenericProfileResolver();
    }

    public function testFilterFromResourceOwner(): void
    {
        $token = new AccessToken(['access_token' => 'test_token']);

        $resourceOwner = Mockery::mock(GenericResourceOwner::class);
        $resourceOwner->shouldReceive('toArray')->andReturn([
            'id'        => '123',
            'email'     => 'user@example.com',
            'name'      => 'Test User',
            'role'      => 'admin',
            'team'      => 'backend',
            'unrelated' => 'value',
        ]);

        $provider = Mockery::mock(AbstractProvider::class);

        $result = $this->resolver->fetchFields(
            $provider,
            $token,
            $resourceOwner,
            ['role', 'team'],
        );

        $this->assertSame(['role' => 'admin', 'team' => 'backend'], $result);
    }

    public function testFilterIgnoresMissingFields(): void
    {
        $token = new AccessToken(['access_token' => 'test_token']);

        $resourceOwner = Mockery::mock(GenericResourceOwner::class);
        $resourceOwner->shouldReceive('toArray')->andReturn([
            'id'   => '123',
            'role' => 'editor',
        ]);

        $provider = Mockery::mock(AbstractProvider::class);

        $result = $this->resolver->fetchFields(
            $provider,
            $token,
            $resourceOwner,
            ['role', 'nonexistent'],
        );

        $this->assertSame(['role' => 'editor'], $result);
    }

    public function testFetchFromFieldsEndpoint(): void
    {
        $token         = new AccessToken(['access_token' => 'test_token']);
        $resourceOwner = Mockery::mock(GenericResourceOwner::class);

        $mockRequest = Mockery::mock(RequestInterface::class);

        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('getAuthenticatedRequest')
            ->with('GET', 'https://api.example.com/userinfo', $token)
            ->andReturn($mockRequest);
        $provider->shouldReceive('getParsedResponse')
            ->with($mockRequest)
            ->andReturn([
                'role'      => 'manager',
                'team'      => 'platform',
                'unrelated' => 'ignored',
            ]);

        $result = $this->resolver->fetchFields(
            $provider,
            $token,
            $resourceOwner,
            ['role', 'team'],
            ['fieldsEndpoint' => 'https://api.example.com/userinfo'],
        );

        $this->assertSame(['role' => 'manager', 'team' => 'platform'], $result);
    }

    public function testFieldsEndpointReturnsEmptyOnNonArrayResponse(): void
    {
        $token         = new AccessToken(['access_token' => 'test_token']);
        $resourceOwner = Mockery::mock(GenericResourceOwner::class);

        $mockRequest = Mockery::mock(RequestInterface::class);

        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('getAuthenticatedRequest')->andReturn($mockRequest);
        $provider->shouldReceive('getParsedResponse')->andReturn('not an array');

        $result = $this->resolver->fetchFields(
            $provider,
            $token,
            $resourceOwner,
            ['role'],
            ['fieldsEndpoint' => 'https://api.example.com/userinfo'],
        );

        $this->assertSame([], $result);
    }

    public function testEmptyFieldsEndpointFallsBackToResourceOwner(): void
    {
        $token = new AccessToken(['access_token' => 'test_token']);

        $resourceOwner = Mockery::mock(GenericResourceOwner::class);
        $resourceOwner->shouldReceive('toArray')->andReturn([
            'role' => 'viewer',
        ]);

        $provider = Mockery::mock(AbstractProvider::class);

        $result = $this->resolver->fetchFields(
            $provider,
            $token,
            $resourceOwner,
            ['role'],
            ['fieldsEndpoint' => ''],
        );

        $this->assertSame(['role' => 'viewer'], $result);
    }
}
