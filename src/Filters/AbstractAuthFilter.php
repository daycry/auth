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
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\ResponseInterface;
use Daycry\Auth\Authentication\Authenticators\Session;

/**
 * Group Authorization Filter.
 */
abstract class AbstractAuthFilter implements FilterInterface
{
    /**
     * Ensures the user is logged in and a member of one or
     * more groups as specified in the filter.
     *
     * @param array|null $arguments
     *
     * @return RedirectResponse|Response|void
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if (empty($arguments)) {
            return;
        }

        if (! auth()->loggedIn()) {
            return $this->handleUnauthenticated($request);
        }

        if ($this->isAuthorized($arguments)) {
            return;
        }

        return $this->redirectToDeniedUrl($request);
    }

    /**
     * Handle unauthenticated requests
     */
    private function handleUnauthenticated(RequestInterface $request)
    {
        if (auth()->getAuthenticator() instanceof Session) {
            // Set the entrance url to redirect a user after successful login
            if (uri_string() !== route_to('login')) {
                session()->setTempdata('beforeLoginUrl', current_url(), 300);
            }

            return redirect()->route('login');
        }

        return service('response')->setStatusCode(
            401,
            lang('Auth.invalidUser'), // message
        );
    }

    /**
     * Check if request expects JSON response
     */
    protected function expectsJson(RequestInterface $request): bool
    {
        $acceptHeader = $request->getHeaderLine('Accept');

        return str_contains($acceptHeader, 'application/json')
               || str_contains($acceptHeader, 'application/xml');
    }

    /**
     * We don't have anything to do here.
     *
     * @param array|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
        // Nothing required
    }

    /**
     * Ensures the user is logged in and has one or more
     * of the permissions as specified in the filter.
     */
    abstract protected function isAuthorized(array $arguments): bool;

    /**
     * Returns redirect response when the user does not have access authorizations.
     */
    abstract protected function redirectToDeniedUrl(RequestInterface $request): RedirectResponse|Response;
}
