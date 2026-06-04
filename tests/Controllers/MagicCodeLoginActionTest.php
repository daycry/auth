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
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class MagicCodeLoginActionTest extends DatabaseTestCase
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

    public function testCodeDeliveryCreatesHashedCodeAndRedirectsToCodeForm(): void
    {
        $user        = fake(UserModel::class);
        $user->email = 'otp@example.com';
        model(UserModel::class)->save($user);

        $result = $this->post('login/magic-link', ['email' => 'otp@example.com', 'delivery' => 'code']);

        $result->assertRedirectTo(route_to('magic-link-code'));
        $this->seeInDatabase($this->tables['identities'], [
            'user_id' => $user->id,
            'type'    => 'magic_code',
        ]);
    }

    public function testCodeDeliveryUnknownEmailDoesNotLeak(): void
    {
        $result = $this->post('login/magic-link', ['email' => 'ghost@example.com', 'delivery' => 'code']);

        // Same redirect as a real account; no identity created.
        $result->assertRedirectTo(route_to('magic-link-code'));
        $this->dontSeeInDatabase($this->tables['identities'], ['type' => 'magic_code']);
    }

    public function testLinkDeliveryUnknownEmailNoLongerLeaks(): void
    {
        // Anti-enumeration fix: unknown email goes to the generic message page,
        // not an "invalid email" error.
        $result = $this->post('login/magic-link', ['email' => 'ghost@example.com', 'delivery' => 'link']);

        $result->assertRedirectTo(route_to('magic-link-message'));
        $result->assertSessionMissing('error');
    }

    public function testCodeModeDisabledIsRejected(): void
    {
        setting('AuthSecurity.magicLinkEnableCode', false);

        $result = $this->post('login/magic-link', ['email' => 'otp@example.com', 'delivery' => 'code']);

        $result->assertSessionHas('error');
    }
}
