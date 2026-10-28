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

use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\LogicException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Exceptions\ValidationException;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Validation\ValidationRules;

/**
 * Class RegisterController
 *
 * Handles displaying registration form,
 * and handling actual registration flow.
 */
class RegisterController extends BaseAuthController
{
    /**
     * Displays the registration form.
     */
    public function registerView(): ResponseInterface
    {
        // Check if already logged in
        if (($redirect = $this->redirectIfLoggedIn(config('Auth')->registerRedirect())) instanceof RedirectResponse) {
            return $redirect;
        }

        // Check if registration is allowed
        if (! setting('Auth.allowRegistration')) {
            // Redirect to login: redirecting back to the register page would loop.
            return $this->handleFailure(
                config('Auth')->loginRoute(),
                lang('Auth.registerDisabled'),
                true,
                403,
            );
        }

        // Check if there's a pending post-auth action
        if ($this->hasPostAuthAction()) {
            return $this->redirectToAuthAction();
        }

        $content = $this->view(setting('Auth.views')['register']);

        return $this->response->setBody($content);
    }

    /**
     * Attempts to register the user.
     */
    public function registerAction(): ResponseInterface
    {
        // Check if already logged in
        if (($redirect = $this->redirectIfLoggedIn(config('Auth')->registerRedirect())) instanceof RedirectResponse) {
            return $redirect;
        }

        // Check if registration is allowed
        if (! setting('Auth.allowRegistration')) {
            return $this->handleFailure(
                config('Auth')->loginRoute(),
                lang('Auth.registerDisabled'),
                true,
                403,
            );
        }

        // Validate input
        $rules    = $this->getValidationRules();
        $postData = $this->authInput();

        // Fire pre-register event before validation
        Events::trigger('pre-register', $postData);

        if (! $this->validateRequest($postData, $rules)) {
            return $this->handleValidationError(config('Auth')->registerRoute());
        }

        // Save the user
        $users             = $this->getUserProvider();
        $allowedPostFields = array_keys($rules);
        $user              = $this->getUserEntity();
        $user->fill($this->authInput($allowedPostFields));

        // Workaround for email only registration/login
        $user->username ??= null;

        try {
            $users->save($user);
        } catch (ValidationException) {
            if ($this->expectsJsonResponse()) {
                return $this->authJsonResponse(['status' => 'error', 'errors' => $users->errors()], 422);
            }

            return redirect()->to(config('Auth')->registerRoute())->withInput()->with('errors', $users->errors());
        }

        // Get complete user object with ID
        $user = $users->findById($users->getInsertID());

        // Add to default group
        $users->addToDefaultGroup($user);

        Events::trigger('register', $user);

        // Start authentication process
        $authenticator = $this->getSessionAuthenticator();
        $authenticator->startLogin($user);

        // Check for post-registration action
        $hasAction = $authenticator->startUpAction('register', $user);
        if ($hasAction) {
            return $this->redirectToAuthAction();
        }

        // Set the user active and complete login
        $user->activate();
        $authenticator->completeLogin($user);

        if ($this->expectsJsonResponse()) {
            if (($pending = $this->pendingMfaResponse()) !== null) {
                return $pending;
            }

            return $this->authJsonResponse(['status' => 'success', 'authenticated' => true, 'redirect' => config('Auth')->registerRedirect(), 'message' => lang('Auth.registerSuccess')], 201);
        }

        return $this->handleSuccess(config('Auth')->registerRedirect(), lang('Auth.registerSuccess'));
    }

    /**
     * Returns the User provider
     */
    protected function getUserProvider(): UserModel
    {
        $provider = model(setting('Auth.userProvider'));

        // An explicit check, not assert(): assertions are disabled in production
        // (zend.assertions=-1), where a wrong provider would fatal later instead.
        if (! $provider instanceof UserModel) {
            throw new LogicException('Config Auth.userProvider must be ' . UserModel::class . ' or a subclass.');
        }

        return $provider;
    }

    /**
     * Returns the Entity class that should be used
     */
    protected function getUserEntity(): User
    {
        return new User();
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

        return $rules->getRegistrationRules();
    }
}
