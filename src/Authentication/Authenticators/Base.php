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
use Daycry\Auth\Config\Auth;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Exceptions\AuthenticationException;
use Daycry\Auth\Exceptions\InvalidArgumentException;
use Daycry\Auth\Models\LoginModel;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Result;

abstract class Base
{
    protected ?string $authType = null;
    protected ?string $method   = null;
    protected UserModel $provider;
    protected ?User $user = null;
    protected LoginModel $loginModel;
    protected ?string $ipAddress = null;
    protected ?string $userAgent = null;
    protected Request $request;
    protected UserIdentityModel $userIdentityModel;

    abstract public function check(array $credentials): Result;

    abstract public function login(User $user, bool $actions = true): void;

    abstract public function getLogCredentials(array $credentials): mixed;

    public function __construct(UserModel $provider)
    {
        $this->request = Services::request();

        $this->userIdentityModel = model(UserIdentityModel::class);

        $this->provider = $provider;

        $this->loginModel = model(LoginModel::class);

        $this->ipAddress = $this->request->getIPAddress();

        if (method_exists($this->request, 'getUserAgent')) {
            $this->userAgent = (string) $this->request->getUserAgent();
        }
    }

    /**
     * Check if the user is logged in
     *
     * @param string|null $username The user's name
     * @param string|null $password The user's password
     */
    protected function checkLogin(array $credentials): Result
    {
        if ($credentials === []) {
            $this->forceLogin();
        }

        $result = $this->check($credentials);

        if (! $result->isOK()) {
            if (service('settings')->get('Auth.recordLoginAttempt') >= Auth::RECORD_LOGIN_ATTEMPT_FAILURE) {
                // Record all failed login attempts.
                $identifier = $this->getLogCredentials($credentials);
                $this->loginModel->recordLoginAttempt(
                    $this->authType,
                    ($identifier) ?: null,
                    false,
                    $this->ipAddress,
                    $this->userAgent ?: null,
                );
            }

            $this->user = null;

            return $result;
        }

        $user = $result->extraInfo();

        if ($user->isBanned()) {
            if (service('settings')->get('Auth.recordLoginAttempt') >= Auth::RECORD_LOGIN_ATTEMPT_FAILURE) {
                // Record a banned login attempt.
                $identifier = $this->getLogCredentials($credentials);

                if ($identifier !== null) {
                    $this->loginModel->recordLoginAttempt(
                        $this->authType,
                        $identifier,
                        false,
                        $this->ipAddress,
                        $this->userAgent,
                        $user->id,
                    );
                }
            }

            $this->user = null;

            return new Result([
                'success' => false,
                'reason'  => $user->getBanMessage() ?? lang('Auth.bannedUser'),
            ]);
        }

        $this->login($user, true);

        if (service('settings')->get('Auth.recordLoginAttempt') === Auth::RECORD_LOGIN_ATTEMPT_ALL) {
            // Record a successful login attempt.
            $identifier = $this->getLogCredentials($credentials);
            if ($identifier !== null) {
                $this->loginModel->recordLoginAttempt(
                    $this->authType,
                    $identifier,
                    true,
                    $this->ipAddress,
                    $this->userAgent,
                    $this->user->id,
                );
            }
        }

        return $result;
    }

    /**
     * Force logging in by setting the WWW-Authenticate header
     *
     * @param string $nonce A server-specified data string which should be uniquely generated each time
     */
    protected function forceLogin($nonce = ''): void
    {
        $rest_auth  = \strtolower($this->method);
        $rest_realm = service('settings')->get('Auth.restRealm');

        // if (service('settings')->get('Auth.strictAccessTokenAndAuth') === true) {
        if (Services::request()->getUserAgent()->isBrowser()) {
            // @codeCoverageIgnoreStart
            if (strtolower($rest_auth) === 'basic') {
                // See http://tools.ietf.org/html/rfc2617#page-5
                header('WWW-Authenticate: Basic realm="' . $rest_realm . '"');
            } elseif (strtolower($rest_auth) === 'digest') {
                // See http://tools.ietf.org/html/rfc2617#page-18
                header(
                    'WWW-Authenticate: Digest realm="' . $rest_realm
                    . '", qop="auth", nonce="' . $nonce
                    . '", opaque="' . md5($rest_realm) . '"',
                );
            }
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

        $this->user->last_active = Time::now();

        $this->provider->updateActiveDate($this->user);
    }
}
