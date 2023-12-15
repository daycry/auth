<?php

declare(strict_types=1);

namespace Daycry\Auth\Authentication\Authenticators;

use CodeIgniter\I18n\Time;
use Daycry\Auth\Config\Auth;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Exceptions\AuthenticationException;
use Daycry\Auth\Interfaces\AuthenticatorInterface;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Result;
use Daycry\Exceptions\Exceptions\RuntimeException;
use Daycry\JWT\JWT as JWTLibrary;
use stdClass;

/**
 * Stateless JWT Authenticator
 */
class JWT extends Base implements AuthenticatorInterface
{
    public const ID_TYPE_JWT = 'jwt';

    protected mixed $payload;

    public function __construct(UserModel $provider)
    {
        $this->method = self::ID_TYPE_JWT;

        parent::__construct($provider);
    }

    /**
     * Attempts to authenticate a user with the given $credentials.
     * Logs the user in with a successful check.
     *
     * @param array{token?: string} $credentials
     */
    public function attempt(array $credentials = []): Result
    {
        return $this->checkLogin($credentials);
    }

    /**
     * Checks a user's $credentials to see if they match an
     * existing user.
     *
     * In this case, $credentials has only a single valid value: token,
     * which is the plain text token to return.
     *
     * @param array{token?: string} $credentials
     */
    public function check(array $credentials = []): Result
    {
        if (! array_key_exists('token', $credentials) || $credentials['token'] === '') {
            return new Result([
                'success' => false,
                'reason'  => lang(
                    'Auth.noToken',
                    [service('settings')->get('Auth.authenticatorHeader')[$this->method]]
                ),
            ]);
        }

        // Check JWT
        try {
            $jwt = service('settings')->get('Auth.jwtAdapter');
            $this->payload = (new $jwt)->decode($credentials['token']);
        } catch (RuntimeException $e) {
            return new Result([
                'success' => false,
                'reason'  => $e->getMessage(),
            ]);
        }

        $userId = $this->payload ?? null;

        if ($userId === null) {
            return new Result([
                'success' => false,
                'reason'  => 'Invalid JWT: no user_id',
            ]);
        }
        
        // Find User
        $user = $this->provider->findById($userId);

        // Find User
        $user = $this->provider->findById($userId);

        if ($user === null) {
            return new Result([
                'success' => false,
                'reason'  => lang('Auth.invalidUser'),
            ]);
        }

        return new Result([
            'success'   => true,
            'extraInfo' => $user,
        ]);
    }

    /**
     * Checks if the user is currently logged in.
     * Since AccessToken usage is inherently stateless,
     * it runs $this->attempt on each usage.
     */
    public function loggedIn(): bool
    {
        if ($this->user !== null) {
            return true;
        }

        /** @var IncomingRequest $request */
        $request = service('request');

        return $this->attempt([
            'token' => $request->getHeaderLine(service('settings')->get('Auth.authenticatorHeader')[$this->method]),
        ])->isOK();
    }

    /**
     * Logs the given user in by saving them to the class.
     */
    public function login(User $user, bool $actions = true): void
    {
        $this->user = $user;
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

        if ($user === null) {
            throw AuthenticationException::forInvalidUser();
        }

        $this->login($user);
    }

    /**
     * Logs the current user out.
     */
    public function logout(): void
    {
        $this->user = null;
    }

    /**
     * Returns payload
     */
    public function getPayload(): int
    {
        return $this->payload;
    }

    public function getLogCredentials(array $credentials): mixed
    {
        $this->authType = self::ID_TYPE_JWT;
        return $credentials['token'] ?? '';
    }
}