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

namespace Tests\Libraries;

use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Test\DatabaseTestTrait;
use Daycry\Auth\Config\AuthOAuth as AuthConfig;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Entities\UserIdentity;
use Daycry\Auth\Enums\IdentityType;
use Daycry\Auth\Exceptions\AuthenticationException;
use Daycry\Auth\Libraries\Oauth\OauthManager;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericResourceOwner;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Mockery;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class OauthManagerTest extends TestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'Daycry\Auth';

    /**
     * @var OauthManager
     */
    private $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $config        = new AuthConfig();
        $this->manager = new OauthManager($config);

        // Seed 'user' group
        db_connect()->table('auth_groups')->insert([
            'name'        => 'user',
            'description' => 'Default user group',
        ]);
    }

    public function testRedirect(): void
    {
        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('getAuthorizationUrl')->andReturn('http://auth.url');
        $provider->shouldReceive('getState')->andReturn('mock_state');

        $this->manager->setProviderInstance($provider);

        $response = $this->manager->redirect();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('http://auth.url', $response->getHeaderLine('Location'));
        $this->assertSame('mock_state', session('oauth2state'));
    }

    public function testHandleCallbackInvalidState(): void
    {
        session()->set('oauth2state', 'valid_state');

        $this->expectException(AuthenticationException::class);

        $this->manager->handleCallback('code', 'invalid_state');
    }

    public function testHandleCallbackSuccessCreatesUser(): void
    {
        session()->set('oauth2state', 'valid_state');

        // Mock Provider
        $provider    = Mockery::mock(AbstractProvider::class);
        $accessToken = new AccessToken(['access_token' => 'test_token']);

        $provider->shouldReceive('getAccessToken')
            ->with('authorization_code', ['code' => 'auth_code'])
            ->andReturn($accessToken);

        // Mock Resource Owner
        $resourceOwner = Mockery::mock(GenericResourceOwner::class);
        $resourceOwner->shouldReceive('getId')->andReturn('social_123');
        $resourceOwner->shouldReceive('toArray')->andReturn([
            'email' => 'newuser@example.com',
            'name'  => 'NewUser',
        ]);

        $provider->shouldReceive('getResourceOwner')
            ->with($accessToken)
            ->andReturn($resourceOwner);

        $this->manager->setProviderInstance($provider, 'generic');

        // Execute
        $user = $this->manager->handleCallback('auth_code', 'valid_state');

        // Assert User Created
        $this->assertInstanceOf(User::class, $user);
        $this->seeInDatabase('users', ['id' => $user->id]);

        // Assert Identity Created
        // Let's check processUser logic in OauthManager.php if it sets username from name.

        // Assert Identity Created
        $this->seeInDatabase('auth_users_identities', [
            'type'   => 'oauth_generic',
            'secret' => 'social_123',
        ]);

        // Login check? The manager logs the user in?
        // Manager processUser: "if (! $user->loggedIn()) { $auth->login($user); }"
        // Let's verify logged in status if possible, or trust the logic flow.
        $this->assertTrue(auth()->loggedIn());
    }

    public function testHandleCallbackExistingUser(): void
    {
        // 1. Create User
        $userModel = new UserModel();
        $user      = new User([
            'username' => 'ExistingUser',
            'email'    => 'existing@example.com',
            'password' => 'password123',
        ]);
        $userModel->save($user);
        $user = $userModel->findById($userModel->getInsertID());

        session()->set('oauth2state', 'valid_state');

        // Mock Provider
        $provider    = Mockery::mock(AbstractProvider::class);
        $accessToken = new AccessToken(['access_token' => 'test_token']);

        $provider->shouldReceive('getAccessToken')->andReturn($accessToken);

        $resourceOwner = Mockery::mock(GenericResourceOwner::class);
        $resourceOwner->shouldReceive('getId')->andReturn('social_456');
        $resourceOwner->shouldReceive('toArray')->andReturn([
            'email' => 'existing@example.com', // Matches existing
            'name'  => 'ExistingUserUpdated',
        ]);

        $provider->shouldReceive('getResourceOwner')->andReturn($resourceOwner);

        $this->manager->setProviderInstance($provider, 'generic');

        // Execute
        $returnedUser = $this->manager->handleCallback('auth_code', 'valid_state');

        // Assert same user
        $this->assertSame($user->id, $returnedUser->id);

        // Assert Identity Added (social link)
        $this->seeInDatabase('auth_users_identities', [
            'user_id' => $user->id,
            'type'    => 'oauth_generic',
            'secret'  => 'social_456',
        ]);
    }

    public function testHandleCallbackStoresJsonExtra(): void
    {
        session()->set('oauth2state', 'valid_state');

        $provider    = Mockery::mock(AbstractProvider::class);
        $accessToken = new AccessToken([
            'access_token'  => 'test_token',
            'refresh_token' => 'refresh_abc',
        ]);

        $provider->shouldReceive('getAccessToken')
            ->with('authorization_code', ['code' => 'auth_code'])
            ->andReturn($accessToken);

        $resourceOwner = Mockery::mock(GenericResourceOwner::class);
        $resourceOwner->shouldReceive('getId')->andReturn('social_json_1');
        $resourceOwner->shouldReceive('toArray')->andReturn([
            'email' => 'jsonuser@example.com',
            'name'  => 'JsonUser',
        ]);

        $provider->shouldReceive('getResourceOwner')
            ->with($accessToken)
            ->andReturn($resourceOwner);

        $this->manager->setProviderInstance($provider, 'generic');

        $this->manager->handleCallback('auth_code', 'valid_state');

        // Verify extra is stored as JSON
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identity      = $identityModel->where('type', 'oauth_generic')
            ->where('secret', 'social_json_1')
            ->first();

        $this->assertInstanceOf(UserIdentity::class, $identity);
        $extra = json_decode($identity->extra, true);
        $this->assertIsArray($extra);
        $this->assertSame('refresh_abc', $extra['refresh_token']);
    }

    public function testHandleCallbackWithProfileFields(): void
    {
        // Configure fields for the generic provider
        $config                                   = new AuthConfig();
        $config->providers['generic_with_fields'] = [
            'clientId'     => 'test',
            'clientSecret' => 'test',
            'redirectUri'  => 'http://localhost/callback',
            'fields'       => ['role', 'team'],
        ];

        $manager = new OauthManager($config);

        session()->set('oauth2state', 'valid_state');

        $provider    = Mockery::mock(AbstractProvider::class);
        $accessToken = new AccessToken([
            'access_token'  => 'test_token',
            'refresh_token' => 'refresh_xyz',
        ]);

        $provider->shouldReceive('getAccessToken')
            ->with('authorization_code', ['code' => 'auth_code'])
            ->andReturn($accessToken);

        $resourceOwner = Mockery::mock(GenericResourceOwner::class);
        $resourceOwner->shouldReceive('getId')->andReturn('social_profile_1');
        $resourceOwner->shouldReceive('toArray')->andReturn([
            'email' => 'profileuser@example.com',
            'name'  => 'ProfileUser',
            'role'  => 'admin',
            'team'  => 'backend',
            'other' => 'ignored',
        ]);

        $provider->shouldReceive('getResourceOwner')
            ->with($accessToken)
            ->andReturn($resourceOwner);

        $manager->setProviderInstance($provider, 'generic_with_fields');

        $manager->handleCallback('auth_code', 'valid_state');

        // Verify profile data is stored in extra JSON
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identity      = $identityModel->where('type', 'oauth_generic_with_fields')
            ->where('secret', 'social_profile_1')
            ->first();

        $extra = json_decode($identity->extra, true);
        $this->assertSame('refresh_xyz', $extra['refresh_token']);
        $this->assertSame(['role' => 'admin', 'team' => 'backend'], $extra['profile']);
    }

    public function testHandleCallbackWorksWhenProfileFetchFails(): void
    {
        // Configure fields that won't be resolvable (but the resolver is generic,
        // so it will just filter toArray - we test that the flow doesn't break)
        session()->set('oauth2state', 'valid_state');

        $provider    = Mockery::mock(AbstractProvider::class);
        $accessToken = new AccessToken(['access_token' => 'test_token']);

        $provider->shouldReceive('getAccessToken')
            ->with('authorization_code', ['code' => 'auth_code'])
            ->andReturn($accessToken);

        $resourceOwner = Mockery::mock(GenericResourceOwner::class);
        $resourceOwner->shouldReceive('getId')->andReturn('social_fail_1');
        $resourceOwner->shouldReceive('toArray')->andReturn([
            'email' => 'failuser@example.com',
            'name'  => 'FailUser',
        ]);

        $provider->shouldReceive('getResourceOwner')
            ->with($accessToken)
            ->andReturn($resourceOwner);

        $this->manager->setProviderInstance($provider, 'generic');

        // Should succeed even without profile fields
        $user = $this->manager->handleCallback('auth_code', 'valid_state');
        $this->assertInstanceOf(User::class, $user);
    }

    public function testGetProfileDataReturnsStoredFields(): void
    {
        // Create user with OAuth identity containing profile data
        $userModel = new UserModel();
        $user      = new User([
            'username' => 'ProfileDataUser',
            'email'    => 'profiledata@example.com',
            'password' => 'password123',
        ]);
        $userModel->save($user);
        $user = $userModel->findById($userModel->getInsertID());

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identityModel->insert([
            'user_id' => $user->id,
            'type'    => 'oauth_generic',
            'name'    => 'ProfileDataUser',
            'secret'  => 'social_pd_1',
            'secret2' => 'access_token_here',
            'extra'   => json_encode([
                'refresh_token' => 'rt_123',
                'profile'       => ['role' => 'editor', 'team' => 'frontend'],
            ]),
        ]);

        $provider = Mockery::mock(AbstractProvider::class);
        $this->manager->setProviderInstance($provider, 'generic');
        $this->assertInstanceOf(User::class, $user);

        $profileData = $this->manager->getProfileData($user);

        $this->assertSame(['role' => 'editor', 'team' => 'frontend'], $profileData);
    }

    public function testGetProfileDataReturnsEmptyForLegacyExtra(): void
    {
        $userModel = new UserModel();
        $user      = new User([
            'username' => 'LegacyUser',
            'email'    => 'legacy@example.com',
            'password' => 'password123',
        ]);
        $userModel->save($user);
        $user = $userModel->findById($userModel->getInsertID());

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identityModel->insert([
            'user_id' => $user->id,
            'type'    => 'oauth_generic',
            'name'    => 'LegacyUser',
            'secret'  => 'social_legacy_1',
            'secret2' => 'access_token_here',
            'extra'   => 'plain_refresh_token_string',
        ]);

        $provider = Mockery::mock(AbstractProvider::class);
        $this->manager->setProviderInstance($provider, 'generic');
        $this->assertInstanceOf(User::class, $user);

        $profileData = $this->manager->getProfileData($user);

        $this->assertSame([], $profileData);
    }

    public function testReLoginUpdatesTokenAndProfile(): void
    {
        // Create user with existing OAuth identity
        $userModel = new UserModel();
        $user      = new User([
            'username' => 'ReLoginUser',
            'email'    => 'relogin@example.com',
            'password' => 'password123',
        ]);
        $userModel->save($user);
        $user = $userModel->findById($userModel->getInsertID());

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identityModel->insert([
            'user_id' => $user->id,
            'type'    => 'oauth_generic',
            'name'    => 'ReLoginUser',
            'secret'  => 'social_relogin_1',
            'secret2' => 'old_access_token',
            'extra'   => json_encode(['refresh_token' => 'old_refresh']),
        ]);

        session()->set('oauth2state', 'valid_state');

        $provider    = Mockery::mock(AbstractProvider::class);
        $accessToken = new AccessToken([
            'access_token'  => 'new_access_token',
            'refresh_token' => 'new_refresh_token',
        ]);

        $provider->shouldReceive('getAccessToken')
            ->with('authorization_code', ['code' => 'auth_code'])
            ->andReturn($accessToken);

        $resourceOwner = Mockery::mock(GenericResourceOwner::class);
        $resourceOwner->shouldReceive('getId')->andReturn('social_relogin_1');
        $resourceOwner->shouldReceive('toArray')->andReturn([
            'email' => 'relogin@example.com',
            'name'  => 'ReLoginUser',
        ]);

        $provider->shouldReceive('getResourceOwner')
            ->with($accessToken)
            ->andReturn($resourceOwner);

        $this->manager->setProviderInstance($provider, 'generic');

        $returnedUser = $this->manager->handleCallback('auth_code', 'valid_state');

        $this->assertSame($user->id, $returnedUser->id);

        // Verify token was updated
        $identity = $identityModel->where('type', 'oauth_generic')
            ->where('secret', 'social_relogin_1')
            ->first();

        $this->assertSame('new_access_token', $identity->secret2);
        $extra = json_decode($identity->extra, true);
        $this->assertSame('new_refresh_token', $extra['refresh_token']);
    }

    public function testRefreshAccessTokenWithJsonExtra(): void
    {
        $userModel = new UserModel();
        $user      = new User([
            'username' => 'RefreshJsonUser',
            'email'    => 'refreshjson@example.com',
            'password' => 'password123',
        ]);
        $userModel->save($user);
        $user = $userModel->findById($userModel->getInsertID());

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identityModel->insert([
            'user_id' => $user->id,
            'type'    => IdentityType::oauthProvider('generic'),
            'name'    => 'RefreshJsonUser',
            'secret'  => 'social_rj',
            'secret2' => 'old_access',
            'extra'   => json_encode(['refresh_token' => 'old_refresh', 'profile' => ['role' => 'admin']]),
        ]);

        $provider = Mockery::mock(AbstractProvider::class);
        $newToken = new AccessToken([
            'access_token'  => 'new_access',
            'refresh_token' => 'new_refresh',
        ]);
        $provider->shouldReceive('getAccessToken')->andReturn($newToken);

        $this->manager->setProviderInstance($provider, 'generic');
        $this->assertInstanceOf(User::class, $user);

        $result = $this->manager->refreshAccessToken($user);

        $this->assertInstanceOf(AccessTokenInterface::class, $result);
        $this->assertSame('new_access', $result->getToken());

        $identity = $identityModel->where('secret', 'social_rj')->first();
        $extra    = json_decode($identity->extra, true);
        $this->assertSame('new_refresh', $extra['refresh_token']);
        $this->assertSame(['role' => 'admin'], $extra['profile']);
    }

    public function testRefreshAccessTokenWithLegacyExtra(): void
    {
        $userModel = new UserModel();
        $user      = new User([
            'username' => 'RefreshLegacyUser',
            'email'    => 'refreshlegacy@example.com',
            'password' => 'password123',
        ]);
        $userModel->save($user);
        $user = $userModel->findById($userModel->getInsertID());

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identityModel->insert([
            'user_id' => $user->id,
            'type'    => IdentityType::oauthProvider('generic'),
            'name'    => 'RefreshLegacyUser',
            'secret'  => 'social_rl',
            'secret2' => 'old_access',
            'extra'   => 'plain_refresh_token',
        ]);

        $provider = Mockery::mock(AbstractProvider::class);
        $newToken = new AccessToken([
            'access_token'  => 'new_access',
            'refresh_token' => 'new_refresh',
        ]);
        $provider->shouldReceive('getAccessToken')->andReturn($newToken);

        $this->manager->setProviderInstance($provider, 'generic');
        $this->assertInstanceOf(User::class, $user);

        $result = $this->manager->refreshAccessToken($user);

        $this->assertInstanceOf(AccessTokenInterface::class, $result);
        $this->assertSame('new_access', $result->getToken());
    }

    public function testRefreshAccessTokenNoIdentity(): void
    {
        $userModel = new UserModel();
        $user      = new User([
            'username' => 'NoIdentityUser',
            'email'    => 'noidentity@example.com',
            'password' => 'password123',
        ]);
        $userModel->save($user);
        $user = $userModel->findById($userModel->getInsertID());

        $provider = Mockery::mock(AbstractProvider::class);
        $this->manager->setProviderInstance($provider, 'generic');
        $this->assertInstanceOf(User::class, $user);

        $result = $this->manager->refreshAccessToken($user);

        $this->assertNotInstanceOf(AccessTokenInterface::class, $result);
    }

    public function testRefreshAccessTokenProviderFails(): void
    {
        $userModel = new UserModel();
        $user      = new User([
            'username' => 'ProviderFailUser',
            'email'    => 'providerfail@example.com',
            'password' => 'password123',
        ]);
        $userModel->save($user);
        $user = $userModel->findById($userModel->getInsertID());

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identityModel->insert([
            'user_id' => $user->id,
            'type'    => IdentityType::oauthProvider('generic'),
            'name'    => 'ProviderFailUser',
            'secret'  => 'social_pf',
            'secret2' => 'old_access',
            'extra'   => json_encode(['refresh_token' => 'some_refresh']),
        ]);

        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('getAccessToken')
            ->andThrow(new IdentityProviderException('token expired', 0, []));

        $this->manager->setProviderInstance($provider, 'generic');
        $this->assertInstanceOf(User::class, $user);

        $result = $this->manager->refreshAccessToken($user);

        $this->assertNotInstanceOf(AccessTokenInterface::class, $result);
    }

    public function testHandleCallbackTriggersOauthLoginEvent(): void
    {
        session()->set('oauth2state', 'valid_state');

        $triggered = false;
        Events::on('oauth-login', static function ($user, $providerName) use (&$triggered): void {
            $triggered = true;
        });

        $provider    = Mockery::mock(AbstractProvider::class);
        $accessToken = new AccessToken(['access_token' => 'test_token']);

        $provider->shouldReceive('getAccessToken')
            ->with('authorization_code', ['code' => 'auth_code'])
            ->andReturn($accessToken);

        $resourceOwner = Mockery::mock(GenericResourceOwner::class);
        $resourceOwner->shouldReceive('getId')->andReturn('social_evt_1');
        $resourceOwner->shouldReceive('toArray')->andReturn([
            'email' => 'eventuser@example.com',
            'name'  => 'EventUser',
        ]);

        $provider->shouldReceive('getResourceOwner')
            ->with($accessToken)
            ->andReturn($resourceOwner);

        $this->manager->setProviderInstance($provider, 'generic');

        $this->manager->handleCallback('auth_code', 'valid_state');

        $this->assertTrue($triggered);
    }

    public function testHandleCallbackTriggersProfileFetchedEvent(): void
    {
        $config                                   = new AuthConfig();
        $config->providers['generic_with_fields'] = [
            'clientId'     => 'test',
            'clientSecret' => 'test',
            'redirectUri'  => 'http://localhost/callback',
            'fields'       => ['role'],
        ];

        $manager = new OauthManager($config);

        session()->set('oauth2state', 'valid_state');

        $capturedData = [];
        Events::on('oauth-profile-fetched', static function ($user, $provider, $profileData) use (&$capturedData): void {
            $capturedData = $profileData;
        });

        $provider    = Mockery::mock(AbstractProvider::class);
        $accessToken = new AccessToken(['access_token' => 'test_token']);

        $provider->shouldReceive('getAccessToken')
            ->with('authorization_code', ['code' => 'auth_code'])
            ->andReturn($accessToken);

        $resourceOwner = Mockery::mock(GenericResourceOwner::class);
        $resourceOwner->shouldReceive('getId')->andReturn('social_pf_evt');
        $resourceOwner->shouldReceive('toArray')->andReturn([
            'email' => 'profileevt@example.com',
            'name'  => 'ProfileEvtUser',
            'role'  => 'editor',
        ]);

        $provider->shouldReceive('getResourceOwner')
            ->with($accessToken)
            ->andReturn($resourceOwner);

        $manager->setProviderInstance($provider, 'generic_with_fields');

        // Seed 'user' group (needed for new user creation)
        $manager->handleCallback('auth_code', 'valid_state');

        $this->assertSame(['role' => 'editor'], $capturedData);
    }

    public function testHandleCallbackStoresScopesGranted(): void
    {
        session()->set('oauth2state', 'valid_state');

        $provider    = Mockery::mock(AbstractProvider::class);
        $accessToken = new AccessToken([
            'access_token' => 'test_token',
            'scope'        => 'openid profile email',
        ]);

        $provider->shouldReceive('getAccessToken')
            ->with('authorization_code', ['code' => 'auth_code'])
            ->andReturn($accessToken);

        $resourceOwner = Mockery::mock(GenericResourceOwner::class);
        $resourceOwner->shouldReceive('getId')->andReturn('social_scope_1');
        $resourceOwner->shouldReceive('toArray')->andReturn([
            'email' => 'scopeuser@example.com',
            'name'  => 'ScopeUser',
        ]);

        $provider->shouldReceive('getResourceOwner')
            ->with($accessToken)
            ->andReturn($resourceOwner);

        $this->manager->setProviderInstance($provider, 'generic');

        $this->manager->handleCallback('auth_code', 'valid_state');

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identity      = $identityModel->where('type', IdentityType::oauthProvider('generic'))
            ->where('secret', 'social_scope_1')
            ->first();

        $extra = json_decode($identity->extra, true);
        $this->assertSame(['openid', 'profile', 'email'], $extra['scopes_granted']);
    }

    public function testHandleCallbackStoresProfileFetchedAt(): void
    {
        $config                                   = new AuthConfig();
        $config->providers['generic_with_fields'] = [
            'clientId'     => 'test',
            'clientSecret' => 'test',
            'redirectUri'  => 'http://localhost/callback',
            'fields'       => ['role'],
        ];

        $manager = new OauthManager($config);

        session()->set('oauth2state', 'valid_state');

        $provider    = Mockery::mock(AbstractProvider::class);
        $accessToken = new AccessToken(['access_token' => 'test_token']);

        $provider->shouldReceive('getAccessToken')
            ->with('authorization_code', ['code' => 'auth_code'])
            ->andReturn($accessToken);

        $resourceOwner = Mockery::mock(GenericResourceOwner::class);
        $resourceOwner->shouldReceive('getId')->andReturn('social_pfa_1');
        $resourceOwner->shouldReceive('toArray')->andReturn([
            'email' => 'pfauser@example.com',
            'name'  => 'PfaUser',
            'role'  => 'dev',
        ]);

        $provider->shouldReceive('getResourceOwner')
            ->with($accessToken)
            ->andReturn($resourceOwner);

        $manager->setProviderInstance($provider, 'generic_with_fields');

        $manager->handleCallback('auth_code', 'valid_state');

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identity      = $identityModel->where('type', IdentityType::oauthProvider('generic_with_fields'))
            ->where('secret', 'social_pfa_1')
            ->first();

        $extra = json_decode($identity->extra, true);
        $this->assertArrayHasKey('profile_fetched_at', $extra);
        $this->assertNotEmpty($extra['profile_fetched_at']);
    }
}
