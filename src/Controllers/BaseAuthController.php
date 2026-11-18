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
use CodeIgniter\HTTP\ResponseInterface;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Interfaces\AuthController;
use Daycry\Auth\Models\LoginModel;
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
    protected function handleValidationError(?string $route = null): ResponseInterface
    {
        if ($this->expectsJsonResponse()) {
            return $this->authJsonResponse(['status' => 'error', 'errors' => $this->validator->getErrors()], 422);
        }
        $route ??= $this->request->getPath();

        return redirect()->to($route)
            ->withInput()
            ->with('errors', $this->validator->getErrors());
    }

    /**
     * Handle successful action with redirect
     */
    protected function handleSuccess(string $redirectUrl, ?string $message = null): ResponseInterface
    {
        if ($this->expectsJsonResponse()) {
            if (($pending = $this->pendingMfaResponse()) !== null) {
                return $pending;
            }

            return $this->authJsonResponse(array_filter([
                'status'        => 'success',
                'authenticated' => auth('session')->loggedIn(),
                'redirect'      => $redirectUrl,
                'message'       => $message,
            ], static fn ($value): bool => $value !== null));
        }
        $redirect = redirect()->to($redirectUrl);

        if ($message) {
            $redirect = $redirect->with('message', $message);
        }

        return $redirect;
    }

    /**
     * Handle error with redirect
     */
    protected function handleError(string $route, string $error, bool $withInput = true): ResponseInterface
    {
        if ($this->expectsJsonResponse()) {
            return $this->authJsonResponse(['status' => 'error', 'message' => $error], 400);
        }
        $redirect = redirect()->to($route);

        if ($withInput) {
            $redirect = $redirect->withInput();
        }

        return $redirect->with('error', $error);
    }

    /**
     * Status-aware JSON error; HTML still calls the existing override hook.
     */
    protected function handleFailure(string $route, string $error, bool $withInput = true, int $statusCode = 400): ResponseInterface
    {
        return $this->expectsJsonResponse()
            ? $this->authJsonResponse(['status' => 'error', 'message' => $error], $statusCode)
            : $this->handleError($route, $error, $withInput);
    }

    /**
     * Get current session authenticator
     */
    protected function getSessionAuthenticator(): Session
    {
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
    protected function redirectToAuthAction(): ResponseInterface
    {
        if ($this->expectsJsonResponse()) {
            return $this->authJsonResponse([
                'status'        => 'action_required',
                'authenticated' => false,
                'redirect'      => url_to('auth-action-show'),
            ], 202);
        }

        return redirect()->route('auth-action-show')->withCookies();
    }

    /**
     * Extract credentials from POST data for login
     */
    protected function extractLoginCredentials(): array
    {
        $credentials             = $this->authInput(setting('Auth.validFields')) ?? [];
        $credentials             = array_filter($credentials);
        $credentials['password'] = $this->authInput('password');

        return $credentials;
    }

    /**
     * Check if remember me is requested
     */
    protected function shouldRememberUser(): bool
    {
        return filter_var($this->authInput('remember'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Handle authentication result
     */
    protected function handleAuthResult(Result $result, string $failureRoute): ResponseInterface
    {
        if (! $result->isOK()) {
            return $this->handleFailure($failureRoute, $result->reason(), true, 401);
        }

        // Handle post-authentication action if exists
        if ($this->hasPostAuthAction()) {
            return $this->redirectToAuthAction();
        }

        // Redirect to success page
        $response = $this->handleSuccess(
            config('Auth')->loginRedirect(),
        );

        return $response instanceof RedirectResponse ? $response->withCookies() : $response;
    }

    /**
     * Do not report first-factor sessions as fully authenticated under MFA policy.
     */
    protected function pendingMfaResponse(): ?ResponseInterface
    {
        if (! $this->expectsJsonResponse() || ! auth('session')->loggedIn()) {
            return null;
        }
        $user   = auth('session')->user();
        $policy = service('mfaPolicy');
        if ($user === null || ! $policy->requires($user)) {
            return null;
        }
        $enrollment = $policy->enrollmentRequired($user);
        if (! $enrollment && (! $policy->hasFactor($user) || $policy->verified($user))) {
            return null;
        }

        return $this->authJsonResponse([
            'status'        => 'action_required',
            'authenticated' => false,
            'code'          => $enrollment ? 'mfa_enrollment_required' : 'mfa_verification_required',
            'redirect'      => url_to($enrollment ? 'security' : 'password-confirm-show'),
        ], 202);
    }

    /**
     * Whether the client explicitly requests JSON or supplied a JSON body.
     */
    protected function expectsJsonResponse(): bool
    {
        return $this->request->is('json')
            || $this->request->negotiate('media', ['text/html', 'application/json']) === 'application/json';
    }

    /**
     * @return array<string, mixed> Validated later by each endpoint's rules.
     */
    protected function authRequestData(): array
    {
        return $this->request->is('json') ? (array) $this->content : $this->request->getPost();
    }

    /**
     * Read only the request body, never query-string or cookie credentials.
     */
    protected function authInput(array|string|null $key = null): mixed
    {
        $data = $this->authRequestData();
        if ($key === null) {
            return $data;
        }
        if (is_array($key)) {
            return array_intersect_key($data, array_flip($key));
        }
        $value = $data[$key] ?? null;

        return is_scalar($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function authJsonResponse(array $data, int $statusCode = 200): ResponseInterface
    {
        return $this->response->setStatusCode($statusCode)
            ->setHeader('Cache-Control', 'no-store')
            ->setJSON($data + ['token' => $this->getTokenArray()]);
    }

    /**
     * Records a login/action attempt in the login log.
     *
     * @param string          $type       Identity type (e.g. Session::ID_TYPE_MAGIC_LINK)
     * @param string          $identifier The identifier used (email, token, etc.)
     * @param bool            $success    Whether the attempt was successful
     * @param int|string|null $userId     The user ID if known
     */
    protected function recordLoginAttempt(
        string $type,
        string $identifier,
        bool $success,
        $userId = null,
    ): void {
        /** @var LoginModel $loginModel */
        $loginModel = model(LoginModel::class);

        $loginModel->recordLoginAttempt(
            $type,
            $identifier,
            $success,
            $this->request->getIPAddress(),
            (string) $this->request->getUserAgent(),
            $userId,
        );
    }
}
