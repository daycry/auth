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
use Tests\Support\WebAuthn\SuppressesWebauthnDeprecations;
use Tests\Support\WebAuthn\VirtualAuthenticator;

/**
 * @internal
 */
final class WebAuthnControllerTest extends DatabaseTestCase
{
    use FeatureTestTrait;
    use SuppressesWebauthnDeprecations;

    protected $namespace = 'Daycry\Auth';

    protected function setUp(): void
    {
        parent::setUp();
        setting('AuthSecurity.webauthnEnabled', true);
        setting('AuthSecurity.webauthnRelyingPartyId', 'example.com');
        setting('AuthSecurity.webauthnRelyingPartyName', 'Example');
        setting('AuthSecurity.webauthnAllowedOrigins', ['https://example.com']);

        $routes = service('routes');
        (new AuthService(config('Auth')))->routes($routes);
        Services::injectMock('routes', $routes);

        $this->suppressWebauthnDeprecations();
    }

    protected function tearDown(): void
    {
        $this->restoreWebauthnDeprecations();
        parent::tearDown();
    }

    public function testRegisterOptionsRequiresLogin(): void
    {
        $result = $this->post('webauthn/register/options');
        $result->assertStatus(403);
    }

    public function testFullPasswordlessRoundTrip(): void
    {
        $user = fake(UserModel::class);

        // Enrol while logged in (session-based auth, as the repo's feature tests do).
        $optionsResult = $this->withSession(['user' => ['id' => $user->id]])
            ->post('webauthn/register/options', ['name' => 'Key']);
        $optionsResult->assertStatus(200);
        $options = json_decode($optionsResult->getJSON(), true);

        $authn        = new VirtualAuthenticator('example.com', 'https://example.com');
        $registerJson = $authn->register(json_encode($options, JSON_THROW_ON_ERROR));

        // The single-use challenge lives in the PHP session; carry it (and the
        // authenticated user) forward to the verify request.
        $verify = $this->withSession($_SESSION)
            ->withBodyFormat('json')
            ->post('webauthn/register/verify', [
                'credential' => json_decode($registerJson, true),
            ]);
        $verify->assertStatus(201);

        // Clear the authenticated session, then passwordless login.
        $loginOptions = $this->withSession([])->post('webauthn/login/options');
        $loginOptions->assertStatus(200);
        $reqOptions = json_decode($loginOptions->getJSON(), true);

        // Carry the freshly-stored login challenge forward (still unauthenticated:
        // $_SESSION holds only the ceremony entry, no `user` key).
        $assertionJson = $authn->login(json_encode($reqOptions, JSON_THROW_ON_ERROR), (string) $user->uuid);
        $loginVerify   = $this->withSession($_SESSION)->withBodyFormat('json')->post('webauthn/login/verify', [
            'credential' => json_decode($assertionJson, true),
        ]);
        $loginVerify->assertStatus(200);
        $loginVerify->assertJSONFragment(['status' => 'ok']);
    }

    public function testEndpoints404WhenDisabled(): void
    {
        setting('AuthSecurity.webauthnEnabled', false);

        $routes = service('routes');
        (new AuthService(config('Auth')))->routes($routes);
        Services::injectMock('routes', $routes);

        $this->post('webauthn/login/options')->assertStatus(404);
    }
}
