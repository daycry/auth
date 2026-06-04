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
final class MagicCodeViewsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $routes = service('routes');
        (new AuthService(config('Auth')))->routes($routes);
        Services::injectMock('routes', $routes);
    }

    public function testCodeFormPostsToVerifyAndHasCodeField(): void
    {
        $html = view(setting('Auth.views')['magic-link-code']);
        $this->assertStringContainsString(site_url('login/magic-link/code'), $html);
        $this->assertStringContainsString('name="token"', $html);
    }

    public function testCodeEmailRendersTheCode(): void
    {
        $html = view(setting('Auth.views')['magic-link-code-email'], ['token' => '424242', 'user' => null]);
        $this->assertStringContainsString('424242', $html);
    }

    public function testLoginFormOffersBothDeliveryButtons(): void
    {
        $html = view(setting('Auth.views')['magic-link-login']);
        $this->assertStringContainsString('value="link"', $html);
        $this->assertStringContainsString('value="code"', $html);
    }
}
