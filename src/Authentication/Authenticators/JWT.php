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

use CodeIgniter\Exceptions\RuntimeException;
use CodeIgniter\HTTP\Request;
use Daycry\Auth\Interfaces\AuthenticatorInterface;
use Daycry\Auth\Interfaces\UserProviderInterface;
use Daycry\Auth\Models\LoginModel;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Result;

/**
 * Stateless JWT Authenticator
 */
class JWT extends StatelessAuthenticator implements AuthenticatorInterface
{
    /**
     * @var string Special ID Type.
     *             This Authenticator is stateless, so no `auth_identities` record.
     */
    public const ID_TYPE_JWT = 'jwt';

    protected mixed $payload;

    public function __construct(
        UserProviderInterface $provider,
        Request $request,
        UserIdentityModel $userIdentityModel,
        LoginModel $loginModel,
    ) {
        $this->method = self::ID_TYPE_JWT;

        parent::__construct($provider, $request, $userIdentityModel, $loginModel);
    }

    /**
     * Attempts to authenticate a user with the given $credentials.
     * Logs the user in with a successful check.
     *
     * @param array{token?: string} $credentials
     */
    public function attempt(array $credentials = []): Result
    {
        if (! array_key_exists('token', $credentials) || empty($credentials['token'])) {
            $credentials = ['token' => $this->getTokenFromRequest()];
        }

        if (empty($credentials['token'])) {
            return new Result([
                'success' => false,
                'reason'  => lang(
                    'Auth.noToken',
                    [service('settings')->get('Auth.authenticatorHeader')[$this->method]],
                ),
            ]);
        }

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
                    [service('settings')->get('Auth.authenticatorHeader')[$this->method]],
                ),
            ]);
        }

        // Check JWT
        try {
            $jwt           = service('settings')->get('Auth.jwtAdapter');
            $this->payload = (new $jwt())->decode($credentials['token']);
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
     * Returns the decoded JWT payload (typically the user ID as int).
     */
    public function getPayload(): mixed
    {
        return $this->payload;
    }

    public function getLogCredentials(array $credentials): mixed
    {
        $this->authType = self::ID_TYPE_JWT;

        return $credentials['token'] ?? '';
    }

    /**
     * Returns the Bearer token extracted from the Authorization header.
     * Returns an empty string when no token is present.
     */
    protected function getTokenFromRequest(): string
    {
        $tokenHeader = service('settings')->get('Auth.authenticatorHeader')[$this->method];

        return $this->parseHeader($this->request->getHeaderLine($tokenHeader));
    }

    private function parseHeader(?string $token): string
    {
        if ($token !== null && str_starts_with($token, 'Bearer')) {
            $token = trim(substr($token, 6));
        }

        return $token ?? '';
    }
}
