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
final class RegisterControllerTest extends DatabaseTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $routes = service('routes');
        (new AuthService(config('Auth')))->routes($routes);
        Services::injectMock('routes', $routes);

        setting('Auth.allowRegistration', false);
    }

    protected function tearDown(): void
    {
        setting()->forget('Auth.allowRegistration');

        parent::tearDown();
    }

    /**
     * Redirecting to the register page itself would loop forever.
     */
    public function testDisabledRegistrationPageRedirectsToLogin(): void
    {
        $result = $this->get('register');

        $result->assertRedirectTo(config('Auth')->loginRoute());
        $result->assertSessionHas('error', lang('Auth.registerDisabled'));
    }

    public function testDisabledRegistrationSubmitRedirectsToLogin(): void
    {
        $result = $this->post('register', [
            'email'            => 'new@example.com',
            'username'         => 'newuser',
            'password'         => 'Brand-New-Pass-123',
            'password_confirm' => 'Brand-New-Pass-123',
        ]);

        $result->assertRedirectTo(config('Auth')->loginRoute());
        $result->assertSessionHas('error', lang('Auth.registerDisabled'));
        $this->dontSeeInDatabase($this->tables['identities'], ['secret' => 'new@example.com']);
    }
}
