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

use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Daycry\Auth\Auth as AuthService;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class MagicCodeViewTest extends DatabaseTestCase
{
    use FeatureTestTrait;

    protected $namespace = 'Daycry\Auth';

    protected function setUp(): void
    {
        parent::setUp();
        setting('AuthSecurity.allowMagicLinkLogins', true);

        $routes = service('routes');
        (new AuthService(config('Auth')))->routes($routes);
        Services::injectMock('routes', $routes);
    }

    public function testCodeViewRedirectsWithoutPendingEmail(): void
    {
        $result = $this->get('login/magic-link/code');
        $result->assertRedirectTo(route_to('magic-link'));
    }

    public function testCodeViewRendersWithPendingEmail(): void
    {
        $result = $this->withSession(['magicCodeEmail' => 'otp@example.com'])
            ->get('login/magic-link/code');

        $result->assertStatus(200);
        $result->assertSee(lang('Auth.magicCodeTitle'));
    }
}
