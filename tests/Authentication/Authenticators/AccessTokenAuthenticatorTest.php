<?php

declare(strict_types=1);

namespace Tests\Authentication\Authenticators;

use CodeIgniter\I18n\Time;
use Daycry\Auth\Authentication\Authentication;
use Daycry\Auth\Authentication\Authenticators\AccessToken;
use Daycry\Auth\Config\Auth;
use Daycry\Auth\Config\Auth as AuthConfig;
use Daycry\Auth\Entities\AccessToken as AccessTokenEntity;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use CodeIgniter\Test\Mock\MockEvents;
use Config\Services;
use Daycry\Auth\Exceptions\AuthenticationException;
use Daycry\Auth\Exceptions\InvalidArgumentException;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class AccessTokenAuthenticatorTest extends DatabaseTestCase
{
    private AccessToken $auth;

    protected function setUp(): void
    {
        parent::setUp();

        $config = new Auth();
        $auth   = new Authentication($config);
        $auth->setProvider(model(UserModel::class));

        /** @var AccessTokens $authenticator */
        $authenticator = $auth->factory('access_token');
        $this->auth    = $authenticator;

        Services::injectMock('events', new MockEvents());
    }

    public function testLogin(): void
    {
        $user = fake(UserModel::class);

        $this->auth->login($user);
        $this->auth->recordActiveDate();
        // Stores the user
        $this->assertInstanceOf(User::class, $this->auth->getUser());
        $this->assertSame($user->id, $this->auth->getUser()->id);
    }

    public function testErrorRecordActiveDate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->auth->recordActiveDate();
    }

    public function testLogout(): void
    {
        // this one's a little odd since it's stateless, but roll with it...
        $user = fake(UserModel::class);

        $this->auth->login($user);
        $this->assertNotNull($this->auth->getUser());

        $this->auth->logout();
        $this->assertNull($this->auth->getUser());
    }

    public function testLoginByIdNoToken(): void
    {
        $user = fake(UserModel::class);

        $this->assertFalse($this->auth->loggedIn());

        $this->auth->loginById($user->id);

        $this->assertTrue($this->auth->loggedIn());
        $this->assertNull($this->auth->getUser()->currentAccessToken());
    }

    public function testLoginByIdWithToken(): void
    {
        /** @var User $user */
        $user  = fake(UserModel::class);
        $token = $user->generateAccessToken('foo');

        $this->setRequestHeader($token->raw_token);

        $this->auth->loginById($user->id);

        $this->assertTrue($this->auth->loggedIn());
        $this->assertInstanceOf(AccessTokenEntity::class, $this->auth->getUser()->currentAccessToken());
        $this->assertSame($token->id, $this->auth->getUser()->currentAccessToken()->id);
    }

    public function testLoginByIdWithInvalidUser(): void
    {
        $this->expectException(AuthenticationException::class);
        /** @var User $user */
        $user  = fake(UserModel::class);
        $token = $user->generateAccessToken('foo');

        $this->setRequestHeader($token->raw_token);

        $this->auth->loginById(5);
    }

    public function testLoginByIdWithMultipleTokens(): void
    {
        /** @var User $user */
        $user   = fake(UserModel::class);
        $token1 = $user->generateAccessToken('foo');
        $user->generateAccessToken('bar');

        $this->setRequestHeader($token1->raw_token);

        $this->auth->loginById($user->id);

        $this->assertTrue($this->auth->loggedIn());
        $this->assertInstanceOf(AccessTokenEntity::class, $this->auth->getUser()->currentAccessToken());
        $this->assertSame($token1->id, $this->auth->getUser()->currentAccessToken()->id);
    }

    public function testCheckNoToken(): void
    {
        $result = $this->auth->check([]);

        $this->assertFalse($result->isOK());
        $this->assertSame(
            lang('Auth.noToken', [service('settings')->get('Auth.authenticatorHeader')['access_token']]),
            $result->reason()
        );
    }

    public function testCheckOldToken(): void
    {
        /** @var User $user */
        $user = fake(UserModel::class);
        /** @var UserIdentityModel $identities */
        $identities = model(UserIdentityModel::class);
        $token      = $user->generateAccessToken('foo');
        // CI 4.2 uses the Chicago timezone that has Daylight Saving Time,
        // so subtracts 1 hour to make sure this test passes.
        $token->last_used_at = Time::now()->subYears(1)->subHours(1)->subMinutes(1);
        $identities->save($token);

        $result = $this->auth->check(['token' => $token->raw_token]);

        $this->assertFalse($result->isOK());
        $this->assertSame(lang('Auth.oldToken'), $result->reason());
    }

    public function testCheckSuccess(): void
    {
        /** @var User $user */
        $user  = fake(UserModel::class);
        $token = $user->generateAccessToken('foo');

        $this->seeInDatabase($this->tables['identities'], [
            'user_id'      => $user->id,
            'type'         => 'access_token',
            'last_used_at' => null,
        ]);

        $result = $this->auth->check(['token' => $token->raw_token]);

        $this->assertTrue($result->isOK());
        $this->assertInstanceOf(User::class, $result->extraInfo());
        $this->assertSame($user->id, $result->extraInfo()->id);

        $updatedToken = $result->extraInfo()->currentAccessToken();
        $this->assertNotEmpty($updatedToken->last_used_at);

        // Checking token in the same second does not throw "DataException : There is no data to update."
        $this->auth->check(['token' => $token->raw_token]);
    }

    public function testAttemptCannotFindUser(): void
    {
        $result = $this->auth->attempt([
            'token' => 'abc123',
        ]);

        $this->assertFalse($result->isOK());
        $this->assertSame(lang('Auth.badToken'), $result->reason());

        // A failed login attempt should have been recorded by default.
        $this->seeInDatabase($this->tables['logins'], [
            'id_type'    => AccessToken::ID_TYPE_ACCESS_TOKEN,
            'identifier' => 'abc123',
            'success'    => 0,
        ]);
    }

    public function testAttemptSuccess(): void
    {
        /** @var User $user */
        $user  = fake(UserModel::class);
        $token = $user->generateAccessToken('foo');
        $this->setRequestHeader($token->raw_token);

        $result = $this->auth->attempt([
            'token' => $token->raw_token,
        ]);

        $this->assertTrue($result->isOK());

        $foundUser = $result->extraInfo();
        $this->assertInstanceOf(User::class, $foundUser);
        $this->assertSame($user->id, $foundUser->id);
        $this->assertInstanceOf(AccessTokenEntity::class, $foundUser->currentAccessToken());
        $this->assertSame($token->token, $foundUser->currentAccessToken()->token);

        // A successful login attempt is not recorded by default.
        $this->seeInDatabase($this->tables['logins'], [
            'id_type'    => AccessToken::ID_TYPE_ACCESS_TOKEN,
            'identifier' => $token->raw_token,
            'success'    => 1,
        ]);
    }

    public function testAttemptSuccessLog(): void
    {
        // Change $recordLoginAttempt in Config.
        /** @var AuthToken $config */
        $config                     = config('Auth');
        $config->recordLoginAttempt = Auth::RECORD_LOGIN_ATTEMPT_ALL;

        /** @var User $user */
        $user  = fake(UserModel::class);
        $token = $user->generateAccessToken('foo');
        $this->setRequestHeader($token->raw_token);

        $result = $this->auth->attempt([
            'token' => $token->raw_token,
        ]);

        $this->assertTrue($result->isOK());

        $foundUser = $result->extraInfo();
        $this->assertInstanceOf(User::class, $foundUser);
        $this->assertSame($user->id, $foundUser->id);
        $this->assertInstanceOf(AccessTokenEntity::class, $foundUser->currentAccessToken());
        $this->assertSame($token->token, $foundUser->currentAccessToken()->token);

        $this->seeInDatabase($this->tables['logins'], [
            'id_type'    => AccessToken::ID_TYPE_ACCESS_TOKEN,
            'identifier' => $token->raw_token,
            'success'    => 1,
        ]);
    }

    public function testCheckBadToken(): void
    {
        $result = $this->auth->check(['token' => 'abc123']);

        $this->assertFalse($result->isOK());
        $this->assertSame(lang('Auth.badToken'), $result->reason());
    }

    protected function setRequestHeader(string $token): void
    {
        $request = service('request');
        $request->setHeader('X-API-KEY', $token);
    }
}