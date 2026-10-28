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
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\ResponseInterface;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Config\Auth;
use Exception;

/**
 * Chain Authentication Filter.
 *
 * Checks all authentication systems specified within
 * `Config\Auth->authenticationChain`
 */
class ChainAuthFilter implements FilterInterface
{
    /**
     * Do whatever processing this filter needs to do.
     * By default it should not return anything during
     * normal execution. However, when an abnormal state
     * is found, it should return an instance of
     * CodeIgniter\HTTP\Response. If it does, script
     * execution will end and that Response will be
     * sent back to the client, allowing for error pages,
     * redirects, etc.
     *
     * @param array|null $arguments
     *
     * @return ResponseInterface|null
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! $request instanceof IncomingRequest) {
            return null;
        }

        helper('settings');

        /** @var Auth $config */
        $config = config(Auth::class);
        $chain  = $config->authenticationChain;

        foreach ($chain as $alias) {
            try {
                if (auth($alias)->loggedIn()) {
                    // A session banned after it started (AuthFilter does the
                    // same); stateless logins already refuse banned users.
                    if (auth($alias)->user()?->isBanned()) {
                        auth($alias)->logout();

                        continue;
                    }

                    // Make sure Auth uses this Authenticator
                    auth()->setAuthenticator($alias);

                    // A matching first-factor session must satisfy MFA before
                    // this chain can grant application access. Do not fall
                    // through to another authenticator after a policy denial.
                    return auth()->getAuthenticator() instanceof Session
                        ? (new MfaRequiredFilter())->before($request)
                        : null;
                }
            } catch (Exception) {
                continue;
            }
        }

        $acceptHeader = $request->getHeaderLine('Accept');
        if (str_contains($acceptHeader, 'application/json') || str_contains($acceptHeader, 'application/xml')) {
            return service('response')->setStatusCode(
                401,
                lang('Auth.invalidUser'),
            );
        }

        return redirect()->route('login');
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
