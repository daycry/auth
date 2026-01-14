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

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Daycry\Auth\Validation\ValidationRules;

class LoginController extends BaseAuthController
{
    /**
     * Displays the form the login to the site.
     */
    public function loginView(): ResponseInterface
    {
        // Check if already logged in
        if (($redirect = $this->redirectIfLoggedIn()) instanceof RedirectResponse) {
            return $redirect;
        }

        // Check if there's a pending post-auth action
        if ($this->hasPostAuthAction()) {
            return $this->redirectToAuthAction();
        }

        $content = $this->view(setting('Auth.views')['login']);

        return $this->response->setBody($content);
    }

    /**
     * Attempts to log the user in.
     */
    public function loginAction(): RedirectResponse
    {
        // Validate input
        $rules    = $this->getValidationRules();
        $postData = $this->request->getPost();

        if (! $this->validateRequest($postData, $rules)) {
            return $this->handleValidationError(config('Auth')->loginRoute());
        }

        // Extract credentials and remember preference
        $credentials = $this->extractLoginCredentials();
        $remember    = $this->shouldRememberUser();

        // Attempt authentication
        $authenticator = $this->getSessionAuthenticator();
        $result        = $authenticator->remember($remember)->attempt($credentials);

        return $this->handleAuthResult($result, config('Auth')->loginRoute());
    }

    /**
     * Returns the rules that should be used for validation.
     *
     * @return         array<string, array<string, list<string>|string>>
     * @phpstan-return array<string, array<string, string|list<string>>>
     */
    protected function getValidationRules(): array
    {
        $rules = new ValidationRules();

        return $rules->getLoginRules();
    }

    /**
     * Logs the current user out.
     */
    public function logoutAction(): RedirectResponse
    {
        // Capture logout redirect URL before auth logout
        $url = config('Auth')->logoutRedirect();

        auth()->logout();

        return $this->handleSuccess($url, lang('Auth.successLogout'));
    }
}
