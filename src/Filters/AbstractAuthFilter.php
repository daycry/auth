<?php

declare(strict_types=1);

namespace Daycry\Auth\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
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
     * @return RedirectResponse|void
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if (empty($arguments)) {
            return;
        }

        if (! auth()->loggedIn()) {
            // Set the entrance url to redirect a user after successful login
            if (uri_string() !== route_to('login')) {
                $session = session();
                $session->setTempdata('beforeLoginUrl', current_url(), 300);
            }

            if(auth()->getAuthenticator() instanceof Session)
            {
                return redirect()->route('login');
            }else{
                return service('response')->setStatusCode(
                    404,
                    lang('Auth.invalidUser') // message
                );
            }
            
        }

        if ($this->isAuthorized($arguments)) {
            return;
        }


        return $this->redirectToDeniedUrl();
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
    abstract protected function redirectToDeniedUrl(): RedirectResponse;
}
