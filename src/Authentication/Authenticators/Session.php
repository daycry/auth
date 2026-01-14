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

namespace Daycry\Auth\Authentication\Authenticators;

use CodeIgniter\Config\Factories;
use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\LogicException;
use CodeIgniter\HTTP\Request;
use CodeIgniter\I18n\Time;
use Config\Services;
use Daycry\Auth\Authentication\Services\RememberMe;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Entities\UserIdentity;
use Daycry\Auth\Enums\AuthenticationState;
use Daycry\Auth\Enums\IdentityType;
use Daycry\Auth\Exceptions\AuthenticationException;
use Daycry\Auth\Exceptions\InvalidArgumentException;
use Daycry\Auth\Exceptions\SecurityException;
use Daycry\Auth\Interfaces\ActionInterface;
use Daycry\Auth\Interfaces\AuthenticatorInterface;
use Daycry\Auth\Interfaces\UserProviderInterface;
use Daycry\Auth\Models\LoginModel;
use Daycry\Auth\Models\RememberModel;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Result;

class Session extends Base implements AuthenticatorInterface
{
    /**
     * @var string Special ID Type.
     *             `username` is stored in `users` table, so no `auth_identities` record.
     */
    public const ID_TYPE_USERNAME = IdentityType::USERNAME->value;

    // Identity types
    public const ID_TYPE_EMAIL_PASSWORD = IdentityType::EMAIL_PASSWORD->value;
    public const ID_TYPE_MAGIC_LINK     = IdentityType::MAGIC_LINK->value;
    public const ID_TYPE_EMAIL_2FA      = IdentityType::EMAIL_2FA->value;
    public const ID_TYPE_EMAIL_ACTIVATE = IdentityType::EMAIL_ACTIVATE->value;

    /**
     * The User auth state
     */
    private AuthenticationState $userState = AuthenticationState::UNKNOWN;

    /**
     * Should the user be remembered?
     */
    protected bool $shouldRemember = false;

    public function __construct(
        UserProviderInterface $provider,
        Request $request,
        UserIdentityModel $userIdentityModel,
        LoginModel $loginModel,
        protected RememberMe $rememberMe,
    ) {
        $this->method = self::ID_TYPE_USERNAME;

        parent::__construct($provider, $request, $userIdentityModel, $loginModel);

        $this->checkSecurityConfig();
    }

    public static function instance(UserProviderInterface $provider): static
    {
        /** @var RememberModel $rememberModel */
        $rememberModel = model(RememberModel::class);

        return new static(
            $provider,
            Services::request(),
            model(UserIdentityModel::class),
            model(LoginModel::class),
            new RememberMe($rememberModel),
        );
    }

    /**
     * Checks less secure Configuration.
     */
    private function checkSecurityConfig(): void
    {
        /** @var Security $securityConfig */
        $securityConfig = config('Security');

        if ($securityConfig->csrfProtection === 'cookie') {
            throw new SecurityException(
                'Config\Security::$csrfProtection is set to \'cookie\'.'
                    . ' Same-site attackers may bypass the CSRF protection.'
                    . ' Please set it to \'session\'.',
            );
        }
    }

    /**
     * Sets the $shouldRemember flag
     *
     * @return $this
     */
    public function remember(bool $shouldRemember = true): self
    {
        $this->shouldRemember = $shouldRemember;

        return $this;
    }

    /**
     * Removes any remember-me tokens, if applicable.
     */
    public function forget(?User $user = null): void
    {
        $user ??= $this->user;
        if ($user === null) {
            return;
        }

        $this->rememberMe->purge($user);
    }

    /**
     * Attempts to authenticate a user with the given $credentials.
     * Logs the user in with a successful check.
     *
     * @phpstan-param array{email?: string, username?: string, password?: string} $credentials
     */
    public function attempt(array $credentials = []): Result
    {
        return $this->checkLogin($credentials);
    }

    /**
     * Checks a user's $credentials to see if they match an
     * existing user.
     *
     * @phpstan-param array{email?: string, username?: string, password?: string} $credentials
     */
    public function check(array $credentials = []): Result
    {
        // Can't validate without a password.
        if (empty($credentials['password']) || count($credentials) < 2) {
            return new Result([
                'success' => false,
                'reason'  => lang('Auth.badAttempt'),
            ]);
        }

        // Remove the password from credentials so we can
        // check afterword.
        $givenPassword = $credentials['password'];
        unset($credentials['password']);

        // Find the existing user
        $user = $this->provider->findByCredentials($credentials);

        if ($user === null) {
            return new Result([
                'success' => false,
                'reason'  => lang('Auth.badAttempt'),
            ]);
        }

        /** @var Passwords $passwords */
        $passwords = service('passwords');

        // Now, try matching the passwords.
        if (! $passwords->verify($givenPassword, $user->password_hash)) {
            return new Result([
                'success' => false,
                'reason'  => lang('Auth.invalidPassword'),
            ]);
        }

        // Check to see if the password needs to be rehashed.
        // This would be due to the hash algorithm or hash
        // cost changing since the last time that a user
        // logged in.
        if ($passwords->needsRehash($user->password_hash)) {
            $user->password_hash = $passwords->hash($givenPassword);
            $this->provider->save($user);
        }

        return new Result([
            'success'   => true,
            'extraInfo' => $user,
        ]);
    }

    /**
     * Returns the current pending login User.
     */
    public function getPendingUser(): ?User
    {
        $this->checkUserState();

        if ($this->userState === AuthenticationState::PENDING) {
            return $this->user;
        }

        return null;
    }

    /**
     * Returns an action object from the session data
     */
    public function getAction(): ?ActionInterface
    {
        /** @var class-string<ActionInterface>|null $actionClass */
        $actionClass = $this->getSessionKey('auth_action');

        if ($actionClass === null) {
            return null;
        }

        return Factories::actions($actionClass); // @phpstan-ignore-line
    }

    /**
     * Check token in Action
     *
     * @param string $token Token to check
     */
    public function checkAction(UserIdentity $identity, string $token): bool
    {
        $user = ($this->loggedIn() || $this->isPending()) ? $this->user : null;

        if ($user === null) {
            throw new LogicException('Cannot get the User.');
        }

        if ($token === '' || $token === '0' || $token !== $identity->secret) {
            return false;
        }

        // On success - remove the identity
        $this->userIdentityModel->deleteIdentitiesByType($user, $identity->type);

        // Clean up our session
        $this->removeSessionKey('auth_action');
        $this->removeSessionKey('auth_action_message');

        $this->user = $user;

        $this->completeLogin($user);

        return true;
    }

    /**
     * Remove the key value in Session User Info
     */
    private function removeSessionKey(string $key): void
    {
        $sessionUserInfo = $this->getSessionUserInfo();
        unset($sessionUserInfo[$key]);
        session()->set(setting('Auth.sessionConfig')['field'], $sessionUserInfo);
    }

    /**
     * Checks if the user is currently in pending login state.
     * They need to do an auth action.
     */
    public function isPending(): bool
    {
        $this->checkUserState();

        return $this->userState === AuthenticationState::PENDING;
    }

    /**
     * Checks if the visitor is anonymous. The user's id is unknown.
     * They are not logged in, are not in pending login state.
     */
    public function isAnonymous(): bool
    {
        $this->checkUserState();

        return $this->userState === AuthenticationState::ANONYMOUS;
    }

    /**
     * Returns pending login error message
     */
    public function getPendingMessage(): string
    {
        $this->checkUserState();

        return $this->getSessionKey('auth_action_message') ?? '';
    }

    /**
     * Logs the given user in.
     */
    public function login(User $user, bool $actions = true): void
    {
        $this->user = $user;

        if ($actions) {
            // Update the user's last used date on their password identity.
            $identity = $user->getEmailIdentity();
            if ($identity instanceof UserIdentity) {
                $this->userIdentityModel->touchIdentity($identity);
            }

            // Set auth action from database.
            $this->setAuthAction();

            // If an action has been defined for login, start it up.
            $this->startUpAction('login', $user);

            $this->startLogin($user);

            $this->issueRememberMeToken();

            if (! $this->hasAction()) {
                $this->completeLogin($user);
            }
        } else {
            // Check identities for actions
            if ($this->getIdentitiesForAction($user) !== []) {
                throw new LogicException(
                    'The user has identities for action, so cannot complete login.'
                    . ' If you want to start to login with auth action, use startLogin() instead.'
                    . ' Or delete identities for action in database.'
                    . ' user_id: ' . $user->id,
                );
            }
            // Check auth_action in Session
            if ($this->getSessionKey('auth_action')) {
                throw new LogicException(
                    'The user has auth action in session, so cannot complete login.'
                    . ' If you want to start to login with auth action, use startLogin() instead.'
                    . ' Or delete `auth_action` and `auth_action_message` in session data.'
                    . ' user_id: ' . $user->id,
                );
            }

            $this->startLogin($user);

            $this->issueRememberMeToken();

            $this->completeLogin($user);
        }
    }

    /**
     * Logs the current user out.
     */
    public function logout(): void
    {
        $this->checkUserState();

        if ($this->user === null) {
            return;
        }

        // Destroy the session data - but ensure a session is still
        // available for flash messages, etc.
        /** @var \CodeIgniter\Session\Session $session */
        $session     = session();
        $sessionData = $session->get();
        if (isset($sessionData)) {
            foreach (array_keys($sessionData) as $key) {
                $session->remove($key);
            }
        }

        // Regenerate the session ID for a touch of added safety.
        $session->regenerate(true);

        // Take care of any remember-me functionality
        $this->rememberMe->purge($this->user);

        // Trigger logout event
        Events::trigger('logout', $this->user);

        $this->user      = null;
        $this->userState = AuthenticationState::ANONYMOUS;
    }

    /**
     * Returns the current user instance.
     */
    public function getUser(): ?User
    {
        $this->checkUserState();

        if ($this->userState === AuthenticationState::LOGGED_IN) {
            return $this->user;
        }

        return null;
    }

    /**
     * Logs a user in based on their ID.
     *
     * @param int|string $userId
     */
    public function loginById($userId): void
    {
        $user = $this->provider->findById($userId);

        if (! $user instanceof User) {
            throw AuthenticationException::forInvalidUser();
        }

        $this->login($user, false);
    }

    /**
     * Gets identities for action
     *
     * @return list<UserIdentity>
     */
    private function getIdentitiesForAction(User $user): array
    {
        return $this->userIdentityModel->getIdentitiesByTypes(
            $user,
            $this->getActionTypes(),
        );
    }

    /**
     * @return list<string>
     */
    private function getActionTypes(): array
    {
        $actions = setting('Auth.actions');
        $types   = [];

        foreach ($actions as $actionClass) {
            if ($actionClass === null) {
                continue;
            }

            /** @var ActionInterface $action */
            $action  = Factories::actions($actionClass);  // @phpstan-ignore-line
            $types[] = $action->getType();
        }

        return $types;
    }

    /**
     * Completes login process
     */
    public function completeLogin(User $user): void
    {
        $this->userState = AuthenticationState::LOGGED_IN;

        // a successful login
        Events::trigger('login', $user);
    }

    /**
     * Has Auth Action?
     *
     * @param int|string|null $userId Provide user id only when checking a
     *                                not-logged-in user
     *                                (e.g. user who tries magic-link login)
     */
    public function hasAction($userId = null): bool
    {
        // Check not-logged-in user
        if ($userId !== null) {
            $user = $this->provider->findById($userId);

            // Check identities for actions
            if ($this->getIdentitiesForAction($user) !== []) {
                // Make pending login state
                $this->user = $user;
                $this->setSessionKey('id', $user->id);
                $this->setAuthAction();

                return true;
            }
        }

        // Check the Session
        if ($this->getSessionKey('auth_action')) {
            return true;
        }

        // Check the database
        return $this->setAuthAction();
    }

    private function issueRememberMeToken(): void
    {
        if ($this->shouldRemember && setting('Auth.sessionConfig')['allowRemembering']) {
            $this->rememberMe->rememberUser($this->user);

            // Reset so it doesn't mess up future calls.
            $this->shouldRemember = false;
        } elseif ($this->rememberMe->getRememberMeToken() !== null && $this->rememberMe->getRememberMeToken() !== '' && $this->rememberMe->getRememberMeToken() !== '0') {
            $this->rememberMe->removeKey();

            // @TODO delete the token record.
        }

        // We'll give a 20% chance to need to do a purge since we
        // don't need to purge THAT often, it's just a maintenance issue.
        // to keep the table from getting out of control.
        if (random_int(1, 100) <= 20) {
            $this->rememberMe->purgeOldTokens();
        }
    }

    /**
     * If an action has been defined, start it up.
     *
     * @param string $type 'register', 'login'
     *
     * @return bool If the action has been defined or not.
     */
    public function startUpAction(string $type, User $user): bool
    {
        $actionClass = setting('Auth.actions')[$type] ?? null;

        if ($actionClass === null) {
            return false;
        }

        /** @var ActionInterface $action */
        $action = Factories::actions($actionClass); // @phpstan-ignore-line

        // Create identity for the action.
        $action->createIdentity($user);

        $this->setAuthAction();

        return true;
    }

    /**
     * Checks if the user is currently logged in.
     */
    public function loggedIn(): bool
    {
        $this->checkUserState();

        return $this->userState === AuthenticationState::LOGGED_IN;
    }

    /**
     * Checks User state
     */
    private function checkUserState(): void
    {
        if ($this->userState !== AuthenticationState::UNKNOWN) {
            // Checked already.
            return;
        }

        /** @var int|string|null $userId */
        $userId = $this->getSessionKey('id');

        // Has User Info in Session.
        if ($userId !== null) {
            $this->user = $this->provider->findById($userId);

            if ($this->user === null) {
                // The user is deleted.
                $this->userState = AuthenticationState::ANONYMOUS;

                // Remove User Info in Session.
                $this->removeSessionUserInfo();

                return;
            }

            // If having `auth_action`, it is pending.
            if ($this->getSessionKey('auth_action')) {
                $this->userState = AuthenticationState::PENDING;

                return;
            }

            $this->userState = AuthenticationState::LOGGED_IN;

            return;
        }

        // No User Info in Session.
        // Check remember-me token.
        if (setting('Auth.sessionConfig')['allowRemembering']) {
            if ($this->checkRememberMe()) {
                $this->setAuthAction();
            }

            return;
        }

        $this->userState = AuthenticationState::ANONYMOUS;
    }

    /**
     * Finds an identity for actions from database, and sets the identity
     * that is found first in the session.
     *
     * @return bool true if the action is set in the session.
     */
    private function setAuthAction(): bool
    {
        if ($this->user === null) {
            return false;
        }

        $authActions = setting('Auth.actions');

        foreach ($authActions as $actionClass) {
            if ($actionClass === null) {
                continue;
            }

            /** @var ActionInterface $action */
            $action = Factories::actions($actionClass);  // @phpstan-ignore-line

            $identity = $this->userIdentityModel->getIdentityByType($this->user, $action->getType());

            if ($identity instanceof UserIdentity) {
                $this->userState = AuthenticationState::PENDING;

                $this->setSessionKey('auth_action', $actionClass);
                $this->setSessionKey('auth_action_message', $identity->extra);

                return true;
            }
        }

        return false;
    }

    /**
     * Sets the key value in Session User Info
     *
     * @param int|string|null $value
     */
    private function setSessionKey(string $key, $value): void
    {
        $sessionUserInfo       = $this->getSessionUserInfo();
        $sessionUserInfo[$key] = $value;
        session()->set(setting('Auth.sessionConfig')['field'], $sessionUserInfo);
    }

    /**
     * @return bool true if logged in by remember-me token.
     */
    private function checkRememberMe(): bool
    {
        $token = $this->rememberMe->check();

        if ($token === null) {
            $this->userState = AuthenticationState::ANONYMOUS;

            return false;
        }

        $user = $this->provider->findById($token->user_id);

        if ($user === null) {
            // The user is deleted.
            $this->userState = AuthenticationState::ANONYMOUS;

            // Remove remember-me cookie.
            $this->rememberMe->removeKey();

            return false;
        }

        $this->startLogin($user);

        $this->rememberMe->refresh($token);

        $this->userState = AuthenticationState::LOGGED_IN;

        return true;
    }

    /**
     * Starts login process
     */
    public function startLogin(User $user): void
    {
        /** @var int|string|null $userId */
        $userId = $this->getSessionKey('id');

        // Check if already logged in.
        if ($userId !== null) {
            throw new LogicException(
                'The user has User Info in Session, so already logged in or in pending login state.'
                    . ' If a logged in user logs in again with other account, the session data of the previous'
                    . ' user will be used as the new user.'
                    . ' Fix your code to prevent users from logging in without logging out or delete the session data.'
                    . ' user_id: ' . $userId,
            );
        }

        $this->user = $user;

        // Regenerate the session ID to help protect against session fixation
        if (ENVIRONMENT !== 'testing') {
            session()->regenerate(true);

            // Regenerate CSRF token even if `security.regenerate = false`.
            Services::security()->generateHash();
        }

        // Let the session know we're logged in
        $this->setSessionKey('id', $user->id);

        /** @var Response $response */
        $response = service('response');

        // When logged in, ensure cache control headers are in place
        $response->noCache();
    }

    /**
     * Removes User Info in Session
     */
    private function removeSessionUserInfo(): void
    {
        session()->remove(setting('Auth.sessionConfig')['field']);
    }

    /**
     * Gets the key value in Session User Info
     *
     * @return int|string|null
     */
    private function getSessionKey(string $key)
    {
        $sessionUserInfo = $this->getSessionUserInfo();

        return $sessionUserInfo[$key] ?? null;
    }

    /**
     * Gets User Info in Session
     */
    private function getSessionUserInfo(): array
    {
        return session(setting('Auth.sessionConfig')['field']) ?? [];
    }

    public function getLogCredentials(array $credentials): mixed
    {
        // Determine the type of ID we're using.
        // Standard fields would be email, username,
        // but any column within config('Auth')->validFields can be used.
        $field = array_intersect(service('settings')->get('Auth.validFields') ?? [], array_keys($credentials));

        if (count($field) !== 1) {
            throw new InvalidArgumentException('Invalid credentials passed to recordLoginAttempt.');
        }

        $field = array_pop($field);

        if (! in_array($field, ['email', 'username'], true)) {
            $this->authType = $field;
        } else {
            $this->authType = (! isset($credentials['email']) && isset($credentials['username']))
                ? self::ID_TYPE_USERNAME
                : self::ID_TYPE_EMAIL_PASSWORD;
        }

        return $credentials[$field];
    }
}
