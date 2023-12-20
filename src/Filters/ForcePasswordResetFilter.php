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
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\ResponseInterface;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Config\Auth;

/**
 * Force Password Reset Filter.
 */
class ForcePasswordResetFilter implements FilterInterface
{
    /**
     * Checks if a logged in user should reset their
     * password, and then redirect to the appropriate
     * page.
     *
     * @param array|null $arguments
     *
     * @return RedirectResponse|void
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! $request instanceof IncomingRequest) {
            return;
        }

        if (auth()->getAuthenticator() instanceof Session) {
            /** @var Session $authenticator */
            $authenticator = auth('session')->getAuthenticator();

            /** @var Auth $config */
            $config = config(Auth::class);

            if ($authenticator->loggedIn() && $authenticator->getUser()->requiresPasswordReset()) {
                return redirect()->to($config->forcePasswordResetRedirect());
            }
        }
    }

    /**
     * We don't have anything to do here.
     *
     * @param Response|ResponseInterface $response
     * @param array|null                 $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
