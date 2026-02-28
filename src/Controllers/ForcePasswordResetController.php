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
use Daycry\Auth\Authentication\Passwords;
use Daycry\Auth\Models\UserModel;

/**
 * Handles forced password resets for logged-in users
 * whose accounts have been flagged for a mandatory password change.
 *
 * The ForcePasswordResetFilter redirects users here when
 * $user->requiresPasswordReset() returns true.
 */
class ForcePasswordResetController extends BaseAuthController
{
    /**
     * Displays the force password reset form.
     */
    public function showView(): ResponseInterface
    {
        if (! auth()->loggedIn()) {
            return redirect()->route('login');
        }

        $content = $this->view(setting('Auth.views')['force-password-reset']);

        return $this->response->setBody($content);
    }

    /**
     * Handles the force password reset form submission.
     */
    public function resetAction(): RedirectResponse
    {
        if (! auth()->loggedIn()) {
            return redirect()->route('login');
        }

        $rules = $this->getValidationRules();

        $postData = $this->request->getPost();

        if (! $this->validateRequest($postData, $rules)) {
            return $this->handleValidationError(config('Auth')->forcePasswordResetRedirect());
        }

        $user            = auth()->user();
        $currentPassword = $this->request->getPost('current_password');
        $newPassword     = $this->request->getPost('new_password');

        // Verify the current password
        /** @var Passwords $passwords */
        $passwords = service('passwords');

        if (! $passwords->verify($currentPassword, $user->getPasswordHash())) {
            return $this->handleError(
                config('Auth')->forcePasswordResetRedirect(),
                lang('Auth.invalidCurrentPassword'),
            );
        }

        // Update the password
        $user->setPassword($newPassword);

        /** @var UserModel $userModel */
        $userModel = model(UserModel::class);
        $userModel->save($user);

        // Clear the force reset flag
        $user->undoForcePasswordReset();

        return $this->handleSuccess(
            config('Auth')->loginRedirect(),
            lang('Auth.forceResetSuccess'),
        );
    }

    /**
     * Returns the rules that should be used for validation.
     *
     * @return array<string, string>
     */
    protected function getValidationRules(): array
    {
        return [
            'current_password'     => 'required',
            'new_password'         => 'required|min_length[8]',
            'new_password_confirm' => 'required|matches[new_password]',
        ];
    }
}
