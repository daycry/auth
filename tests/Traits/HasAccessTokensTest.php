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

namespace Tests\Traits;

use Daycry\Auth\Entities\AccessToken;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Models\AccessTokenRepository;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;

/**
 * Covers the User-facing API exposed by the HasAccessTokens trait.
 *
 * @internal
 */
final class HasAccessTokensTest extends DatabaseTestCase
{
    private function makeUser(): User
    {
        /** @var User $user */
        $user = fake(UserModel::class);

        return $user;
    }

    public function testGenerateAccessTokenReturnsRawToken(): void
    {
        $user  = $this->makeUser();
        $token = $user->generateAccessToken('mobile');

        $this->assertNotEmpty($token->raw_token);
    }

    public function testGetAccessTokenReturnsNullForBlankInput(): void
    {
        $user = $this->makeUser();

        $this->assertNotInstanceOf(AccessToken::class, $user->getAccessToken(null));
        $this->assertNotInstanceOf(AccessToken::class, $user->getAccessToken(''));
        $this->assertNotInstanceOf(AccessToken::class, $user->getAccessToken('0'));
    }

    public function testGetAccessTokenReturnsTokenForValidRaw(): void
    {
        $user  = $this->makeUser();
        $token = $user->generateAccessToken('mobile');

        $found = $user->getAccessToken($token->raw_token);
        $this->assertInstanceOf(AccessToken::class, $found);
        $this->assertSame((int) $token->id, (int) $found->id);
    }

    public function testGetAccessTokenByIdRoundTrip(): void
    {
        $user  = $this->makeUser();
        $token = $user->generateAccessToken('mobile');

        $found = $user->getAccessTokenById((int) $token->id);
        $this->assertInstanceOf(AccessToken::class, $found);
        $this->assertSame((int) $token->id, (int) $found->id);
    }

    public function testAccessTokensReturnsAllForUser(): void
    {
        $user = $this->makeUser();
        $user->generateAccessToken('a');
        $user->generateAccessToken('b');

        $this->assertCount(2, $user->accessTokens());
    }

    public function testCurrentAccessTokenIsNullWhenUnset(): void
    {
        $user = $this->makeUser();
        $this->assertNotInstanceOf(AccessToken::class, $user->currentAccessToken());
    }

    public function testSetAccessTokenStoresAndReturnsViaCurrent(): void
    {
        $user  = $this->makeUser();
        $token = $user->generateAccessToken('mobile');

        $result = $user->setAccessToken($token);
        $this->assertSame($user, $result, 'setAccessToken returns $this');
        $this->assertSame($token, $user->currentAccessToken());
    }

    public function testTokenCanFalseWhenNoCurrentToken(): void
    {
        $user = $this->makeUser();

        $this->assertFalse($user->tokenCan('users.read'));
        $this->assertTrue($user->tokenCant('users.read'));
    }

    public function testTokenCanRespectsScopesOnCurrentToken(): void
    {
        $user  = $this->makeUser();
        $token = $user->generateAccessToken('limited', ['users.read']);

        $user->setAccessToken($token);

        $this->assertTrue($user->tokenCan('users.read'));
        $this->assertFalse($user->tokenCant('users.read'));
        $this->assertFalse($user->tokenCan('users.write'));
        $this->assertTrue($user->tokenCant('users.write'));
    }

    public function testRevokeAccessTokenRemovesItFromActiveLookup(): void
    {
        $user  = $this->makeUser();
        $token = $user->generateAccessToken('mobile');

        $user->revokeAccessToken($token->raw_token);

        // The trait soft-revokes by setting `revoked_at`; the active-token
        // lookup on the repository must skip revoked rows.
        $this->assertNotInstanceOf(AccessToken::class, $this->repo()->getAccessTokenByRawToken($token->raw_token));
    }

    public function testRevokeAccessTokenBySecretRemovesIt(): void
    {
        $user  = $this->makeUser();
        $token = $user->generateAccessToken('mobile');

        $user->revokeAccessTokenBySecret(hash('sha256', $token->raw_token));

        $this->assertNotInstanceOf(AccessToken::class, $this->repo()->getAccessTokenByRawToken($token->raw_token));
    }

    public function testRevokeAllAccessTokensRevokesEveryTokenForUser(): void
    {
        $user = $this->makeUser();
        $a    = $user->generateAccessToken('a');
        $b    = $user->generateAccessToken('b');

        $user->revokeAllAccessTokens();

        $this->assertNotInstanceOf(AccessToken::class, $this->repo()->getAccessTokenByRawToken($a->raw_token));
        $this->assertNotInstanceOf(AccessToken::class, $this->repo()->getAccessTokenByRawToken($b->raw_token));
    }

    private function repo(): AccessTokenRepository
    {
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        return new AccessTokenRepository($identityModel);
    }
}
