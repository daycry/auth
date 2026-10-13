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

namespace Tests\Helpers;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Auth\Libraries\CheckIpInRange;

/**
 * @internal
 */
final class CheckIpHelperTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Load the helper
        helper('checkIp');
    }

    public function testCheckIpFunction(): void
    {
        $this->assertTrue(function_exists('checkIp'));
    }

    public function testCheckIpMatchesIpv6Cidr(): void
    {
        $this->assertTrue(checkIp('2001:db8:1::1', ['2001:db8::/32']));
    }

    public function testCheckIpRejectsIpv6OutsideCidr(): void
    {
        // Regression: IPv6 entries were routed to the IPv4 matcher, where both
        // sides evaluated to 0 and every IPv6 client matched every IPv6 CIDR.
        $this->assertFalse(checkIp('2001:db9::1', ['2001:db8::/32']));
    }

    public function testCheckIpMatchesBareIpv6RegardlessOfNotation(): void
    {
        $this->assertTrue(checkIp('2001:DB8:0:0:0:0:0:1', ['2001:db8::1']));
        $this->assertFalse(checkIp('2001:db8::2', ['2001:db8::1']));
    }

    public function testCheckIpRejectsIpv6ClientAgainstIpv4Ranges(): void
    {
        $this->assertFalse(checkIp('2001:db8::1', ['0.0.0.0/8']));
        $this->assertFalse(checkIp('2001:db8::1', ['0.0.0.0-10.0.0.0']));
        $this->assertFalse(checkIp('2001:db8::1', ['0.0.*.*']));
    }

    public function testCheckIpRejectsIpv4ClientAgainstIpv6Ranges(): void
    {
        $this->assertFalse(checkIp('192.168.1.1', ['::/0']));
    }

    public function testCheckIpRejectsInvalidClientAddress(): void
    {
        $this->assertFalse(checkIp('garbage', ['0.0.0.0/8', '2001:db8::/32']));
    }

    public function testCheckIpWithMixedIpv4AndIpv6Entries(): void
    {
        $ips = ['10.0.0.0/8', '2001:db8::/32'];

        $this->assertTrue(checkIp('10.1.2.3', $ips));
        $this->assertTrue(checkIp('2001:db8::42', $ips));
        $this->assertFalse(checkIp('192.168.1.1', $ips));
        $this->assertFalse(checkIp('2001:db9::42', $ips));
    }

    public function testCheckIpWithExactMatch(): void
    {
        $ip  = '192.168.1.1';
        $ips = ['192.168.1.1', '10.0.0.1'];

        $result = checkIp($ip, $ips);
        $this->assertTrue($result);
    }

    public function testCheckIpWithNoMatch(): void
    {
        $ip  = '192.168.1.1';
        $ips = ['192.168.1.2', '10.0.0.1'];

        $result = checkIp($ip, $ips);
        $this->assertFalse($result);
    }

    public function testCheckIpWithCidrRange(): void
    {
        $ip  = '192.168.1.1';
        $ips = ['192.168.1.0/24'];

        $result = checkIp($ip, $ips);
        $this->assertTrue($result);
    }

    public function testCheckIpWithDashRange(): void
    {
        $ip  = '192.168.1.5';
        $ips = ['192.168.1.1-192.168.1.10'];

        $result = checkIp($ip, $ips);
        $this->assertTrue($result);
    }

    public function testCheckIpWithWildcard(): void
    {
        $ip  = '192.168.1.1';
        $ips = ['192.168.1.*'];

        $result = checkIp($ip, $ips);
        $this->assertTrue($result);
    }

    public function testCheckIpWithMultipleIps(): void
    {
        $ip  = '10.0.0.1';
        $ips = ['192.168.1.1', '10.0.0.1', '172.16.0.1'];

        $result = checkIp($ip, $ips);
        $this->assertTrue($result);
    }

    public function testCheckIpWithEmptyArray(): void
    {
        $ip  = '192.168.1.1';
        $ips = [];

        $result = checkIp($ip, $ips);
        $this->assertFalse($result);
    }

    public function testCheckIpWithWhitespace(): void
    {
        $ip  = '192.168.1.1';
        $ips = [' 192.168.1.1 ', '10.0.0.1'];

        $result = checkIp($ip, $ips);
        $this->assertTrue($result);
    }

    public function testCheckIpBreaksOnFirstMatch(): void
    {
        $ip  = '192.168.1.1';
        $ips = ['192.168.1.1', '192.168.1.0/24']; // Both would match

        $result = checkIp($ip, $ips);
        $this->assertTrue($result);
    }

    public function testCheckIpWithInvalidRange(): void
    {
        $ip  = '192.168.1.1';
        $ips = ['192.168.2.0/24']; // IP not in range

        $result = checkIp($ip, $ips);
        $this->assertFalse($result);
    }

    public function testCheckIpDependencies(): void
    {
        // Test that required class exists
        $this->assertTrue(class_exists(CheckIpInRange::class));
    }

    public function testCheckIpWithComplexRanges(): void
    {
        $testCases = [
            ['192.168.1.1', ['192.168.1.0/24'], true],
            ['192.168.1.1', ['192.168.2.0/24'], false],
            ['192.168.1.5', ['192.168.1.1-192.168.1.10'], true],
            ['192.168.1.15', ['192.168.1.1-192.168.1.10'], false],
            ['192.168.1.1', ['192.168.1.*'], true],
            ['192.168.2.1', ['192.168.1.*'], false],
        ];

        foreach ($testCases as [$ip, $ips, $expected]) {
            $result = checkIp($ip, $ips);
            $this->assertSame($expected, $result, "Failed for IP: {$ip} with ranges: " . implode(', ', $ips));
        }
    }

    public function testCheckIpReturnType(): void
    {
        $ip  = '192.168.1.1';
        $ips = ['192.168.1.1'];

        $result = checkIp($ip, $ips);
        $this->assertIsBool($result);
    }
}
