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

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use Daycry\Auth\Auth;
use Daycry\Auth\Filters\RatesFilter;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class SecurityRatesTest extends TestCase
{
    public function testAuthRoutesAreLimitedByDefaultAndCanOptOut(): void
    {
        $routes = service('routes');
        $routes->resetRoutes();
        (new Auth(config('Auth')))->routes($routes);
        $this->assertContains('rates:auth', $routes->getFiltersForRoute('login', 'POST'));
    }

    public function testAuthRouteGroupCanOptOut(): void
    {
        $routes = service('routes');
        $routes->resetRoutes();
        (new Auth(config('Auth')))->routes($routes, ['routeFilters' => ['login' => false]]);
        $this->assertNotContains('rates:auth', $routes->getFiltersForRoute('login', 'POST'));
        $this->assertContains('rates:auth', $routes->getFiltersForRoute('register', 'POST'));
    }

    public function testIpv6AddressesWithinOnePrefixShareTheLimit(): void
    {
        $this->assertSame($this->key('2001:db8:1:2::1'), $this->key('2001:db8:1:2:ffff::2'));
        $this->assertNotSame($this->key('2001:db8:1:2::1'), $this->key('2001:db8:1:3::1'));
    }

    public function testAccountIpKeysSeparateAccountsAndNetworks(): void
    {
        $this->assertSame($this->key('192.0.2.1', ' A@example.com ', 'USER_IP'), $this->key('192.0.2.1', 'a@example.com', 'USER_IP'));
        $this->assertNotSame($this->key('192.0.2.1', 'a@example.com', 'USER_IP'), $this->key('192.0.2.1', 'b@example.com', 'USER_IP'));
        $this->assertNotSame($this->key('192.0.2.1', '', 'USER_IP'), $this->key('192.0.2.2', '', 'USER_IP'));
    }

    public function testJsonAccountsUseTheSameKeysAsFormsAndRemainIsolated(): void
    {
        $this->assertSame($this->key('192.0.2.1', 'alice@example.com', 'USER_IP'), $this->key('192.0.2.1', '', 'USER_IP', '{"email":" Alice@Example.com "}'));
        $this->assertNotSame($this->key('192.0.2.1', '', 'USER_IP', '{"email":"alice@example.com"}'), $this->key('192.0.2.1', '', 'USER_IP', '{"email":"bob@example.com"}'));
        $this->assertSame($this->key('192.0.2.1', '', 'USER_IP'), $this->key('192.0.2.1', '', 'USER_IP', '{broken'));
        $this->assertSame($this->key('192.0.2.1', '', 'USER_IP'), $this->key('192.0.2.1', '', 'USER_IP', '{"email":["alice@example.com"]}'));
    }

    private function key(string $ip, string $email = '', string $method = 'AUTH_IP', ?string $json = null): string
    {
        $request = $this->createMock(IncomingRequest::class);
        $request->method('getIPAddress')->willReturn($ip);
        $request->method('getPost')->willReturn($email);
        $request->method('is')->willReturn($json !== null);
        $request->method('getBody')->willReturn($json ?? '');
        $request->method('getUri')->willReturn(new URI('https://example.com/login'));
        $request->method('getMethod')->willReturn('POST');
        $filter = new class () extends RatesFilter {
            public function key(IncomingRequest $request, string $method): string
            {
                return $this->buildLimitedUri($request, null, $method);
            }
        };

        return $filter->key($request, $method);
    }
}
