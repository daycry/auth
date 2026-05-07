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

namespace Tests\Models;

use CodeIgniter\Test\DatabaseTestTrait;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Entities\UserIdentity;
use Daycry\Auth\Enums\IdentityType;
use Daycry\Auth\Models\OAuthTokenRepository;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class OAuthTokenRepositoryTest extends TestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'Daycry\Auth';
    private OAuthTokenRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $this->repo    = new OAuthTokenRepository($identityModel);
    }

    private function createUser(string $username = 'TestUser', string $email = 'test@example.com'): User
    {
        $userModel = new UserModel();
        $user      = new User([
            'username' => $username,
            'email'    => $email,
            'password' => 'password123',
        ]);
        $userModel->save($user);

        return $userModel->findById($userModel->getInsertID());
    }

    public function testFindByUserAndProvider(): void
    {
        $user = $this->createUser();

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identityModel->insert([
            'user_id' => $user->id,
            'type'    => IdentityType::oauthProvider('github'),
            'name'    => 'TestUser',
            'secret'  => 'social_123',
            'secret2' => 'access_token',
            'extra'   => json_encode(['refresh_token' => 'rt_abc']),
        ]);

        $identity = $this->repo->findByUserAndProvider((int) $user->id, 'github');

        $this->assertInstanceOf(UserIdentity::class, $identity);
        $this->assertSame('social_123', $identity->secret);
    }

    public function testFindByUserAndProviderReturnsNull(): void
    {
        $user = $this->createUser();

        $identity = $this->repo->findByUserAndProvider((int) $user->id, 'github');

        $this->assertNotInstanceOf(UserIdentity::class, $identity);
    }

    public function testFindByProviderAndSocialId(): void
    {
        $user = $this->createUser();

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identityModel->insert([
            'user_id' => $user->id,
            'type'    => IdentityType::oauthProvider('google'),
            'name'    => 'TestUser',
            'secret'  => 'google_456',
            'secret2' => 'access_token',
        ]);

        $identity = $this->repo->findByProviderAndSocialId('google', 'google_456');

        $this->assertInstanceOf(UserIdentity::class, $identity);
        $this->assertSame((string) $user->id, (string) $identity->user_id);
    }

    public function testCreateOAuthIdentity(): void
    {
        $user = $this->createUser();

        $this->repo->createOAuthIdentity((int) $user->id, 'facebook', [
            'name'    => 'FBUser',
            'secret'  => 'fb_789',
            'secret2' => 'at_xyz',
            'extra'   => json_encode(['refresh_token' => 'rt_fb']),
            'expires' => null,
        ]);

        $this->seeInDatabase('auth_users_identities', [
            'user_id' => $user->id,
            'type'    => IdentityType::oauthProvider('facebook'),
            'secret'  => 'fb_789',
        ]);
    }

    public function testUpdateOAuthIdentity(): void
    {
        $user = $this->createUser();

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identityModel->insert([
            'user_id' => $user->id,
            'type'    => IdentityType::oauthProvider('github'),
            'name'    => 'TestUser',
            'secret'  => 'social_upd',
            'secret2' => 'old_token',
        ]);

        $identity          = $this->repo->findByUserAndProvider((int) $user->id, 'github');
        $identity->secret2 = 'new_token';
        $this->assertInstanceOf(UserIdentity::class, $identity);

        $this->repo->updateOAuthIdentity($identity);

        $this->seeInDatabase('auth_users_identities', [
            'secret'  => 'social_upd',
            'secret2' => 'new_token',
        ]);
    }

    public function testGetProfileData(): void
    {
        $user = $this->createUser();

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identityModel->insert([
            'user_id' => $user->id,
            'type'    => IdentityType::oauthProvider('azure'),
            'name'    => 'AzureUser',
            'secret'  => 'az_123',
            'secret2' => 'at_az',
            'extra'   => json_encode([
                'refresh_token' => 'rt_az',
                'profile'       => ['department' => 'Engineering', 'jobTitle' => 'Dev'],
            ]),
        ]);

        $profileData = $this->repo->getProfileData((int) $user->id, 'azure');

        $this->assertSame(['department' => 'Engineering', 'jobTitle' => 'Dev'], $profileData);
    }

    public function testGetProfileDataLegacyExtra(): void
    {
        $user = $this->createUser();

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identityModel->insert([
            'user_id' => $user->id,
            'type'    => IdentityType::oauthProvider('generic'),
            'name'    => 'LegacyUser',
            'secret'  => 'legacy_1',
            'secret2' => 'at_legacy',
            'extra'   => 'plain_refresh_token',
        ]);

        $profileData = $this->repo->getProfileData((int) $user->id, 'generic');

        $this->assertSame([], $profileData);
    }

    public function testParseExtraJson(): void
    {
        $extra = json_encode(['refresh_token' => 'rt', 'profile' => ['a' => 1]]);

        $result = $this->repo->parseExtra($extra);

        $this->assertSame('rt', $result['refresh_token']);
        $this->assertSame(['a' => 1], $result['profile']);
    }

    public function testParseExtraLegacy(): void
    {
        $result = $this->repo->parseExtra('plain_token_string');

        $this->assertSame(['refresh_token' => 'plain_token_string'], $result);
    }

    public function testParseExtraEmpty(): void
    {
        $this->assertSame([], $this->repo->parseExtra(null));
        $this->assertSame([], $this->repo->parseExtra(''));
    }
}
