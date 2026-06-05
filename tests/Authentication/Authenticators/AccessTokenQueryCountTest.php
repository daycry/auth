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

namespace Tests\Authentication\Authenticators;

use CodeIgniter\Events\Events;
use CodeIgniter\I18n\Time;
use Daycry\Auth\Authentication\Authentication;
use Daycry\Auth\Authentication\Authenticators\AccessToken;
use Daycry\Auth\Config\Auth;
use Daycry\Auth\Entities\AccessToken as AccessTokenEntity;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;

/**
 * Guards the query-count contract for access-token authentication:
 * a successful check must resolve both the token identity AND its user
 * in a single SELECT (the user is JOIN-ed in, not lazy-loaded second).
 *
 * NOTE: this test deliberately does NOT inject MockEvents, because it
 * relies on the real `DBQuery` event firing for each statement.
 *
 * @internal
 */
final class AccessTokenQueryCountTest extends DatabaseTestCase
{
    private AccessToken $auth;

    protected function setUp(): void
    {
        parent::setUp();

        $config = new Auth();
        $auth   = new Authentication($config);
        $auth->setProvider(model(UserModel::class));

        /** @var AccessToken $authenticator */
        $authenticator = $auth->factory('access_token');
        $this->auth    = $authenticator;
    }

    public function testCheckIssuesExactlyOneQuery(): void
    {
        // Push the throttle window high so the `last_used_at` UPDATE is skipped,
        // isolating the SELECT count.
        setting('AuthSecurity.tokenLastUsedThrottle', 9999);

        /** @var User $user */
        $user  = fake(UserModel::class);
        $token = $user->generateAccessToken('foo');

        // Seed a recent last_used_at so the throttled UPDATE is skipped and only
        // the SELECT(s) remain in the counted window.
        $this->markTokenRecentlyUsed($token);

        $count = 0;
        Events::on('DBQuery', static function () use (&$count): void {
            $count++;
        });

        $result = $this->auth->check(['token' => $token->raw_token]);

        // Snapshot immediately so only check()'s statements are measured.
        $measured = $count;

        $this->assertTrue($result->isOK());
        $this->assertInstanceOf(User::class, $result->extraInfo());
        $this->assertSame($user->id, $result->extraInfo()->id);

        $this->assertSame(1, $measured, 'AccessToken auth must issue exactly one query');
    }

    public function testResolvedUserDoesNotIssueAnExtraQuery(): void
    {
        setting('AuthSecurity.tokenLastUsedThrottle', 9999);

        /** @var User $user */
        $user  = fake(UserModel::class);
        $token = $user->generateAccessToken('foo');

        $this->markTokenRecentlyUsed($token);

        $result = $this->auth->check(['token' => $token->raw_token]);
        $this->assertTrue($result->isOK());

        $accessToken = $result->extraInfo()->currentAccessToken();
        $this->assertNotNull($accessToken);

        // The user relation must already be cached on the token entity, so
        // accessing it issues zero additional queries.
        $count = 0;
        Events::on('DBQuery', static function () use (&$count): void {
            $count++;
        });

        $resolvedUser = $accessToken->user();

        // Snapshot immediately so only user()'s statements (zero) are measured.
        $measured = $count;

        $this->assertInstanceOf(User::class, $resolvedUser);
        $this->assertSame($user->id, $resolvedUser->id);
        $this->assertSame(0, $measured, 'token->user() must not issue an additional query');
    }

    public function testJoinHydratedTokenPreservesScopes(): void
    {
        setting('AuthSecurity.tokenLastUsedThrottle', 9999);

        /** @var User $user */
        $user  = fake(UserModel::class);
        $token = $user->generateAccessToken('foo', ['users.read', 'posts.write']);
        $this->markTokenRecentlyUsed($token);

        $result = $this->auth->check(['token' => $token->raw_token]);
        $this->assertTrue($result->isOK());

        // The serialized `extra` (scopes) column must survive the JOIN
        // hydration intact — the token is built via injectRawData(), so the
        // raw JSON is decoded on read exactly as the non-JOIN path would.
        $accessToken = $result->extraInfo()->currentAccessToken();
        $this->assertNotNull($accessToken);
        $this->assertTrue($accessToken->can('users.read'));
        $this->assertTrue($accessToken->can('posts.write'));
        $this->assertTrue($accessToken->cant('users.delete'));
    }

    /**
     * Persists a recent `last_used_at` on the token so the throttled
     * UPDATE inside check() is skipped, leaving only SELECT(s) to count.
     */
    private function markTokenRecentlyUsed(AccessTokenEntity $token): void
    {
        $token->last_used_at = Time::now()->format('Y-m-d H:i:s');
        model(UserIdentityModel::class)->save($token);
    }
}
