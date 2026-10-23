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

namespace Daycry\Auth\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Config\Auth;
use Daycry\Auth\Interfaces\AuthenticatorInterface;

/**
 * Authentication Filter.
 *
 * JSON Web Token authentication for web applications
 * Access Token authentication for web applications
 */
class AuthFilter implements FilterInterface
{
    /**
     * @param mixed|null $arguments
     *
     * @return ResponseInterface|null
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('checkEndpoint');

        if (! $request instanceof IncomingRequest) {
            return null;
        }

        $endpoint = checkEndpoint();

        $alias = $this->determineAuthenticator($arguments, $endpoint);

        $authenticator = auth($alias)->getAuthenticator();

        /** @var Auth $config */
        $config = config(Auth::class);

        if ($authenticator instanceof Session) {
            return $this->handleSessionAuthentication($authenticator, $config);
        }

        return $this->handleTokenAuthentication($authenticator);
    }

    /**
     * Determine which authenticator to use
     *
     * @param mixed $endpoint
     */
    protected function determineAuthenticator(?array $arguments, $endpoint): string
    {
        $alias = $arguments ? $arguments[0] : service('settings')->get('Auth.defaultAuthenticator');

        return ($endpoint && $endpoint->auth) ? $endpoint->auth : $alias;
    }

    /**
     * Handle session-based authentication
     */
    protected function handleSessionAuthentication(Session $authenticator, Auth $config)
    {
        if (auth()->loggedIn()) {
            if (setting('AuthSecurity.recordActiveDate')) {
                $authenticator->recordActiveDate();
            }

            $user = $authenticator->getUser();

            if ($user === null) {
                return;
            }

            // Check if user is banned
            if ($user->isBanned()) {
                $error = $user->getBanMessage() ?? lang('Auth.logOutBannedUser');
                $authenticator->logout();

                return redirect()->to($config->logoutRedirect())
                    ->with('error', $error);
            }

            // Check if user needs activation
            if (! $user->isActivated()) {
                $hasAction = $authenticator->startUpAction('register', $user);
                if ($hasAction) {
                    return redirect()->route('auth-action-show')
                        ->with('error', lang('Auth.activationBlocked'));
                }
            }

            return;
        }

        // Handle pending actions
        if ($authenticator->isPending()) {
            return redirect()->route('auth-action-show')
                ->with('error', $authenticator->getPendingMessage());
        }

        // Save current URL for redirect after login
        if (uri_string() !== route_to('login')) {
            session()->setTempdata('beforeLoginUrl', current_url(), 300);
        }

        return redirect()->route('login');
    }

    /**
     * Handle token-based authentication
     */
    protected function handleTokenAuthentication(AuthenticatorInterface $authenticator)
    {
        $result = $authenticator->attempt();

        if (! $result->isOK()) {
            return service('response')
                ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED)
                ->setJSON(['message' => $result->reason()]);
        }

        if (setting('AuthSecurity.recordActiveDate')) {
            $authenticator->recordActiveDate();
        }

        // Handle additional access token validation if enabled
        if (service('settings')->get('AuthSecurity.accessTokenEnabled')) {
            return $this->validateAccessToken();
        }
    }

    /**
     * Validate access token when enabled
     */
    protected function validateAccessToken(): ?ResponseInterface
    {
        $accessToken = service('auth', false)->setAuthenticator('access_token')->attempt();

        if (! $accessToken->isOK() && service('settings')->get('AuthSecurity.strictApiAndAuth')) {
            return service('response')
                ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED)
                ->setJSON([
                    'message' => $accessToken->reason() ?? lang('Auth.badToken'),
                ]);
        }

        return null;
    }

    /**
     * We don't have anything to do here.
     *
     * @param array|null $arguments
     *
     * @return ResponseInterface|null
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Nothing required
        return null;
    }
}
