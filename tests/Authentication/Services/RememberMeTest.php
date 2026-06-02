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

namespace Tests\Authentication\Services;

use CodeIgniter\I18n\Time;
use CodeIgniter\Test\Mock\MockEvents;
use Config\Services;
use Daycry\Auth\Authentication\Services\RememberMe;
use Daycry\Auth\Models\RememberModel;
use Daycry\Auth\Models\UserModel;
use stdClass;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeUser;

/**
 * @internal
 */
final class RememberMeTest extends DatabaseTestCase
{
    use FakeUser;

    private RememberMe $rememberMe;
    private RememberModel $rememberModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFakeUser();

        $events = new MockEvents();
        Services::injectMock('events', $events);

        // Set user email
        $this->user->email = 'remember@example.com';
        model(UserModel::class)->save($this->user);

        $this->rememberModel = model(RememberModel::class);
        $this->rememberMe    = new RememberMe($this->rememberModel);

        // Ensure no remember-me cookie leaks from previous tests
        $cookiePrefix = (string) (service('settings')->get('Cookie.prefix') ?? '');
        $cookieName   = $cookiePrefix . service('settings')->get('Auth.sessionConfig')['rememberCookieName'];
        service('superglobals')->unsetCookie($cookieName);
        unset($_COOKIE[$cookieName], $_COOKIE[service('settings')->get('Auth.sessionConfig')['rememberCookieName']]);
    }

    public function testRememberUserCreatesTokenInDatabase(): void
    {
        $this->rememberMe->rememberUser($this->user);

        $this->seeInDatabase($this->tables['remember_tokens'], [
            'user_id' => $this->user->id,
        ]);
    }

    public function testCheckWithNoCookieReturnsNull(): void
    {
        $result = $this->rememberMe->check();

        $this->assertNotInstanceOf(stdClass::class, $result);
    }

    public function testGetRememberMeTokenReturnsNullWhenNoCookieSet(): void
    {
        $token = $this->rememberMe->getRememberMeToken();

        $this->assertNull($token);
    }

    public function testCheckWithValidTokenReturnsTokenObject(): void
    {
        // Manually create a remember token in the DB
        $selector  = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(20));
        $expires   = Time::now()->addSeconds(
            (int) service('settings')->get('Auth.sessionConfig')['rememberLength'],
        )->format('Y-m-d H:i:s');

        $this->rememberModel->rememberUser(
            $this->user,
            $selector,
            hash('sha256', $validator),
            $expires,
        );

        // Set the raw cookie on the request (selector:validator)
        $cookiePrefix = (string) (service('settings')->get('Cookie.prefix') ?? '');
        $cookieName   = $cookiePrefix . service('settings')->get('Auth.sessionConfig')['rememberCookieName'];

        service('superglobals')->setCookie($cookieName, $selector . ':' . $validator);

        $result = $this->rememberMe->check();

        $this->assertInstanceOf(stdClass::class, $result);
        $this->assertSame($selector, $result->selector);
        $this->assertSame((string) $this->user->id, (string) $result->user_id);

        // Cleanup
        service('superglobals')->unsetCookie($cookieName);
    }

    public function testCheckWithInvalidValidatorReturnsNull(): void
    {
        // Create a DB record
        $selector  = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(20));
        $expires   = Time::now()->addSeconds(
            (int) service('settings')->get('Auth.sessionConfig')['rememberLength'],
        )->format('Y-m-d H:i:s');

        $this->rememberModel->rememberUser(
            $this->user,
            $selector,
            hash('sha256', $validator),
            $expires,
        );

        // Set the cookie with a WRONG validator
        $cookiePrefix = (string) (service('settings')->get('Cookie.prefix') ?? '');
        $cookieName   = $cookiePrefix . service('settings')->get('Auth.sessionConfig')['rememberCookieName'];

        service('superglobals')->setCookie($cookieName, $selector . ':wrongvalidator');

        $result = $this->rememberMe->check();

        $this->assertNotInstanceOf(stdClass::class, $result);

        // Cleanup
        service('superglobals')->unsetCookie($cookieName);
    }

    public function testCheckWithStolenValidatorPurgesAllUserTokens(): void
    {
        $expires = Time::now()->addSeconds(
            (int) service('settings')->get('Auth.sessionConfig')['rememberLength'],
        )->format('Y-m-d H:i:s');

        // Two valid remember tokens for the same user.
        $selectorA = bin2hex(random_bytes(12));
        $this->rememberModel->rememberUser($this->user, $selectorA, hash('sha256', bin2hex(random_bytes(20))), $expires);

        $selectorB = bin2hex(random_bytes(12));
        $this->rememberModel->rememberUser($this->user, $selectorB, hash('sha256', bin2hex(random_bytes(20))), $expires);

        $cookiePrefix = (string) (service('settings')->get('Cookie.prefix') ?? '');
        $cookieName   = $cookiePrefix . service('settings')->get('Auth.sessionConfig')['rememberCookieName'];

        // Present selectorA with the WRONG validator — a theft/guess signal.
        service('superglobals')->setCookie($cookieName, $selectorA . ':wrong-validator');

        $result = $this->rememberMe->check();
        $this->assertNotInstanceOf(stdClass::class, $result);

        // Theft response: ALL of the user's remember-me tokens are invalidated.
        $this->dontSeeInDatabase($this->tables['remember_tokens'], ['user_id' => $this->user->id]);

        service('superglobals')->unsetCookie($cookieName);
    }

    public function testCheckWithExpiredTokenReturnsNull(): void
    {
        $selector  = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(20));
        // The validator is correct; only the expiry is stale (expired yesterday).
        $expired = Time::now()->subDays(1)->format('Y-m-d H:i:s');

        $this->rememberModel->rememberUser(
            $this->user,
            $selector,
            hash('sha256', $validator),
            $expired,
        );

        $cookiePrefix = (string) (service('settings')->get('Cookie.prefix') ?? '');
        $cookieName   = $cookiePrefix . service('settings')->get('Auth.sessionConfig')['rememberCookieName'];

        service('superglobals')->setCookie($cookieName, $selector . ':' . $validator);

        $result = $this->rememberMe->check();

        // An expired remember-me token must NOT authenticate, even with a valid
        // validator — expiry has to be enforced at validation time, not left to
        // the probabilistic purge.
        $this->assertNotInstanceOf(stdClass::class, $result);

        service('superglobals')->unsetCookie($cookieName);
    }

    public function testCheckWithMalformedCookieReturnsNull(): void
    {
        $cookiePrefix = (string) (service('settings')->get('Cookie.prefix') ?? '');
        $cookieName   = $cookiePrefix . service('settings')->get('Auth.sessionConfig')['rememberCookieName'];

        service('superglobals')->setCookie($cookieName, 'no-colon-separator');

        $result = $this->rememberMe->check();

        $this->assertNotInstanceOf(stdClass::class, $result);

        service('superglobals')->unsetCookie($cookieName);
    }

    public function testPurgeRemovesUserTokens(): void
    {
        $this->rememberMe->rememberUser($this->user);

        $this->seeInDatabase($this->tables['remember_tokens'], ['user_id' => $this->user->id]);

        $this->rememberMe->purge($this->user);

        $this->dontSeeInDatabase($this->tables['remember_tokens'], ['user_id' => $this->user->id]);
    }

    public function testPurgeOldTokensRemovesExpiredRecords(): void
    {
        $selector  = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(20));
        // Insert an already-expired token
        $expired = Time::now()->subDays(1)->format('Y-m-d H:i:s');

        $this->rememberModel->rememberUser($this->user, $selector, hash('sha256', $validator), $expired);

        $this->seeInDatabase($this->tables['remember_tokens'], ['selector' => $selector]);

        $this->rememberMe->purgeOldTokens();

        $this->dontSeeInDatabase($this->tables['remember_tokens'], ['selector' => $selector]);
    }
}
