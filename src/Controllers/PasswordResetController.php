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
use CodeIgniter\Exceptions\RuntimeException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;
use Daycry\Auth\Authentication\Passwords;
use Daycry\Auth\Enums\IdentityType;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Services\AuditLogger;
use Daycry\Auth\Validation\ValidationRules;

/**
 * Handles password reset flow via email token.
 *
 * The user requests a reset link, receives an email with a token,
 * and uses that token to set a new password.
 */
class PasswordResetController extends BaseAuthController
{
    /**
     * @var UserModel
     */
    protected $provider;

    public function __construct()
    {
        /** @var class-string<UserModel> $providerClass */
        $providerClass = setting('Auth.userProvider');

        $this->provider = new $providerClass();
    }

    /**
     * Displays the form to request a password reset email.
     */
    public function requestView(): ResponseInterface
    {
        if (($redirect = $this->redirectIfLoggedIn()) instanceof RedirectResponse) {
            return $redirect;
        }

        $content = $this->view(setting('Auth.views')['password-reset-request']);

        return $this->response->setBody($content);
    }

    /**
     * Receives the email, generates a reset token, and sends an email.
     */
    public function requestAction(): RedirectResponse
    {
        // Validate email format
        $rules    = $this->getValidationRules();
        $postData = $this->request->getPost();

        if (! $this->validateRequest($postData, $rules)) {
            return $this->handleValidationError('password-reset');
        }

        $email = $this->request->getPost('email');
        $user  = $this->provider->findByCredentials(['email' => $email]);

        if ($user !== null) {
            $sender = service('tokenEmailSender');

            try {
                $sender->sendTokenEmail(
                    $user,
                    IdentityType::RESET_PASSWORD->value,
                    setting('AuthSecurity.passwordResetLifetime'),
                    lang('Auth.passwordResetSubject'),
                    setting('Auth.views')['password-reset-email'],
                    ['user' => $user],
                );
            } catch (RuntimeException $e) {
                log_message('error', $e->getMessage());
            }

            // Record the attempt
            $this->recordLoginAttempt(IdentityType::RESET_PASSWORD->value, $email, true, $user->id);
        } else {
            // Record failed attempt but don't reveal that user doesn't exist
            $this->recordLoginAttempt(IdentityType::RESET_PASSWORD->value, $email, false);
        }

        // Always redirect to message view (don't reveal if user exists)
        return redirect()->route('password-reset-message');
    }

    /**
     * Shows the "check your email" message view.
     */
    public function messageView(): ResponseInterface
    {
        $content = $this->view(setting('Auth.views')['password-reset-message']);

        return $this->response->setBody($content);
    }

    /**
     * Displays the password reset form (with token from query string).
     */
    public function resetView(): ResponseInterface
    {
        $token = $this->request->getGet('token');

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        $identity = $identityModel->getIdentityBySecret(IdentityType::RESET_PASSWORD->value, $token);

        if ($identity === null) {
            return redirect()->route('password-reset-request')
                ->with('error', lang('Auth.passwordResetTokenInvalid'));
        }

        if (Time::now()->isAfter($identity->expires)) {
            // Delete expired token
            $identityModel->delete($identity->id);

            return redirect()->route('password-reset-request')
                ->with('error', lang('Auth.passwordResetTokenExpired'));
        }

        $content = $this->view(setting('Auth.views')['password-reset-form'], [
            'token' => $token,
        ]);

        return $this->response->setBody($content);
    }

    /**
     * Handles the password reset form submission.
     */
    public function resetAction(): RedirectResponse
    {
        $rules = $this->getResetValidationRules();

        $postData = $this->request->getPost();
        $token    = (string) $this->request->getPost('token');

        if (! $this->validateRequest($postData, $rules)) {
            return redirect()->to($this->resetFormUrl($token))
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        $identity = $identityModel->getIdentityBySecret(IdentityType::RESET_PASSWORD->value, $token);

        if ($identity === null) {
            return redirect()->route('password-reset-request')
                ->with('error', lang('Auth.passwordResetTokenInvalid'));
        }

        if (Time::now()->isAfter($identity->expires)) {
            $identityModel->delete($identity->id);

            return redirect()->route('password-reset-request')
                ->with('error', lang('Auth.passwordResetTokenExpired'));
        }

        // Find the user
        $user = $this->provider->findById($identity->user_id);

        if ($user === null) {
            return redirect()->route('password-reset-request')
                ->with('error', lang('Auth.passwordResetTokenInvalid'));
        }

        $password = (string) $this->request->getPost('password');

        // Enforce the same password policy as registration (composition,
        // personal-info, dictionary, pwned and history validators). Checked
        // before the token is consumed so the user can retry with it.
        /** @var Passwords $passwords */
        $passwords = service('passwords');
        $check     = $passwords->check($password, $user);

        if (! $check->isOK()) {
            return redirect()->to($this->resetFormUrl($token))
                ->with('error', $check->reason());
        }

        // Capture the previous hash *before* we replace it — needed for
        // password-history bookkeeping.
        $previousHash = $user->password_hash ?? null;

        // Update the password
        $user->setPassword($password);

        /** @var UserModel $userModel */
        $userModel = model(UserModel::class);
        $userModel->save($user);

        service('passwordChangeRecorder')->record($user, $previousHash);

        // Delete the reset token identity
        $identityModel->delete($identity->id);

        service('auditLogger')->record(AuditLogger::EVENT_PASSWORD_RESET, (int) $user->id);

        Events::trigger('passwordReset', $user);

        return redirect()->route('login')
            ->with('message', lang('Auth.passwordResetSuccess'));
    }

    /**
     * Returns the rules used to validate the reset form submission.
     *
     * The full password policy is applied afterwards through
     * {@see Passwords::check()}, once the user is known.
     *
     * @return array<string, array<string, list<string>|string>|string>
     */
    protected function getResetValidationRules(): array
    {
        $rules = new ValidationRules();

        return [
            'token'            => 'required',
            'password'         => $rules->getPasswordRules(),
            'password_confirm' => $rules->getPasswordConfirmRules(),
        ];
    }

    /**
     * Returns the URL of the reset form for the given token.
     */
    protected function resetFormUrl(string $token): string
    {
        return site_url('password-reset/verify?token=' . rawurlencode($token));
    }

    /**
     * Returns the rules that should be used for validation.
     *
     * @return         array<string, array<string, list<string>|string>>
     * @phpstan-return array<string, array<string, string|list<string>>>
     */
    protected function getValidationRules(): array
    {
        return [
            'email' => config('Auth')->emailValidationRules,
        ];
    }
}
