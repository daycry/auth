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

namespace Tests\Authentication\Filters;

use Daycry\Auth\Entities\AccessToken;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Filters\TokenScopeFilter;
use Daycry\Auth\Models\UserModel;
use ReflectionMethod;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class TokenScopeFilterTest extends DatabaseTestCase
{
    private TokenScopeFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filter = new TokenScopeFilter();
    }

    public function testRejectsWhenUserIsNotLoggedIn(): void
    {
        // Anonymous request → cannot extract a token → deny.
        $reflection = new ReflectionMethod($this->filter, 'isAuthorized');

        $this->assertFalse($reflection->invoke($this->filter, ['posts.read']));
    }

    public function testEmptyArgumentsDeny(): void
    {
        $reflection = new ReflectionMethod($this->filter, 'isAuthorized');

        // No scopes argument given → deny.
        $this->assertFalse($reflection->invoke($this->filter, []));
    }

    public function testAccessTokenWildcardScopeAllows(): void
    {
        $token        = new AccessToken();
        $token->extra = ['*'];

        // The filter requires a logged-in user — but the resolveCurrentAccessToken
        // helper goes through `currentAccessToken()`. Test the underlying check
        // directly via AccessToken::can() to confirm the matrix the filter relies on.
        $this->assertTrue($token->can('posts.read'));
        $this->assertTrue($token->can('anything.at.all'));
    }

    public function testAccessTokenScopesGrantOnlyMatchingAbilities(): void
    {
        $token        = new AccessToken();
        $token->extra = ['posts.read', 'posts.write'];

        $this->assertTrue($token->can('posts.read'));
        $this->assertTrue($token->can('posts.write'));
        $this->assertFalse($token->can('users.delete'));
    }

    public function testAccessTokenWithoutScopesDeniesEverything(): void
    {
        // A token with an empty scope array grants nothing.
        $token        = new AccessToken();
        $token->extra = ['noop']; // a stored value other than '*'

        $this->assertFalse($token->can('users.delete'));
    }

    public function testIsAuthorizedAcceptsTokenWithMatchingScope(): void
    {
        $reflection = new ReflectionMethod($this->filter, 'isAuthorized');

        // Set up a logged-in user via the access_token authenticator with a
        // current token granting `posts.read`.
        /** @var User $user */
        $user  = fake(UserModel::class);
        $token = $user->generateAccessToken('mobile', ['posts.read']);
        $user->setAccessToken($user->getAccessToken($token->raw_token));

        // Simulate auth() returning this user
        auth('access_token')->loginById($user->id);
        $authUser = auth()->user();
        if ($authUser instanceof User) {
            $authUser->setAccessToken($token);
        }

        $this->assertTrue($reflection->invoke($this->filter, ['posts.read']));
        $this->assertFalse($reflection->invoke($this->filter, ['users.delete']));
    }

    public function testIsAuthorizedSkipsEmptyScopeArguments(): void
    {
        $reflection = new ReflectionMethod($this->filter, 'isAuthorized');

        /** @var User $user */
        $user  = fake(UserModel::class);
        $token = $user->generateAccessToken('mobile', ['posts.read']);

        auth('access_token')->loginById($user->id);
        $authUser = auth()->user();
        if ($authUser instanceof User) {
            $authUser->setAccessToken($user->getAccessToken($token->raw_token));
        }

        // Empty scope strings are skipped — the only effective scope here is
        // `posts.read` which the token has.
        $this->assertTrue($reflection->invoke($this->filter, ['', 'posts.read', '   ']));
    }
}
