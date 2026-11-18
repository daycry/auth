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

use CodeIgniter\HTTP\ResponseInterface;
use Daycry\Auth\Authentication\Passwords;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Services\AuditLogger;
use Daycry\Auth\Validation\ValidationRules;

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
            return $this->expectsJsonResponse()
                ? $this->authJsonResponse(['status' => 'error', 'message' => lang('Auth.notEnoughPrivilege')], 401)
                : redirect()->route('login');
        }

        $content = $this->view(setting('Auth.views')['force-password-reset']);

        return $this->response->setBody($content);
    }

    /**
     * Handles the force password reset form submission.
     */
    public function resetAction(): ResponseInterface
    {
        if (! auth()->loggedIn()) {
            return $this->expectsJsonResponse()
                ? $this->authJsonResponse(['status' => 'error', 'message' => lang('Auth.notEnoughPrivilege')], 401)
                : redirect()->route('login');
        }

        $rules = $this->getValidationRules();

        $postData = $this->authInput();

        // Errors go back to the form itself. The `force_reset` redirect is
        // where the filter sends flagged users and defaults to '/'.
        $formUrl = $this->request->getPath();

        if (! $this->validateRequest($postData, $rules)) {
            return $this->handleValidationError($formUrl);
        }

        $user            = auth()->user();
        $currentPassword = $this->authInput('current_password');
        $newPassword     = $this->authInput('new_password');

        // Verify the current password
        /** @var Passwords $passwords */
        $passwords = service('passwords');

        if (! $passwords->verify($currentPassword, $user->getPasswordHash())) {
            return $this->handleFailure($formUrl, lang('Auth.invalidCurrentPassword'), true, 401);
        }

        // Enforce the same password policy as registration.
        $check = $passwords->check((string) $newPassword, $user);

        if (! $check->isOK()) {
            return $this->handleFailure($formUrl, (string) $check->reason(), true, 422);
        }

        // Capture the previous hash for password-history bookkeeping.
        $previousHash = $user->password_hash ?? null;

        // Update the password
        $user->setPassword($newPassword);

        /** @var UserModel $userModel */
        $userModel = model(UserModel::class);
        $userModel->save($user);

        service('passwordChangeRecorder')->record($user, $previousHash);

        // Clear the force reset flag
        $user->undoForcePasswordReset();

        service('auditLogger')->record(AuditLogger::EVENT_PASSWORD_CHANGED, (int) $user->id, [
            'forced' => true,
        ]);

        return $this->handleSuccess(
            config('Auth')->loginRedirect(),
            lang('Auth.forceResetSuccess'),
        );
    }

    /**
     * Returns the rules that should be used for validation.
     *
     * @return array<string, array<string, list<string>|string>|string>
     */
    protected function getValidationRules(): array
    {
        $passwordRules = (new ValidationRules())->getPasswordRules();

        return [
            'current_password'     => 'required',
            'new_password'         => $passwordRules,
            'new_password_confirm' => 'required|matches[new_password]',
        ];
    }
}
