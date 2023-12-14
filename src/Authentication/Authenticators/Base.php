<?php

declare(strict_types=1);

namespace Daycry\Auth\Authentication\Authenticators;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\Request;
use Config\Services;
use Daycry\Auth\Config\Auth;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Exceptions\AuthenticationException;
use Daycry\Auth\Interfaces\LibraryAuthenticatorInterface;
use Daycry\Auth\Models\LoginModel;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Result;

abstract class Base
{
    protected ?string $authType = null;

    protected ?string $method = null;

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
        /** @var IncomingRequest $this->request */
        $this->request = Services::request();

        $this->userIdentityModel = model(UserIdentityModel::class);

        $this->provider = $provider;

        $this->loginModel = model(LoginModel::class);

        $this->ipAddress = $this->request->getIPAddress();
        $this->userAgent = (string) $this->request->getUserAgent();
    }

    /**
     * Check if the user is logged in
     *
     * @access protected
     * @param string|null $username The user's name
     * @param string|null $password The user's password
     */
    protected function checkLogin(array $credentials): Result
    {
        if (empty($credentials)) {
            $this->forceLogin();
        }

        $authMethod = \strtolower($this->method);

        $authSource = null;
        if(isset(service('settings')->get('Auth.authSource')[$authMethod])) {
            $authSource = service('settings')->get('Auth.authSource')[$authMethod];
        }

        if ($authSource === 'library') {
            log_message('debug', "Performing Library authentication for" . json_encode($credentials));

            return $this->_performLibraryAuth($credentials);
        }

        $result = $this->check($credentials);

        if (! $result->isOK()) {
            if (service('settings')->get('Auth.recordLoginAttempt') >= Auth::RECORD_LOGIN_ATTEMPT_FAILURE) {
                // Record all failed login attempts.
                $identifier = $this->getLogCredentials($credentials);
                $this->loginModel->recordLoginAttempt(
                    $this->authType,
                    $identifier,
                    false,
                    $this->ipAddress,
                    $this->userAgent
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
                $this->loginModel->recordLoginAttempt(
                    $this->authType,
                    $identifier,
                    false,
                    $this->ipAddress,
                    $this->userAgent,
                    $user->id
                );
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
            $this->loginModel->recordLoginAttempt(
                $this->authType,
                $identifier,
                true,
                $this->ipAddress,
                $this->userAgent,
                $this->user->id
            );
        }

        return $result;
    }

    protected function _performLibraryAuth(array $credentials)
    {
        $authLibraryClass = service('settings')->get('Auth.libraryCustomAuthenticators');

        if (!isset($authLibraryClass[ $this->method ]) || !\class_exists($authLibraryClass[ $this->method ])) {
            throw AuthenticationException::forUnknownAuthenticator($this->method);
        }

        $authLibraryClass = new $authLibraryClass[ $this->method ]($this->provider);

        if ((!$authLibraryClass instanceof LibraryAuthenticatorInterface)) {
            throw AuthenticationException::forInvalidLibraryImplementation();
        }

        if (\is_callable([ $authLibraryClass, 'check' ])) {
            /** @var User $user */
            return $authLibraryClass->{'check'}($credentials);
        }
    }

    /**
     * Force logging in by setting the WWW-Authenticate header
     *
     * @access protected
     * @param string $nonce A server-specified data string which should be uniquely generated each time
     * @return void
     */
    protected function forceLogin($nonce = '')
    {
        $rest_auth = \strtolower($this->method);
        $rest_realm = service('settings')->get('RestFul.restRealm');

        //if (service('settings')->get('RestFul.strictAccessTokenAndAuth') === true) {
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
                    . '", opaque="' . md5($rest_realm) . '"'
                );
            }
            // @codeCoverageIgnoreEnd
        }

        throw AuthenticationException::forInvalidUser();
    }
}