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

namespace Tests\Extensibility;

use CodeIgniter\Events\Events;
use Config\Services;
use Daycry\Auth\Authentication\Actions\EmailActivator;
use Daycry\Auth\Authentication\Authentication;
use Daycry\Auth\Authentication\Authenticators\AccessToken;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Authentication\Services\UserLockoutManager;
use Daycry\Auth\Config\Auth;
use Daycry\Auth\Config\Services as AuthServices;
use Daycry\Auth\Entities\AccessToken as AccessTokenIdentity;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Interfaces\OAuthTokenRepositoryInterface;
use Daycry\Auth\Interfaces\UserProviderInterface;
use Daycry\Auth\Libraries\Oauth\OauthManager;
use Daycry\Auth\Models\AccessTokenRepository;
use Daycry\Auth\Models\OAuthTokenRepository;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Services\AuditLogger;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeUser;
use Tests\Support\ListensToEvents;

/**
 * The documented ways to extend the library without copying its code:
 * overridable service bindings, protected factories/hooks, the configured
 * User class, and lifecycle events.
 *
 * @internal
 */
final class ExtensionPointsTest extends DatabaseTestCase
{
    use ListensToEvents;
    use FakeUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFakeUser();
    }

    protected function tearDown(): void
    {
        $this->stopListening();

        parent::tearDown();
    }

    public function testNewServicesAreSharedAndCanBeBuiltFresh(): void
    {
        foreach (['auditLogger', 'tokenEmailSender', 'passwordChangeRecorder', 'userAnonymizer'] as $name) {
            $this->assertSame(service($name), service($name), $name);
            $this->assertNotSame(service($name), AuthServices::$name(false), $name);
        }
    }

    public function testOverriddenAuditLoggerReceivesLibraryAuditEvents(): void
    {
        $logger = new class () extends AuditLogger {
            /**
             * @var list<string>
             */
            public array $events = [];

            public function record(string $eventType, ?int $userId, array $metadata = [], ?int $actorId = null): void
            {
                $this->events[] = $eventType;
            }
        };
        Services::injectMock('auditLogger', $logger);

        $this->user->addGroup('admin');

        $this->assertSame([AuditLogger::EVENT_GROUP_ASSIGNED], $logger->events);
        $this->dontSeeInDatabase($this->tables['audit_logs'], ['user_id' => $this->user->id]);
    }

    public function testAccessTokenAuthenticatorUsesTheRepositoryService(): void
    {
        $raw = $this->user->generateAccessToken('api')->raw_token;

        Services::injectMock('accessTokenRepository', new class (model(UserIdentityModel::class)) extends AccessTokenRepository {
            public function getAccessTokenByRawTokenWithUser(string $rawToken): ?AccessTokenIdentity
            {
                return null; // e.g. a revocation list consulted first
            }
        });

        $result = $this->accessTokenAuthenticator()->check(['token' => $raw]);

        $this->assertFalse($result->isOK());
        $this->assertSame(lang('Auth.badToken'), $result->reason());
    }

    public function testOauthManagerUsesTheRepositoryService(): void
    {
        $repository = new OAuthTokenRepository(model(UserIdentityModel::class));
        Services::injectMock('oauthTokenRepository', $repository);

        $manager = new class (config('AuthOAuth')) extends OauthManager {
            public function repository(): OAuthTokenRepositoryInterface
            {
                return $this->getRepository();
            }
        };

        $this->assertSame($repository, $manager->repository());
    }

    public function testConfiguredUserClassIsHonouredByHandBuiltQueries(): void
    {
        model(UserIdentityModel::class)->createEmailIdentity($this->user, [
            'email'    => 'custom@example.com',
            'password' => 'secret-password-1',
        ]);
        $raw = $this->user->generateAccessToken('api')->raw_token;

        $this->injectMockAttributes(['userProvider' => CustomUserModel::class]);
        Services::resetSingle('auth');

        $this->assertInstanceOf(CustomUser::class, (new CustomUserModel())->findByCredentials(['email' => 'custom@example.com']));
        $this->assertInstanceOf(CustomUser::class, service('accessTokenRepository')->getAccessTokenByRawTokenWithUser($raw)?->user());
    }

    public function testSessionCollaboratorsComeFromOverridableFactories(): void
    {
        $lockout = new UserLockoutManager(model(UserModel::class));

        SessionWithCustomLockout::$lockout = $lockout;

        try {
            $session = SessionWithCustomLockout::instance(model(UserModel::class));
        } finally {
            SessionWithCustomLockout::$lockout = null;
        }

        $this->assertSame($lockout, $session->getLockoutManager());
    }

    public function testActivationIsRequiredForEmailActivatorSubclasses(): void
    {
        setting('Auth.actions', ['register' => VerifyAddress::class, 'login' => null]);

        try {
            $this->user->active = false;

            $this->assertFalse($this->user->isActivated());
        } finally {
            setting()->forget('Auth.actions');
        }
    }

    public function testBanAndUnbanFireEvents(): void
    {
        $seen = [];
        $this->listen('user-banned', static function (User $user, ?string $message = null) use (&$seen): void {
            $seen[] = ['banned', $user->id, $message];
        });
        $this->listen('user-unbanned', static function (User $user) use (&$seen): void {
            $seen[] = ['unbanned', $user->id, null];
        });

        $this->user->ban('Spam');
        $this->user->unBan();

        $this->assertSame([['banned', $this->user->id, 'Spam'], ['unbanned', $this->user->id, null]], $seen);
    }

    public function testLockoutFiresUserLocked(): void
    {
        $locked = null;
        $this->listen('user-locked', static function (User $user, string $until = '') use (&$locked): void {
            $locked = [$user->id, $until];
        });

        $manager = new UserLockoutManager(model(UserModel::class));

        for ($i = 0; $i < (int) setting('AuthSecurity.userMaxAttempts'); $i++) {
            $manager->recordFailedAttempt($this->user);
        }

        $this->assertIsArray($locked);
        $this->assertSame($this->user->id, $locked[0]);
    }

    public function testAccessTokenLifecycleFiresEvents(): void
    {
        $seen = [];
        $this->listen('access-token-created', static function (User $user, ?AccessTokenIdentity $token = null) use (&$seen): void {
            $seen[] = 'created:' . $token?->name;
        });
        $this->listen('access-token-revoked', static function (User $user, ?AccessTokenIdentity $token = null) use (&$seen): void {
            $seen[] = 'revoked:' . ($token->name ?? 'all');
        });

        $repository = service('accessTokenRepository');
        $token      = $repository->generateAccessToken($this->user, 'cli');
        $repository->softRevokeAccessToken($this->user, $token->raw_token);
        $repository->softRevokeAllAccessTokens($this->user);

        $this->assertSame(['created:cli', 'revoked:cli', 'revoked:all'], $seen);
        $this->assertNotNull($token->raw_token, 'the caller still receives the raw token');
    }

    public function testAccessTokenCreatedEventOmitsTheRawToken(): void
    {
        $raw = 'unset';
        $this->listen('access-token-created', static function (User $user, ?AccessTokenIdentity $token = null) use (&$raw): void {
            $raw = $token?->raw_token;
        });

        service('accessTokenRepository')->generateAccessToken($this->user, 'cli');

        $this->assertNull($raw);
    }

    private function accessTokenAuthenticator(): AccessToken
    {
        $config                           = new Auth();
        $config->authenticators['tokens'] = AccessToken::class;
        $authentication                   = new Authentication($config);
        $authentication->setProvider(model(UserModel::class));

        /** @var AccessToken */
        return $authentication->factory('tokens');
    }
}

/**
 * @internal
 */
final class CustomUser extends User
{
}

/**
 * @internal
 */
final class CustomUserModel extends UserModel
{
    protected $returnType = CustomUser::class;
}

/**
 * A Session whose lockout manager comes from the overridable factory.
 *
 * @internal
 */
final class SessionWithCustomLockout extends Session
{
    public static ?UserLockoutManager $lockout = null;

    protected function createLockoutManager(UserProviderInterface $provider): UserLockoutManager
    {
        return self::$lockout ?? parent::createLockoutManager($provider);
    }
}

/**
 * A custom activation action whose name does not contain "Activator".
 *
 * @internal
 */
final class VerifyAddress extends EmailActivator
{
}
