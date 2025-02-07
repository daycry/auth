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

use CodeIgniter\I18n\Time;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Exceptions\AuthenticationException;
use Daycry\Auth\Interfaces\AuthenticatorInterface;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Result;

class AccessToken extends Base implements AuthenticatorInterface
{
    public const ID_TYPE_ACCESS_TOKEN = 'access_token';

    public function __construct(UserModel $provider)
    {
        $this->method = self::ID_TYPE_ACCESS_TOKEN;

        parent::__construct($provider);
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
            $credentials = ['token' => $this->getAccessToken()];
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

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        $token = $identityModel->getAccessTokenByRawToken($credentials['token']);

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
                Time::now()->subSeconds(service('settings')->get('Auth.unusedAccessTokenLifetime')),
            )
        ) {
            return new Result([
                'success' => false,
                'reason'  => lang('Auth.oldToken'),
            ]);
        }

        $token->last_used_at = Time::now()->format('Y-m-d H:i:s');

        if ($token->hasChanged()) {
            $identityModel->save($token);
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
     * Logs the given user in by saving them to the class.
     */
    public function login(User $user, bool $actions = true): void
    {
        $this->user = $user;
    }

    /**
     * Logs the current user out.
     */
    public function logout(): void
    {
        $this->user = null;
    }

    /**
     * Checks if the user is currently logged in.
     * Since AccessToken usage is inherently stateless,
     * it runs $this->attempt on each usage.
     */
    public function loggedIn(): bool
    {
        if ($this->user instanceof User) {
            return true;
        }

        service('request');

        return $this->attempt([
            'token' => $this->getAccessToken(),
        ])->isOK();
    }

    /**
     * Logs a user in based on their ID.
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
            $user->getAccessToken($this->getAccessToken()),
        );

        $this->login($user);
    }

    public function getLogCredentials(array $credentials): mixed
    {
        $this->authType = self::ID_TYPE_ACCESS_TOKEN;

        return $credentials['token'] ?? '';
    }

    private function getAccessToken()
    {
        $accessTokenName = service('settings')->get('Auth.authenticatorHeader')[$this->method];

        $key = $this->request->getHeaderLine($accessTokenName);
        $key = ($key) ?: $this->request->getGetPost($accessTokenName);
        $key = ($key) ?: $this->request->getVar($accessTokenName);

        if (empty($key)) {
            return null;
        }

        return $key;
    }
}
