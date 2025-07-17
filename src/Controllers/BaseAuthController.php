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

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Interfaces\AuthController;
use Daycry\Auth\Result;
use Daycry\Auth\Traits\BaseControllerTrait;
use Daycry\Auth\Traits\Viewable;

/**
 * Base Auth Controller that provides common functionality for all auth controllers
 *
 * Uses BaseControllerTrait for core functionality and implements
 * common patterns used across auth controllers.
 */
abstract class BaseAuthController extends BaseController implements AuthController
{
    use BaseControllerTrait;
    use Viewable;

    /**
     * Get CSRF token array for forms
     */
    protected function getTokenArray(): array
    {
        return $this->getToken();
    }

    /**
     * Check if user is already logged in and redirect if needed
     */
    protected function redirectIfLoggedIn(?string $redirectUrl = null): ?RedirectResponse
    {
        if (auth()->loggedIn()) {
            $url = $redirectUrl ?? config('Auth')->loginRedirect();

            return redirect()->to($url);
        }

        return null;
    }

    /**
     * Get validation rules for the specific controller
     * Must be implemented by child classes
     */
    abstract protected function getValidationRules(): array;

    /**
     * Validate request data with given rules
     */
    protected function validateRequest(array $data, array $rules): bool
    {
        return $this->validateData($data, $rules, [], config('Auth')->DBGroup);
    }

    /**
     * Handle validation errors with redirect
     */
    protected function handleValidationError(?string $route = null): RedirectResponse
    {
        $route ??= $this->request->getUri()->getPath();

        return redirect()->to($route)
            ->withInput()
            ->with('errors', $this->validator->getErrors());
    }

    /**
     * Handle successful action with redirect
     */
    protected function handleSuccess(string $redirectUrl, ?string $message = null): RedirectResponse
    {
        $redirect = redirect()->to($redirectUrl);

        if ($message) {
            $redirect = $redirect->with('message', $message);
        }

        return $redirect;
    }

    /**
     * Handle error with redirect
     */
    protected function handleError(string $route, string $error, bool $withInput = true): RedirectResponse
    {
        $redirect = redirect()->to($route);

        if ($withInput) {
            $redirect = $redirect->withInput();
        }

        return $redirect->with('error', $error);
    }

    /**
     * Get current session authenticator
     */
    protected function getSessionAuthenticator(): Session
    {
        /** @var Session $authenticator */
        return auth('session')->getAuthenticator();
    }

    /**
     * Check if current request has post-authentication action
     */
    protected function hasPostAuthAction(): bool
    {
        return $this->getSessionAuthenticator()->hasAction();
    }

    /**
     * Redirect to auth action if one exists
     */
    protected function redirectToAuthAction(): RedirectResponse
    {
        return redirect()->route('auth-action-show')->withCookies();
    }

    /**
     * Extract credentials from POST data for login
     */
    protected function extractLoginCredentials(): array
    {
        $credentials             = $this->request->getPost(setting('Auth.validFields')) ?? [];
        $credentials             = array_filter($credentials);
        $credentials['password'] = $this->request->getPost('password');

        return $credentials;
    }

    /**
     * Check if remember me is requested
     */
    protected function shouldRememberUser(): bool
    {
        return (bool) $this->request->getPost('remember');
    }

    /**
     * Handle authentication result
     */
    protected function handleAuthResult(Result $result, string $failureRoute): RedirectResponse
    {
        if (! $result->isOK()) {
            return $this->handleError($failureRoute, $result->reason());
        }

        // Handle post-authentication action if exists
        if ($this->hasPostAuthAction()) {
            return $this->redirectToAuthAction();
        }

        // Redirect to success page
        return $this->handleSuccess(
            config('Auth')->loginRedirect(),
        )->withCookies();
    }
}
