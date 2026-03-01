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

namespace Tests\Authentication\Filters;

use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Daycry\Auth\Filters\RatesFilter;
use Tests\Support\FilterTestCase;

/**
 * @internal
 */
final class RatesFilterTest extends FilterTestCase
{
    use FeatureTestTrait;

    protected string $alias     = 'rates';
    protected string $classname = RatesFilter::class;

    protected function setUp(): void
    {
        Services::reset(true);

        parent::setUp();

        $_SESSION = [];

        // Ensure discovery is disabled so checkEndpoint() returns null
        setting('Auth.enableDiscovery', false);
    }

    public function testFilterAllowsRequestWithinLimit(): void
    {
        // Set a generous limit
        setting('AuthSecurity.requestLimit', 10);
        setting('AuthSecurity.timeLimit', 60);
        setting('AuthSecurity.limitMethod', 'ROUTED_URL');

        $result = $this->get('protected-route');

        $result->assertStatus(200);
        $result->assertSee('Protected');
    }

    public function testFilterBlocksRequestOverLimit(): void
    {
        // Set a very low limit
        setting('AuthSecurity.requestLimit', 1);
        setting('AuthSecurity.timeLimit', 60);
        setting('AuthSecurity.limitMethod', 'ROUTED_URL');

        // First request should succeed
        $result = $this->get('protected-route');
        $result->assertStatus(200);

        // Second request should be throttled
        $result = $this->get('protected-route');
        $result->assertStatus(429);
    }

    public function testFilterOpenRouteIsNotAffected(): void
    {
        $result = $this->get('open-route');

        $result->assertStatus(200);
        $result->assertSee('Open');
    }

    public function testFilterWithIpAddressLimitMethod(): void
    {
        setting('AuthSecurity.requestLimit', 1);
        setting('AuthSecurity.timeLimit', 60);
        setting('AuthSecurity.limitMethod', 'IP_ADDRESS');

        // First request should succeed
        $result = $this->get('protected-route');
        $result->assertStatus(200);

        // Second request from same IP should be throttled
        $result = $this->get('protected-route');
        $result->assertStatus(429);
    }

    public function testFilterUsesDefaultLimitWhenNotConfigured(): void
    {
        // Don't set custom limits; defaults are 10 requests / 60 seconds
        setting('AuthSecurity.limitMethod', 'ROUTED_URL');

        // Should succeed with default limits
        $result = $this->get('protected-route');
        $result->assertStatus(200);
    }
}
