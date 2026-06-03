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

namespace Daycry\Auth\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Interfaces\JWTAdapterInterface;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Validation\ValidationRules;

/**
 * JwtController
 *
 * Stateless JWT authentication with refresh token rotation.
 * Access tokens are short-lived JWTs; refresh tokens are
 * long-lived opaque tokens stored in the identities table.
 *
 * Routes (add to your app):
 *   $routes->post('auth/jwt/login',   'Daycry\Auth\Controllers\JwtController::login',   ['as' => 'jwt-login']);
 *   $routes->post('auth/jwt/refresh', 'Daycry\Auth\Controllers\JwtController::refresh', ['as' => 'jwt-refresh']);
 *   $routes->post('auth/jwt/logout',  'Daycry\Auth\Controllers\JwtController::logout',  ['as' => 'jwt-logout']);
 */
class JwtController extends BaseAuthController
{
    /**
     * {@inheritDoc}
     */
    protected function getValidationRules(): array
    {
        $rules = new ValidationRules();

        return $rules->getLoginRules();
    }

    /**
     * Authenticate with email+password.
     *
     * Returns a short-lived JWT access token and a long-lived refresh token.
     *
     * POST body: email, password
     * Response JSON: access_token, refresh_token, token_type
     */
    public function login(): ResponseInterface
    {
        $rules    = $this->getValidationRules();
        $postData = $this->request->getPost();

        if (! $this->validateRequest($postData, $rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'message' => $this->validator->getErrors(),
            ]);
        }

        $credentials = $this->extractLoginCredentials();

        $authenticator = $this->getSessionAuthenticator();
        $result        = $authenticator->check($credentials);

        if (! $result->isOK()) {
            return $this->response->setStatusCode(401)->setJSON([
                'message' => $result->reason(),
            ]);
        }

        $user = $result->extraInfo();

        return $this->response->setJSON($this->buildTokenResponse($user));
    }

    /**
     * Exchange a valid refresh token for a new access token and rotated refresh token.
     *
     * POST body: user_id, refresh_token
     * Response JSON: access_token, refresh_token, token_type
     */
    public function refresh(): ResponseInterface
    {
        $userId       = (int) $this->request->getPost('user_id');
        $refreshToken = (string) $this->request->getPost('refresh_token');

        if ($userId === 0 || $refreshToken === '') {
            return $this->response->setStatusCode(401)->setJSON([
                'message' => lang('Auth.invalidRefreshToken'),
            ]);
        }

        $repository = service('jwtTokenRepository');
        $identity   = $repository->getRefreshToken($userId, $refreshToken);

        if ($identity === null) {
            return $this->response->setStatusCode(401)->setJSON([
                'message' => lang('Auth.invalidRefreshToken'),
            ]);
        }

        // Revoke the used refresh token (rotation — one-time use)
        $repository->softRevokeRefreshToken((int) $identity->id, $userId, 'rotation');

        /** @var UserModel $userModel */
        $userModel = model(UserModel::class);
        $user      = $userModel->findById($userId);

        if ($user === null) {
            return $this->response->setStatusCode(401)->setJSON([
                'message' => lang('Auth.invalidUser'),
            ]);
        }

        return $this->response->setJSON($this->buildTokenResponse($user));
    }

    /**
     * Revoke the refresh token (stateless logout).
     *
     * POST body: user_id, refresh_token
     * Response JSON: message
     */
    public function logout(): ResponseInterface
    {
        $userId       = (int) $this->request->getPost('user_id');
        $refreshToken = (string) $this->request->getPost('refresh_token');

        if ($userId > 0 && $refreshToken !== '') {
            $repository = service('jwtTokenRepository');
            $identity   = $repository->getRefreshToken($userId, $refreshToken);

            if ($identity !== null) {
                $repository->softRevokeRefreshToken((int) $identity->id, $userId, 'logout');
            }
        }

        return $this->response->setJSON(['message' => lang('Auth.successLogout')]);
    }

    /**
     * Builds the token pair response for a given user.
     *
     * Generates a JWT access token (via the configured adapter) and a new
     * opaque refresh token stored in the identities table.
     *
     * @param User $user
     *
     * @return array<string, mixed>
     */
    private function buildTokenResponse(object $user): array
    {
        // Generate access token via the configured JWT adapter. The payload
        // embeds the user's current token_version so the token can be revoked
        // wholesale (ban / password change) without a per-token denylist.
        $adapterClass = setting('Auth.jwtAdapter');
        /** @var JWTAdapterInterface $adapter */
        $adapter     = new $adapterClass();
        $accessToken = $adapter->encode([
            'uid' => $user->id,
            'tv'  => (int) ($user->token_version ?? 0),
        ]);

        // Generate and persist a new refresh token
        $rawRefresh = bin2hex(random_bytes(32));
        $expiresAt  = Time::now()
            ->addSeconds((int) setting('AuthSecurity.jwtRefreshLifetime'))
            ->format('Y-m-d H:i:s');

        service('jwtTokenRepository')->createRefreshToken((int) $user->id, $rawRefresh, $expiresAt);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $rawRefresh,
            'user_id'       => $user->id,
            'token_type'    => 'Bearer',
        ];
    }
}
