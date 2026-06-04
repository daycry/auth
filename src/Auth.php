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

namespace Daycry\Auth;

use BadMethodCallException;
use CodeIgniter\Router\RouteCollection;
use Daycry\Auth\Authentication\Authentication;
use Daycry\Auth\Config\Auth as AuthConfig;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Entities\UserIdentity;
use Daycry\Auth\Exceptions\AuthenticationException;
use Daycry\Auth\Interfaces\ActionInterface;
use Daycry\Auth\Interfaces\AuthenticatorInterface;
use Daycry\Auth\Interfaces\UserProviderInterface;

/**
 * Facade for Authentication
 *
 * Common methods (defined by AuthenticatorInterface — available on every
 * authenticator: Session, AccessToken, JWT):
 *
 * @method Result               attempt(array $credentials)
 * @method Result               check(array $credentials)
 * @method bool                 checkAction(UserIdentity $identity, string $token)
 * @method void                 completeLogin(User $user)
 * @method void                 forget(?User $user = null)
 * @method ActionInterface|null getAction()
 * @method mixed                getLogCredentials(array $credentials)
 * @method string               getPendingMessage()
 * @method User|null            getPendingUser()
 * @method User|null            getUser()
 * @method bool                 hasAction(int|string|null $userId = null)
 * @method bool                 isAnonymous()
 * @method bool                 isPending()
 * @method bool                 loggedIn()
 * @method void                 login(User $user, bool $actions = true)
 * @method void                 loginById(int|string $userId)
 * @method void                 logout()
 * @method void                 recordActiveDate()
 *
 * Session-only methods (calling these through a stateless authenticator throws):
 * @method self remember(bool $shouldRemember = true)
 * @method void startLogin(User $user)
 * @method bool startUpAction(string $type, User $user)
 */
class Auth
{
    /**
     * The current version of CodeIgniter Shield
     */
    public const SHIELD_VERSION = '5.0.0';

    protected ?UserProviderInterface $userProvider = null;
    protected ?Authentication $authenticate        = null;

    /**
     * The Authenticator alias to use for this request.
     */
    protected ?string $alias = null;

    public function __construct(protected AuthConfig $config)
    {
    }

    protected function ensureAuthentication(): void
    {
        if ($this->authenticate !== null) {
            return;
        }

        $authenticate = new Authentication($this->config);
        $authenticate->setProvider($this->getProvider());

        $this->authenticate = $authenticate;
    }

    /**
     * Sets the Authenticator alias that should be used for this request.
     *
     * @return $this
     */
    public function setAuthenticator(?string $alias = null): self
    {
        if ($alias !== null) {
            $this->alias = $alias;
        }

        return $this;
    }

    /**
     * Returns the current authentication class.
     */
    public function getAuthenticator(): AuthenticatorInterface
    {
        $this->ensureAuthentication();

        return $this->authenticate
            ->factory($this->alias);
    }

    /**
     * Returns the current user, if logged in.
     */
    public function user(): ?User
    {
        return $this->getAuthenticator()->loggedIn()
            ? $this->getAuthenticator()->getUser()
            : null;
    }

    /**
     * Returns the current user's id, if logged in.
     *
     * @return int|string|null
     */
    public function id()
    {
        $user = $this->user();

        return ($user !== null) ? $user->id : null;
    }

    public function authenticate(array $credentials): Result
    {
        $this->ensureAuthentication();

        return $this->authenticate
            ->factory($this->alias)
            ->attempt($credentials);
    }

    /**
     * Will set the routes in your application to use
     * the Shield auth routes.
     *
     * Usage (in Config/Routes.php):
     *      - auth()->routes($routes);
     *      - auth()->routes($routes, ['except' => ['login', 'register']])
     */
    public function routes(RouteCollection &$routes, array $config = []): void
    {
        $authRoutes = config('Auth')->routes;

        $namespace = $config['namespace'] ?? 'Daycry\Auth\Controllers';

        // Defense in depth: skip the WebAuthn route group entirely when the
        // feature is globally disabled (the controller also 404s per-method).
        $disabledGroups = [];
        if (! (bool) (setting('AuthSecurity.webauthnEnabled') ?? false)) {
            $disabledGroups[] = 'webauthn';
        }

        $routes->group('/', ['namespace' => $namespace], static function (RouteCollection $routes) use ($authRoutes, $config, $disabledGroups): void {
            foreach ($authRoutes as $name => $row) {
                if (in_array($name, $disabledGroups, true)) {
                    continue;
                }
                if (! isset($config['except']) || ! in_array($name, $config['except'], true)) {
                    foreach ($row as $params) {
                        $options = isset($params[3])
                            ? ['as' => $params[3]]
                            : null;
                        $routes->{$params[0]}($params[1], $params[2], $options);
                    }
                }
            }
        });
    }

    /**
     * Returns the Model that is responsible for getting users.
     *
     * @throws AuthenticationException
     */
    public function getProvider(): UserProviderInterface
    {
        if ($this->userProvider !== null) {
            return $this->userProvider;
        }

        $className          = $this->config->userProvider;
        $this->userProvider = new $className();

        return $this->userProvider;
    }

    /**
     * Provide magic function-access to Authenticators to save use
     * from repeating code here, and to allow them to have their
     * own, additional, features on top of the required ones,
     * like "remember-me" functionality.
     *
     * @param list<string> $args
     *
     * @throws BadMethodCallException When the active authenticator has no such method.
     */
    public function __call(string $method, array $args)
    {
        $this->ensureAuthentication();

        $authenticator = $this->authenticate->factory($this->alias);

        if (method_exists($authenticator, $method)) {
            return $authenticator->{$method}(...$args);
        }

        // Fail loudly instead of silently returning null: a typo or a
        // Session-only method called on a stateless authenticator must surface
        // immediately rather than be misread as a falsy "not logged in" result.
        throw new BadMethodCallException(sprintf(
            'Method %s::%s() does not exist on the "%s" authenticator.',
            $authenticator::class,
            $method,
            $this->alias,
        ));
    }
}
