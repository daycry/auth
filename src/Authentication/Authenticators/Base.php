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

use CodeIgniter\HTTP\Request;
use CodeIgniter\I18n\Time;
use Config\Services;
use Daycry\Auth\Config\AuthSecurity;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Exceptions\AuthenticationException;
use Daycry\Auth\Exceptions\InvalidArgumentException;
use Daycry\Auth\Interfaces\UserProviderInterface;
use Daycry\Auth\Models\LoginModel;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Result;

abstract class Base
{
    protected ?string $authType  = null;
    protected ?string $method    = null;
    protected ?User $user        = null;
    protected ?string $ipAddress = null;
    protected ?string $userAgent = null;

    /**
     * @param array<string, mixed> $credentials
     */
    abstract public function check(array $credentials): Result;

    abstract public function login(User $user, bool $actions = true): void;

    abstract public function getLogCredentials(array $credentials): mixed;

    public function __construct(
        protected UserProviderInterface $provider,
        protected Request $request,
        protected UserIdentityModel $userIdentityModel,
        protected LoginModel $loginModel,
    ) {
        $this->ipAddress = $this->request->getIPAddress();

        if (method_exists($this->request, 'getUserAgent')) {
            $this->userAgent = (string) $this->request->getUserAgent();
        }
    }

    public static function instance(UserProviderInterface $provider): static
    {
        return new static(
            $provider,
            Services::request(),
            model(UserIdentityModel::class),
            model(LoginModel::class),
        );
    }

    /**
     * Checks the credentials and logs the user in on success.
     *
     * @param array<string, mixed> $credentials
     */
    protected function checkLogin(array $credentials): Result
    {
        if ($credentials === []) {
            $this->forceLogin();
        }

        $result = $this->check($credentials);

        if (! $result->isOK()) {
            $this->recordAttempt($credentials, false);
            $this->user = null;

            return $result;
        }

        $user = $result->extraInfo();

        if ($user->isBanned()) {
            $this->recordAttempt($credentials, false, $user->id);
            $this->user = null;

            return new Result([
                'success' => false,
                'reason'  => $user->getBanMessage() ?? lang('Auth.bannedUser'),
            ]);
        }

        $this->login($user, true);

        $this->recordAttempt($credentials, true, $this->user->id);

        return $result;
    }

    /**
     * Records a login attempt as `AuthSecurity::$recordLoginAttempt` asks:
     * failures from RECORD_LOGIN_ATTEMPT_FAILURE, successes only at
     * RECORD_LOGIN_ATTEMPT_ALL.
     *
     * Credentials without a recognised identifier field are not recorded.
     * An attempt not tied to a user (unknown account, bad token) stores empty
     * identifier and user-agent values as NULL.
     *
     * @param array<string, mixed> $credentials
     */
    private function recordAttempt(array $credentials, bool $success, ?int $userId = null): void
    {
        $threshold = $success
            ? AuthSecurity::RECORD_LOGIN_ATTEMPT_ALL
            : AuthSecurity::RECORD_LOGIN_ATTEMPT_FAILURE;

        if ((int) service('settings')->get('AuthSecurity.recordLoginAttempt') < $threshold) {
            return;
        }

        try {
            $identifier = $this->getLogCredentials($credentials);
        } catch (InvalidArgumentException) {
            return;
        }

        $anonymous = $userId === null;

        if (! $anonymous && $identifier === null) {
            return;
        }

        $this->loginModel->recordLoginAttempt(
            $this->authType,
            $anonymous ? ($identifier ?: null) : $identifier,
            $success,
            $this->ipAddress,
            $anonymous ? ($this->userAgent ?: null) : $this->userAgent,
            $userId,
        );
    }

    /**
     * Force logging in by setting the WWW-Authenticate header.
     *
     * Only the `Basic` challenge is emitted. HTTP Digest Auth is not supported
     * by design — see `docs/03-authentication.md` § "Why HTTP Digest Auth is
     * not supported" for the rationale (incompatible with bcrypt password
     * storage, deprecated by RFC 7616, no security gain over Basic + TLS).
     *
     * @param string $nonce Reserved for future use. Currently ignored — kept
     *                      in the signature so downstream subclasses that
     *                      override this method do not break.
     */
    protected function forceLogin($nonce = ''): void
    {
        if (Services::request()->getUserAgent()->isBrowser()) {
            // @codeCoverageIgnoreStart
            $rest_realm = service('settings')->get('Auth.restRealm');
            // See http://tools.ietf.org/html/rfc7617#section-2
            header('WWW-Authenticate: Basic realm="' . $rest_realm . '"');
            // @codeCoverageIgnoreEnd
        }

        throw AuthenticationException::forInvalidUser();
    }

    /**
     * Returns the currently logged in user.
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * Updates the user's last active date.
     */
    public function recordActiveDate(): void
    {
        if (! $this->user instanceof User) {
            throw new InvalidArgumentException(
                __METHOD__ . '() requires logged in user before calling.',
            );
        }

        // Throttle `last_active` writes — on the authenticated hot path this
        // would otherwise be one users-table UPDATE per request. Skip the write
        // when the recorded value is newer than the configured threshold.
        $throttle = (int) (setting('AuthSecurity.activeDateThrottle') ?? 0);
        $current  = $this->user->last_active;

        if (
            $throttle > 0
            && $current instanceof Time
            && $current->isAfter(Time::now()->subSeconds($throttle))
        ) {
            return;
        }

        $this->user->last_active = Time::now();

        $this->provider->updateActiveDate($this->user);
    }
}
