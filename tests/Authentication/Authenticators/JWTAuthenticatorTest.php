<?php

declare(strict_types=1);

namespace Tests\Authentication\Authenticators;

use CodeIgniter\Config\Services;
use CodeIgniter\Test\Mock\MockEvents;
use Daycry\Auth\Auth;
use Daycry\Auth\Authentication\Authentication;
use Daycry\Auth\Authentication\Authenticators\JWT;
use Daycry\Auth\Authentication\JWT\Adapters\DaycryJWTAdapter;
use Daycry\Auth\Config\Auth as ConfigAuth;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Exceptions\AuthenticationException;
use Daycry\Auth\Exceptions\InvalidArgumentException;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Result;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class JWTAuthenticatorTest extends DatabaseTestCase
{
    private const BAD_JWT = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJJc3N1ZXIgb2YgdGhlIEpXVCIsImF1ZCI6IkF1ZGllbmNlIG9mIHRoZSBKV1QiLCJzdWIiOiIxIiwiaWF0IjoxNjUzOTkxOTg5LCJleHAiOjE2NTM5OTU1ODl9.hgOYHEcT6RGHb3po1lspTcmjrylY1Cy1IvYmHOyx0CY';

    private JWT $auth;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $config                        = new ConfigAuth();
        $config->authenticators['jwt'] = JWT::class;

        $auth = new Authentication($config);
        $auth->setProvider(\model(UserModel::class));

        /** @var JWT $authenticator */
        $authenticator = $auth->factory('jwt');
        $this->auth    = $authenticator;

        Services::injectMock('events', new MockEvents());
    }

    private function createUser(): User
    {
        return \fake(UserModel::class);
    }

    public function testLogin(): void
    {
        $user = $this->createUser();

        $this->auth->login($user);

        // Stores the user
        $this->assertInstanceOf(User::class, $this->auth->getUser());
        $this->assertSame($user->id, $this->auth->getUser()->id);
    }

    public function testLogout(): void
    {
        // this one's a little odd since it's stateless, but roll with it...

        $user = $this->createUser();

        $this->auth->login($user);
        $this->assertNotNull($this->auth->getUser());

        $this->auth->logout();
        $this->assertNull($this->auth->getUser());
    }

    public function testLoginById(): void
    {
        $user = $this->createUser();

        $this->assertFalse($this->auth->loggedIn());

        $this->auth->loginById($user->id);

        $this->assertTrue($this->auth->loggedIn());
    }

    public function testLoginByIdNoUser(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Unable to locate the specified user.');

        $this->createUser();

        $this->assertFalse($this->auth->loggedIn());

        $this->auth->loginById(9999);
    }

    public function testCheckNoToken(): void
    {
        $result = $this->auth->check([]);

        $this->assertFalse($result->isOK());
        $this->assertSame(
            \lang('Auth.noToken', [service('settings')->get('Auth.authenticatorHeader')['jwt']]),
            $result->reason()
        );
    }

    public function testCheckBadSignatureToken(): void
    {
        $result = $this->auth->check(['token' => self::BAD_JWT]);

        $this->assertFalse($result->isOK());
        $this->assertSame(lang('Auth.invalidJWT'), $result->reason());
    }

    public function testCheckNoSubToken(): void
    {
        $payload = null;
        $token = (new DaycryJWTAdapter())->encode($payload);

        $result = $this->auth->check(['token' => $token]);

        $this->assertFalse($result->isOK());
        $this->assertSame('Invalid JWT: no user_id', $result->reason());
    }

    public function testCheckNoUserInDatabase(): void
    {
        $token = $this->generateJWT();

        $users = model(UserModel::class);
        $users->delete(1);

        $result = $this->auth->check(['token' => $token]);

        $this->assertFalse($result->isOK());
        $this->assertSame(lang('Auth.invalidUser'), $result->reason());
    }

    public function testCheckSuccess(): void
    {
        $token = $this->generateJWT();

        $result = $this->auth->check(['token' => $token]);

        $this->assertTrue($result->isOK());
        $this->assertInstanceOf(User::class, $result->extraInfo());
        $this->assertSame(1, $result->extraInfo()->id);
    }

    public function testGetPayload(): void
    {
        $token = $this->generateJWT();

        $this->auth->check(['token' => $token]);
        $payload = $this->auth->getPayload();

        $this->assertSame($this->user->id, $payload);
    }

    public function testAttemptBadSignatureToken(): void
    {
        $result = $this->auth->attempt([
            'token' => self::BAD_JWT,
        ]);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertFalse($result->isOK());
        $this->assertSame(lang('Auth.invalidJWT'), $result->reason());

        // A login attempt should have always been recorded
        $this->seeInDatabase('auth_logins', [
            'id_type'    => JWT::ID_TYPE_JWT,
            'identifier' => self::BAD_JWT,
            'success'    => 0,
        ]);
    }

    public function testAttemptBannedUser(): void
    {
        $token = $this->generateJWT();

        $this->user->ban();

        $result = $this->auth->attempt([
            'token' => $token,
        ]);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertFalse($result->isOK());
        $this->assertSame(lang('Auth.bannedUser'), $result->reason());

        // The login attempt should have been recorded
        $this->seeInDatabase('auth_logins', [
            'id_type'    => JWT::ID_TYPE_JWT,
            'identifier' => $token,
            'success'    => 0,
            'user_id'    => $this->user->id,
        ]);
    }

    public function testAttemptSuccess(): void
    {
        // Change $recordLoginAttempt in Config.
        /** @var AuthJWT $config */
        $config                     = config('Auth');
        $config->recordLoginAttempt = ConfigAuth::RECORD_LOGIN_ATTEMPT_ALL;

        $token = $this->generateJWT();

        $result = $this->auth->attempt([
            'token' => $token,
        ]);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertTrue($result->isOK());

        $foundUser = $result->extraInfo();

        $this->assertInstanceOf(User::class, $foundUser);
        $this->assertSame(1, $foundUser->id);

        // A login attempt should have been recorded
        $this->seeInDatabase('auth_logins', [
            'id_type'    => JWT::ID_TYPE_JWT,
            'identifier' => $token,
            'success'    => 1,
        ]);
    }

    public function testRecordActiveDateNoUser(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Daycry\Auth\Authentication\Authenticators\Base::recordActiveDate() requires logged in user before calling.'
        );

        $this->auth->recordActiveDate();
    }

    /**
     * @param Time|null $clock The Time object
     */
    private function generateJWT(): string
    {
        $this->user = fake(UserModel::class, ['id' => 1, 'username' => 'John Smith']);

        $generator = new DaycryJWTAdapter();

        return $generator->encode($this->user->id);
    }
}