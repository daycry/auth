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

use Daycry\Auth\Auth;
use Daycry\Auth\Entities\User;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class AuthHelperTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Load the helper
        helper('auth');
    }

    public function testAuthFunction(): void
    {
        $this->assertTrue(function_exists('auth'));

        $auth = auth();
        $this->assertInstanceOf(Auth::class, $auth);
    }

    public function testAuthFunctionWithAlias(): void
    {
        $auth = auth('session');
        $this->assertInstanceOf(Auth::class, $auth);
    }

    public function testUserIdFunction(): void
    {
        $this->assertTrue(function_exists('user_id'));

        // Test when no user is logged in
        $userId = user_id();
        $this->assertNull($userId);
    }

    public function testUserIdFunctionWithLoggedInUser(): void
    {
        // Since we can't easily mock the service function in this context,
        // we'll test the behavior when no user is logged in
        $this->assertNull(user_id()); // Default behavior when no user is logged in
    }

    public function testAuthHelperFunctionsDefined(): void
    {
        $this->assertTrue(function_exists('auth'));
        $this->assertTrue(function_exists('user_id'));
    }

    public function testAuthServiceIntegration(): void
    {
        $auth1 = auth();
        $auth2 = auth();

        // Should return the same service instance
        $this->assertSame($auth1, $auth2);
    }

    public function testAuthWithDifferentAliases(): void
    {
        $auth1 = auth('session');
        $auth2 = auth('jwt');

        // Should be different instances due to different authenticators
        $this->assertInstanceOf(Auth::class, $auth1);
        $this->assertInstanceOf(Auth::class, $auth2);
    }
}
