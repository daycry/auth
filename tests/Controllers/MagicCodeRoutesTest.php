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

namespace Tests\Controllers;

use Config\Services;
use Daycry\Auth\Auth as AuthService;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class MagicCodeRoutesTest extends TestCase
{
    public function testCodeRouteIsRegisteredAndViewKeysExist(): void
    {
        $routes = service('routes');
        (new AuthService(config('Auth')))->routes($routes);
        Services::injectMock('routes', $routes);

        $this->assertNotFalse(route_to('magic-link-code'));
        $this->assertArrayHasKey('magic-link-code', setting('Auth.views'));
        $this->assertArrayHasKey('magic-link-code-email', setting('Auth.views'));
    }
}
