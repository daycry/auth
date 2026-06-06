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

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\Request;
use CodeIgniter\I18n\Time;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Exceptions\AuthenticationException;
use Daycry\Auth\Interfaces\AuthenticatorInterface;
use Daycry\Auth\Interfaces\UserProviderInterface;
use Daycry\Auth\Models\AccessTokenRepository;
use Daycry\Auth\Models\LoginModel;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Result;

class AccessToken extends StatelessAuthenticator implements AuthenticatorInterface
{
    public const ID_TYPE_ACCESS_TOKEN = 'access_token';

    private ?AccessTokenRepository $accessTokenRepository = null;

    public function __construct(
        UserProviderInterface $provider,
        Request $request,
        UserIdentityModel $userIdentityModel,
        LoginModel $loginModel,
    ) {
        $this->method = self::ID_TYPE_ACCESS_TOKEN;

        parent::__construct($provider, $request, $userIdentityModel, $loginModel);
    }

    /**
     * Lazy-resolve the access token repository — keeps the constructor
     * signature stable while routing token lookups through the repository
     * pattern instead of the deprecated UserIdentityModel methods.
     */
    private function tokenRepository(): AccessTokenRepository
    {
        return $this->accessTokenRepository ??= new AccessTokenRepository(
            $this->userIdentityModel,
        );
    }

    /**
     * Attempts to authenticate a user with the given $credentials.
     * Logs the user in with a successful check.
     *
     * @throws AuthenticationException
     */
    public function attempt(array $credentials = []): Result
    {
        helper(['checkIp']);

        if (! array_key_exists('token', $credentials) || empty($credentials['token'])) {
            $credentials = ['token' => $this->getTokenFromRequest()];
        }

        return $this->checkLogin($credentials);
    }

    /**
     * Checks a user's $credentials to see if they match an
     * existing user.
     *
     * In this case, $credentials has only a single valid value: token,
     * which is the plain text token to return.
     */
    public function check(array $credentials = []): Result
    {
        if (! array_key_exists('token', $credentials) || empty($credentials['token'])) {
            return new Result([
                'success' => false,
                'reason'  => lang(
                    'Auth.noToken',
                    [service('settings')->get('Auth.authenticatorHeader')[$this->method]],
                ),
            ]);
        }

        $token = $this->tokenRepository()->getAccessTokenByRawTokenWithUser($credentials['token']);

        if ($token === null) {
            return new Result([
                'success' => false,
                'reason'  => lang('Auth.badToken'),
            ]);
        }

        assert($token->last_used_at instanceof Time || $token->last_used_at === null);

        // Hasn't been used in a long time
        if (
            $token->last_used_at
            && $token->last_used_at->isBefore(
                Time::now()->subSeconds(service('settings')->get('AuthSecurity.unusedAccessTokenLifetime')),
            )
        ) {
            return new Result([
                'success' => false,
                'reason'  => lang('Auth.oldToken'),
            ]);
        }

        // Throttle `last_used_at` writes — for high-traffic API tokens this
        // would otherwise be one UPDATE per request. Skip the write when the
        // last recorded use is more recent than the configured threshold.
        $throttle = (int) service('settings')->get('AuthSecurity.tokenLastUsedThrottle');

        if (
            $throttle <= 0
            || $token->last_used_at === null
            || $token->last_used_at->isBefore(Time::now()->subSeconds($throttle))
        ) {
            $token->last_used_at = Time::now()->format('Y-m-d H:i:s');

            if ($token->hasChanged()) {
                $this->userIdentityModel->save($token);
            }
        }

        // Ensure the token is set as the current token
        $user = $token->user();
        $user->setAccessToken($token);

        return new Result([
            'success'   => true,
            'extraInfo' => $user,
        ]);
    }

    /**
     * Logs a user in based on their ID, setting the matching access token.
     *
     * @param int|string $userId
     *
     * @throws AuthenticationException
     */
    public function loginById($userId): void
    {
        $user = $this->provider->findById($userId);

        if (! $user instanceof User) {
            throw AuthenticationException::forInvalidUser();
        }

        $user->setAccessToken(
            $user->getAccessToken($this->getTokenFromRequest()),
        );

        $this->login($user);
    }

    public function getLogCredentials(array $credentials): mixed
    {
        $this->authType = self::ID_TYPE_ACCESS_TOKEN;

        $token = $credentials['token'] ?? '';

        // Never persist the raw, replayable bearer credential in the login log.
        // Record a non-reversible SHA-256 fingerprint instead — this is the same
        // hash the access token is stored under, so logs remain correlatable
        // without being usable to authenticate.
        return $token === '' ? '' : hash('sha256', (string) $token);
    }

    /**
     * Returns the raw access token from the request headers or query string.
     * Returns an empty string when no token is present.
     */
    protected function getTokenFromRequest(): string
    {
        $accessTokenName = service('settings')->get('Auth.authenticatorHeader')[$this->method];

        $request = $this->request;
        $key     = $request->getHeaderLine($accessTokenName);

        // getGetPost() / getVar() only exist on IncomingRequest; the parent
        // typehint is the abstract Request, so we narrow before calling them.
        if ($key === '' && $request instanceof IncomingRequest) {
            $key = (string) ($request->getGetPost($accessTokenName) ?? '');

            if ($key === '') {
                $key = (string) ($request->getVar($accessTokenName) ?? '');
            }
        }

        return $key;
    }
}
