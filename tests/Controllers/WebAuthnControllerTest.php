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
use Daycry\Auth\Authentication\Authenticators\Session;
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

    public function testCredentialSyncRequiresLoginAndNeverAcceptsAnotherUserId(): void
    {
        $this->withRoutes([['get', 'webauthn/credentials/sync', '\Daycry\Auth\Controllers\WebAuthnController::credentialSignals']]);
        $this->get('webauthn/credentials/sync')->assertStatus(403);
        Services::resetSingle('auth');
        $user   = fake(UserModel::class);
        $other  = fake(UserModel::class);
        $result = $this->withSession(['user' => ['id' => $user->id]])->get('webauthn/credentials/sync?user_id=' . $other->id);
        $result->assertStatus(200);
        $result->assertJSONFragment(['userId' => rtrim(strtr(base64_encode((string) $user->uuid), '+/', '-_'), '=')]);
        $this->assertStringContainsString('no-store', $result->response()->getHeaderLine('Cache-Control'));
    }

    public function testConditionalRegistrationRequiresRecentInteractiveLogin(): void
    {
        setting('AuthSecurity.webauthnAutoUpgrade', true);
        $user = fake(UserModel::class);
        $this->withSession(['user' => ['id' => $user->id]])->withBodyFormat('json')->post('webauthn/register/options', ['conditional' => true])->assertStatus(403);
        $this->withSession(['user' => ['id' => $user->id], Session::AUTHENTICATED_AT_KEY => time()])->withBodyFormat('json')->post('webauthn/register/options', ['conditional' => true])->assertStatus(200);
    }

    public function testMandatoryMfaEnrollmentRequiresFreshPrimaryProofAndThenFactorProof(): void
    {
        setting('AuthSecurity.mfaRequired', true);
        $user = fake(UserModel::class);
        $this->withSession(['user' => ['id' => $user->id]])->post('webauthn/register/options')->assertStatus(403);
        $session = ['user' => ['id' => $user->id], Session::AUTHENTICATED_AT_KEY => time()];
        $result  = $this->withSession($session)->post('webauthn/register/options');
        $result->assertStatus(200);
        $authn = new VirtualAuthenticator('example.com', 'https://example.com');
        $json  = $authn->register($result->getJSON());
        $this->withSession($_SESSION)->withBodyFormat('json')->post('webauthn/register/verify', ['credential' => json_decode($json, true)])->assertStatus(201);
        $this->withSession($_SESSION)->post('webauthn/register/options')->assertStatus(403);
        service('mfaPolicy')->markVerified($user);
        $this->withSession($_SESSION)->post('webauthn/register/options')->assertStatus(200);
    }

    public function testPasskeyWithoutUserVerificationCannotSatisfyMandatoryMfa(): void
    {
        $user    = fake(UserModel::class);
        $manager = service('webAuthnManager');
        $authn   = new VirtualAuthenticator('example.com', 'https://example.com');
        $options = $manager->startRegistration($user, 'Key');
        $manager->finishRegistration($user, $authn->register(json_encode($options)));
        setting('AuthSecurity.mfaRequired', true);
        $result    = $this->withSession([])->post('webauthn/login/options');
        $assertion = $authn->login($result->getJSON(), (string) $user->uuid, false);
        $result    = $this->withSession($_SESSION)->withBodyFormat('json')->post('webauthn/login/verify', ['credential' => json_decode($assertion, true)]);
        $this->assertContains($result->response()->getStatusCode(), [403, 422]);
        $this->assertFalse(auth('session')->loggedIn());
        $this->assertFalse(service('mfaPolicy')->verified($user));
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

    public function testPasswordlessLoginRefusesBannedUser(): void
    {
        $user = fake(UserModel::class);

        $options = json_decode($this->withSession(['user' => ['id' => $user->id]])
            ->post('webauthn/register/options', ['name' => 'Key'])->getJSON(), true);

        $authn        = new VirtualAuthenticator('example.com', 'https://example.com');
        $registerJson = $authn->register(json_encode($options, JSON_THROW_ON_ERROR));
        $this->withSession($_SESSION)->withBodyFormat('json')->post('webauthn/register/verify', [
            'credential' => json_decode($registerJson, true),
        ])->assertStatus(201);

        model(UserModel::class)->findById($user->id)->ban('Passkey ban');

        $reqOptions    = json_decode($this->withSession([])->post('webauthn/login/options')->getJSON(), true);
        $assertionJson = $authn->login(json_encode($reqOptions, JSON_THROW_ON_ERROR), (string) $user->uuid);
        $loginVerify   = $this->withSession($_SESSION)->withBodyFormat('json')->post('webauthn/login/verify', [
            'credential' => json_decode($assertionJson, true),
        ]);

        $loginVerify->assertStatus(403);
        $loginVerify->assertJSONFragment(['error' => 'banned', 'message' => 'Passkey ban']);
        $this->assertArrayNotHasKey('id', $_SESSION[setting('Auth.sessionConfig')['field']] ?? []);
    }

    public function testDuplicateRegistrationReturns409(): void
    {
        $user = fake(UserModel::class);

        // First enrolment succeeds.
        $optionsResult = $this->withSession(['user' => ['id' => $user->id]])
            ->post('webauthn/register/options', ['name' => 'Key']);
        $optionsResult->assertStatus(200);
        $options = json_decode($optionsResult->getJSON(), true);

        $authn        = new VirtualAuthenticator('example.com', 'https://example.com');
        $registerJson = $authn->register(json_encode($options, JSON_THROW_ON_ERROR));

        $verify = $this->withSession($_SESSION)
            ->withBodyFormat('json')
            ->post('webauthn/register/verify', ['credential' => json_decode($registerJson, true)]);
        $verify->assertStatus(201);

        // Second enrolment of the SAME credential (same authenticator instance,
        // fresh options) must be rejected cleanly — a 409 conflict, not a leaked
        // raw DatabaseException.
        $optionsResult2 = $this->withSession(['user' => ['id' => $user->id]])
            ->post('webauthn/register/options', ['name' => 'Key again']);
        $optionsResult2->assertStatus(200);
        $options2 = json_decode($optionsResult2->getJSON(), true);

        $registerJson2 = $authn->register(json_encode($options2, JSON_THROW_ON_ERROR));

        $verify2 = $this->withSession($_SESSION)
            ->withBodyFormat('json')
            ->post('webauthn/register/verify', ['credential' => json_decode($registerJson2, true)]);

        $verify2->assertStatus(409);
        $verify2->assertJSONFragment(['status' => 'error', 'error' => 'conflict']);

        // The generic, user-facing message — never a SQL/exception string.
        $payload = json_decode($verify2->getJSON(), true);
        $this->assertSame(lang('Auth.webauthnDuplicate'), $payload['message']);
        $this->assertStringNotContainsStringIgnoringCase('exception', (string) $payload['message']);
        $this->assertStringNotContainsStringIgnoringCase('SQLSTATE', (string) $payload['message']);
        $this->assertStringNotContainsStringIgnoringCase('UNIQUE', (string) $payload['message']);
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
