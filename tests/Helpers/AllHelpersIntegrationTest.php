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

use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class AllHelpersIntegrationTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Load all helpers
        helper(['auth', 'checkEndpoint', 'checkIp', 'email']);
    }

    public function testAllHelperFunctionsExist(): void
    {
        // auth_helper functions
        $this->assertTrue(function_exists('auth'));
        $this->assertTrue(function_exists('user_id'));

        // checkEndpoint_helper functions
        $this->assertTrue(function_exists('checkEndpoint'));

        // checkIp_helper functions
        $this->assertTrue(function_exists('checkIp'));

        // email_helper functions
        $this->assertTrue(function_exists('emailer'));
    }

    public function testAuthHelperFunctionality(): void
    {
        // Test auth() function
        $auth = auth();
        $this->assertNotNull($auth);

        // Test user_id() function
        $userId = user_id();
        $this->assertNull($userId); // No user logged in by default
    }

    public function testCheckEndpointHelperFunctionality(): void
    {
        // Test checkEndpoint() function
        $endpoint = checkEndpoint();
        $this->assertNull($endpoint); // No endpoint configured by default
    }

    public function testCheckIpHelperFunctionality(): void
    {
        // Test various IP checking scenarios
        $testCases = [
            // [ip, allowed_ips, expected_result]
            ['192.168.1.1', ['192.168.1.1'], true],
            ['192.168.1.1', ['192.168.1.2'], false],
            ['192.168.1.1', ['192.168.1.0/24'], true],
            ['192.168.1.1', ['192.168.1.1-192.168.1.10'], true],
            ['192.168.1.1', ['192.168.1.*'], true],
            ['192.168.1.1', [], false],
            ['10.0.0.1', ['192.168.1.1', '10.0.0.1'], true],
        ];

        foreach ($testCases as [$ip, $allowedIps, $expected]) {
            $result = checkIp($ip, $allowedIps);
            $this->assertSame($expected, $result, "Failed for IP: {$ip}");
        }
    }

    public function testEmailHelperFunctionality(): void
    {
        // Test emailer() function
        $email = emailer();
        $this->assertNotNull($email);

        // Test emailer with config
        $email = emailer(['protocol' => 'mail']);
        $this->assertNotNull($email);
    }

    public function testHelperErrorHandling(): void
    {
        // Test that helpers handle edge cases gracefully

        // Empty IP array
        $result = checkIp('192.168.1.1', []);
        $this->assertFalse($result);

        // Empty overrides array
        $email = emailer([]);
        $this->assertNotNull($email);

        // Non-existent endpoint
        $endpoint = checkEndpoint();
        $this->assertNull($endpoint);
    }
}
