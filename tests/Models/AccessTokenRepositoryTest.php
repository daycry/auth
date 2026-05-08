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

use Daycry\Auth\Authentication\Authenticators\AccessToken;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Models\AccessTokenRepository;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class AccessTokenRepositoryTest extends DatabaseTestCase
{
    private AccessTokenRepository $repo;
    private UserIdentityModel $identityModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->identityModel = model(UserIdentityModel::class);
        $this->repo          = new AccessTokenRepository($this->identityModel);
    }

    private function makeUser(): User
    {
        /** @var User $user */
        $user = fake(UserModel::class);

        return $user;
    }

    public function testGenerateReturnsAccessTokenWithRawAndScopes(): void
    {
        $user = $this->makeUser();

        $token = $this->repo->generateAccessToken($user, 'mobile', ['users.read']);

        $this->assertNotEmpty($token->raw_token);
        $this->assertSame('mobile', $token->name);
        $this->assertSame(['users.read'], $token->scopes);
    }

    public function testGetAccessTokenByRawTokenIgnoresRevoked(): void
    {
        $user  = $this->makeUser();
        $token = $this->repo->generateAccessToken($user, 'apiv1');

        $found = $this->repo->getAccessTokenByRawToken($token->raw_token);
        $this->assertInstanceOf(\Daycry\Auth\Entities\AccessToken::class, $found);
        $this->assertSame((int) $token->id, (int) $found->id);

        $this->repo->softRevokeAccessToken($user, $token->raw_token);

        $this->assertNotInstanceOf(\Daycry\Auth\Entities\AccessToken::class, $this->repo->getAccessTokenByRawToken($token->raw_token));
    }

    public function testGetAccessTokenForUserAndById(): void
    {
        $user  = $this->makeUser();
        $token = $this->repo->generateAccessToken($user, 'mobile');

        $byRaw = $this->repo->getAccessToken($user, $token->raw_token);
        $this->assertInstanceOf(\Daycry\Auth\Entities\AccessToken::class, $byRaw);
        $this->assertSame((int) $token->id, (int) $byRaw->id);

        $byId = $this->repo->getAccessTokenById((int) $token->id, $user);
        $this->assertInstanceOf(\Daycry\Auth\Entities\AccessToken::class, $byId);
        $this->assertSame((int) $token->id, (int) $byId->id);
    }

    public function testGetAllAccessTokensReturnsList(): void
    {
        $user = $this->makeUser();

        $this->repo->generateAccessToken($user, 'mobile');
        $this->repo->generateAccessToken($user, 'cli');

        $all = $this->repo->getAllAccessTokens($user);
        $this->assertCount(2, $all);
    }

    public function testSoftRevokeAccessTokenSetsRevokedAt(): void
    {
        $user  = $this->makeUser();
        $token = $this->repo->generateAccessToken($user, 'mobile');

        $this->repo->softRevokeAccessToken($user, $token->raw_token);

        $row = $this->identityModel->find($token->id);
        $this->assertNotEmpty($row->revoked_at);
    }

    public function testSoftRevokeAccessTokenNoOpsForUnknownToken(): void
    {
        $user = $this->makeUser();

        // Does not throw.
        $this->repo->softRevokeAccessToken($user, 'never-issued');
        $this->expectNotToPerformAssertions();
    }

    public function testSoftRevokeAccessTokenBySecretSetsRevokedAt(): void
    {
        $user  = $this->makeUser();
        $token = $this->repo->generateAccessToken($user, 'mobile');

        $this->repo->softRevokeAccessTokenBySecret($user, hash('sha256', $token->raw_token));

        $row = $this->identityModel->find($token->id);
        $this->assertNotEmpty($row->revoked_at);
    }

    public function testSoftRevokeAllAccessTokensRevokesAllForUser(): void
    {
        $user = $this->makeUser();

        $a = $this->repo->generateAccessToken($user, 'mobile');
        $b = $this->repo->generateAccessToken($user, 'cli');

        $this->repo->softRevokeAllAccessTokens($user);

        $this->assertNotEmpty($this->identityModel->find($a->id)->revoked_at);
        $this->assertNotEmpty($this->identityModel->find($b->id)->revoked_at);
        $this->assertNotInstanceOf(\Daycry\Auth\Entities\AccessToken::class, $this->repo->getAccessTokenByRawToken($a->raw_token));
        $this->assertNotInstanceOf(\Daycry\Auth\Entities\AccessToken::class, $this->repo->getAccessTokenByRawToken($b->raw_token));
    }

    public function testDeprecatedDeleteWrappersDelegateToRevocation(): void
    {
        $user  = $this->makeUser();
        $token = $this->repo->generateAccessToken($user, 'mobile');

        // Each deprecated wrapper just delegates to a revocation method on the
        // underlying model. Verify they don't throw and clear the token from
        // the active-token query.
        $this->repo->deleteAccessToken($user, $token->raw_token);
        $this->assertNotInstanceOf(\Daycry\Auth\Entities\AccessToken::class, $this->repo->getAccessTokenByRawToken($token->raw_token));

        $token2 = $this->repo->generateAccessToken($user, 'cli');
        $this->repo->deleteAccessTokenBySecret($user, hash('sha256', $token2->raw_token));
        $this->assertNotInstanceOf(\Daycry\Auth\Entities\AccessToken::class, $this->repo->getAccessTokenByRawToken($token2->raw_token));

        $token3 = $this->repo->generateAccessToken($user, 'web');
        $this->repo->deleteAllAccessTokens($user);
        $this->assertNotInstanceOf(\Daycry\Auth\Entities\AccessToken::class, $this->repo->getAccessTokenByRawToken($token3->raw_token));
    }

    public function testGetAccessTokenByRawTokenReturnsNullForUnknown(): void
    {
        $this->assertNotInstanceOf(\Daycry\Auth\Entities\AccessToken::class, $this->repo->getAccessTokenByRawToken('totally-unknown-raw-token'));
    }

    public function testGenerateAccessTokenDefaultsToWildcardScope(): void
    {
        $user  = $this->makeUser();
        $token = $this->repo->generateAccessToken($user, 'mobile');

        $this->assertSame(['*'], $token->scopes);
        $this->assertSame(AccessToken::ID_TYPE_ACCESS_TOKEN, $token->type);
    }
}
