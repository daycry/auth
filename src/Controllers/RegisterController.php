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
            return $this->handleError(
                $this->request->getUri()->getPath(),
                lang('Auth.registerDisabled'),
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
    public function registerAction(): RedirectResponse
    {
        // Check if already logged in
        if (($redirect = $this->redirectIfLoggedIn(config('Auth')->registerRedirect())) instanceof RedirectResponse) {
            return $redirect;
        }

        // Check if registration is allowed
        if (! setting('Auth.allowRegistration')) {
            return $this->handleError(
                config('Auth')->registerRoute(),
                lang('Auth.registerDisabled'),
            );
        }

        // Validate input
        $rules    = $this->getValidationRules();
        $postData = $this->request->getPost();

        if (! $this->validateRequest($postData, $rules)) {
            return $this->handleValidationError(config('Auth')->registerRoute());
        }

        // Save the user
        $users             = $this->getUserProvider();
        $allowedPostFields = array_keys($rules);
        $user              = $this->getUserEntity();
        $user->fill($this->request->getPost($allowedPostFields));

        // Workaround for email only registration/login
        if ($user->username === null) {
            $user->username = null;
        }

        try {
            $users->save($user);
        } catch (ValidationException $e) {
            return $this->handleError(
                config('Auth')->registerRoute(),
                'Registration failed',
                true,
            )->with('errors', $users->errors());
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

        return $this->handleSuccess(
            config('Auth')->registerRedirect(),
            lang('Auth.registerSuccess'),
        );
    }

    /**
     * Returns the User provider
     */
    protected function getUserProvider(): UserModel
    {
        $provider = model(setting('Auth.userProvider'));

        assert($provider instanceof UserModel, 'Config Auth.userProvider is not a valid UserProvider.');

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
