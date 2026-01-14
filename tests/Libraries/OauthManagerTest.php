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

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Test\DatabaseTestTrait;
use Daycry\Auth\Config\Auth as AuthConfig;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Exceptions\AuthenticationException;
use Daycry\Auth\Libraries\Oauth\OauthManager;
use Daycry\Auth\Models\UserModel;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\GenericResourceOwner;
use League\OAuth2\Client\Token\AccessToken;
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
}
