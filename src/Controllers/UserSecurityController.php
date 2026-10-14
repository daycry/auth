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
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Authentication\Passwords;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Libraries\TOTP;
use Daycry\Auth\Models\DeviceSessionModel;
use Daycry\Auth\Models\LoginModel;
use Daycry\Auth\Models\RememberModel;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Services\AuditLogger;
use Daycry\Auth\Services\PasswordChangeRecorder;

/**
 * UserSecurityController
 *
 * Lets authenticated users manage their own security settings:
 *   - Active device sessions (list + revoke)
 *   - TOTP 2FA setup / disable
 *
 * Routes to add in your app (example):
 *   $routes->group('account/security', ['filter' => 'session'], static function ($routes): void {
 *       $routes->get('/',                  'Daycry\Auth\Controllers\UserSecurityController::index',             ['as' => 'security']);
 *       $routes->post('sessions/revoke',   'Daycry\Auth\Controllers\UserSecurityController::revokeSession',     ['as' => 'security-revoke-session']);
 *       $routes->post('sessions/revoke-all','Daycry\Auth\Controllers\UserSecurityController::revokeAllSessions',['as' => 'security-revoke-all']);
 *       $routes->get('totp/setup',         'Daycry\Auth\Controllers\UserSecurityController::totpSetup',        ['as' => 'totp-setup']);
 *       $routes->post('totp/setup/confirm','Daycry\Auth\Controllers\UserSecurityController::totpSetupConfirm', ['as' => 'totp-setup-confirm']);
 *       $routes->post('totp/disable',      'Daycry\Auth\Controllers\UserSecurityController::totpDisable',      ['as' => 'totp-disable']);
 *       $routes->post('sessions/password', 'Daycry\Auth\Controllers\UserSecurityController::changePassword',   ['as' => 'security-change-password']);
 *       $routes->get('email/change',       'Daycry\Auth\Controllers\UserSecurityController::changeEmailView',  ['as' => 'security-change-email']);
 *       $routes->post('email/change',      'Daycry\Auth\Controllers\UserSecurityController::changeEmail',      ['as' => 'security-change-email-action']);
 *       $routes->get('email/confirm',      'Daycry\Auth\Controllers\UserSecurityController::confirmEmailChange',['as' => 'security-confirm-email']);
 *       $routes->post('oauth/unlink',      'Daycry\Auth\Controllers\UserSecurityController::unlinkOauth',      ['as' => 'security-unlink-oauth']);
 *   });
 */
class UserSecurityController extends BaseAuthController
{
    protected function getValidationRules(): array
    {
        return [];
    }

    /**
     * Security overview: device sessions + TOTP status.
     */
    public function index(): string
    {
        $user = auth()->user();

        /** @var DeviceSessionModel $deviceModel */
        $deviceModel = model(DeviceSessionModel::class);

        $sessions   = $deviceModel->getActiveForUser($user);
        $currentSid = session_id();

        return $this->view(setting('Auth.views')['security_overview'], [
            'sessions'            => $sessions,
            'currentSid'          => $currentSid,
            'totpEnabled'         => $user->hasTotpEnabled(),
            'webAuthnCredentials' => $user->webAuthnCredentials(),
            'webauthnEnabled'     => (bool) (setting('AuthSecurity.webauthnEnabled') ?? false),
        ]);
    }

    /**
     * Revoke a single device session.
     */
    public function revokeSession(): RedirectResponse
    {
        $sessionId = (string) $this->request->getPost('session_id');

        if ($sessionId === '') {
            return redirect()->back()->with('error', 'Invalid session.');
        }

        $user = auth()->user();

        /** @var DeviceSessionModel $deviceModel */
        $deviceModel = model(DeviceSessionModel::class);

        // Safety check: ensure the session belongs to the current user
        $session = $deviceModel->findBySessionId($sessionId);

        if ($session === null || $session->user_id !== $user->id) {
            return redirect()->back()->with('error', 'Session not found.');
        }

        $deviceModel->terminateSession($sessionId);

        return redirect()->back()->with('message', 'Session revoked successfully.');
    }

    /**
     * Revoke all other active sessions (keep current one).
     */
    public function revokeAllSessions(): RedirectResponse
    {
        $user = auth()->user();

        /** @var DeviceSessionModel $deviceModel */
        $deviceModel = model(DeviceSessionModel::class);
        $deviceModel->terminateAllForUser($user, session_id());

        return redirect()->back()->with('message', 'All other sessions have been revoked.');
    }

    /**
     * Show the TOTP setup page with QR code.
     *
     * Always generates a fresh secret. If the user navigates away before
     * confirming, a new QR code will be issued on their next visit.
     */
    public function totpSetup(): RedirectResponse|string
    {
        $user = auth()->user();

        if ($user->hasTotpEnabled()) {
            return redirect()->route('security')->with('error', 'TOTP is already enabled.');
        }

        $otpAuthUrl = $user->enableTotp();
        $secret     = $user->getTotpSecret();

        return $this->view(setting('Auth.views')['action_totp_setup_show'], [
            'otpAuthUrl' => $otpAuthUrl,
            'secret'     => $secret ?? '',
            'qrCodeUrl'  => TOTP::getQRCodeUrl($otpAuthUrl),
            'confirmUrl' => url_to('totp-setup-confirm'),
        ]);
    }

    /**
     * Confirm TOTP setup by verifying the first code.
     *
     * On failure the pending secret is kept so the user can retry with the same
     * QR code already in their authenticator app.
     */
    public function totpSetupConfirm(): RedirectResponse|string
    {
        $user = auth()->user();
        $code = (string) $this->request->getPost('token');

        if (! $user->verifyTotpCode($code)) {
            return redirect()->route('totp-setup')
                ->with('error', lang('Auth.totpSetupInvalidCode'));
        }

        // Mark the secret as confirmed — from this point hasTotpEnabled() returns true.
        $user->confirmTotp();

        // Generate backup codes — shown to the user once on this page.
        $backupCodes = $user->generateBackupCodes();

        return $this->view(setting('Auth.views')['action_totp_setup_success'], [
            'redirectUrl' => url_to('security'),
            'backupCodes' => $backupCodes,
        ]);
    }

    /**
     * Disable TOTP for the current user.
     */
    public function totpDisable(): RedirectResponse
    {
        $code = (string) $this->request->getPost('token');
        $user = auth()->user();

        if (! $user->verifyTotpCode($code)) {
            return redirect()->back()->with('error', lang('Auth.invalidTotpToken'));
        }

        $user->disableTotp();

        return redirect()->route('security')->with('message', 'Two-factor authentication has been disabled.');
    }

    /**
     * User-facing login activity feed: lists the user's recent login attempts
     * (success + failure) so they can spot suspicious activity targeting
     * their account.
     *
     * Add the route, e.g.:
     *     $routes->get('account/security/activity',
     *         'Daycry\Auth\Controllers\UserSecurityController::loginActivity',
     *         ['as' => 'security-activity']);
     */
    public function loginActivity(): string
    {
        $user = auth()->user();

        /** @var LoginModel $loginModel */
        $loginModel = model(LoginModel::class);

        $limit = max(1, (int) ($this->request->getGet('limit') ?? 25));
        $limit = min($limit, 100);

        $entries = $loginModel->recentForUser($user, $limit);

        $views    = setting('Auth.views');
        $viewName = $views['security_login_activity'] ?? 'Daycry\Auth\Views\security\login_activity';

        return $this->view($viewName, [
            'entries' => $entries,
            'limit'   => $limit,
        ]);
    }

    /**
     * Renders the password confirmation form.
     *
     * Wire as:
     *     $routes->get('auth/confirm-password',
     *         'Daycry\Auth\Controllers\UserSecurityController::confirmPasswordView',
     *         ['filter' => 'session', 'as' => 'password-confirm-show']);
     */
    public function confirmPasswordView(): ResponseInterface
    {
        $views    = setting('Auth.views');
        $viewName = $views['confirm_password'] ?? 'Daycry\Auth\Views\confirm_password';

        $content = $this->view($viewName);

        return $this->response->setBody($content);
    }

    /**
     * Verifies the submitted password against the logged-in user, stamps
     * `password_confirmed_at` in the session, then redirects to the URL
     * the user was originally trying to reach (or to a fallback).
     *
     * Wire as:
     *     $routes->post('auth/confirm-password',
     *         'Daycry\Auth\Controllers\UserSecurityController::confirmPasswordAction',
     *         ['filter' => 'session', 'as' => 'password-confirm']);
     */
    public function confirmPasswordAction(): RedirectResponse
    {
        $user     = auth()->user();
        $password = (string) $this->request->getPost('password');

        if ($user === null || $password === '') {
            return redirect()->route('password-confirm-show')
                ->with('error', lang('Auth.invalidPassword'));
        }

        /** @var Passwords $passwords */
        $passwords = service('passwords');

        if (! $passwords->verify($password, $user->getPasswordHash())) {
            return redirect()->route('password-confirm-show')
                ->with('error', lang('Auth.invalidPassword'));
        }

        session()->set('password_confirmed_at', time());

        (new AuditLogger())->record(AuditLogger::EVENT_PASSWORD_CONFIRMED, (int) $user->id);

        $intended = (string) (session()->getTempdata('passwordConfirmIntendedUrl') ?? '');
        session()->removeTempdata('passwordConfirmIntendedUrl');

        if ($intended === '') {
            $intended = config('Auth')->loginRedirect();
        }

        return redirect()->to($intended);
    }

    /**
     * Change-password form, or the set-password form for an account that has
     * no usable password yet (e.g. created through OAuth).
     */
    public function changePasswordView(): string
    {
        return $this->view($this->viewFor('security-change-password'), [
            'hasPassword' => $this->hasUsablePassword(auth()->user()),
        ]);
    }

    /**
     * Changes the password (requires the current one), or sets a first
     * password on a password-less account (requires an e-mail address and a
     * recent interactive sign-in instead).
     *
     * Every other device session and remember-me token is revoked, issued
     * JWTs are invalidated; the current session stays signed in.
     */
    public function changePassword(): RedirectResponse
    {
        $user        = auth()->user();
        $hasPassword = $this->hasUsablePassword($user);

        /** @var Passwords $passwords */
        $passwords = service('passwords');

        if ($hasPassword) {
            if (! $passwords->verify((string) $this->request->getPost('current_password'), (string) $user->getPasswordHash())) {
                return redirect()->back()->with('error', lang('Auth.changePasswordCurrentInvalid'));
            }
        } elseif ((string) $user->email === '') {
            return redirect()->back()->with('error', lang('Auth.changePasswordNeedsEmail'));
        } elseif (! $this->recentlyAuthenticated()) {
            return redirect()->back()->with('error', lang('Auth.reauthRequired'));
        }

        $newPassword = (string) $this->request->getPost('new_password');

        if ($newPassword !== (string) $this->request->getPost('new_password_confirm')) {
            return redirect()->back()->with('error', lang('Auth.changePasswordMismatch'));
        }

        $check = $passwords->check($newPassword, $user);

        if (! $check->isOK()) {
            return redirect()->back()->with('error', $check->reason());
        }

        $previousHash = $user->getPasswordHash();

        $user->setPassword($newPassword);
        model(UserModel::class)->save($user);

        // History, password_changed_at, JWT access + refresh revocation.
        (new PasswordChangeRecorder())->record($user, $previousHash);

        // Sign out everywhere else; keep this session.
        model(DeviceSessionModel::class)->terminateAllForUser($user, session_id());
        model(RememberModel::class)->purgeRememberTokensByUserId($user->id);

        (new AuditLogger())->record(AuditLogger::EVENT_PASSWORD_CHANGED, (int) $user->id);
        service('securityNotifier')->passwordChanged($user);
        Events::trigger('password-changed', $user);

        return redirect()->route('security')
            ->with('message', lang($hasPassword ? 'Auth.changePasswordSuccess' : 'Auth.setPasswordSuccess'));
    }

    /**
     * Whether the user can sign in with a password: an e-mail identity whose
     * hash is not the empty password (OAuth accounts that later confirm an
     * e-mail get an identity without a usable password).
     */
    protected function hasUsablePassword(User $user): bool
    {
        $hash = (string) $user->getPasswordHash();

        if ($hash === '') {
            return false;
        }

        /** @var Passwords $passwords */
        $passwords = service('passwords');

        return ! $passwords->verify('', $hash);
    }

    /**
     * Whether the user proved who they are recently: an interactive sign-in or
     * a password confirmation within AuthSecurity::$passwordConfirmationLifetime.
     * Gates sensitive changes on password-less accounts, where there is no
     * current password to ask for.
     */
    protected function recentlyAuthenticated(): bool
    {
        $lifetime = (int) (setting('AuthSecurity.passwordConfirmationLifetime') ?? 0);
        $latest   = max(
            (int) session(Session::AUTHENTICATED_AT_KEY),
            (int) session('password_confirmed_at'),
        );

        return $latest > 0 && time() - $latest <= $lifetime;
    }

    /**
     * View name for a self-service screen, with a fallback for published
     * Config\Auth::$views arrays that predate the key.
     */
    protected function viewFor(string $key): string
    {
        $defaults = [
            'security-change-password'        => '\Daycry\Auth\Views\security\change_password',
            'security-change-email'           => '\Daycry\Auth\Views\security\change_email',
            'security-confirm-email-change'   => '\Daycry\Auth\Views\security\confirm_email_change',
            'security-delete-account'         => '\Daycry\Auth\Views\security\delete_account',
            'security-confirm-delete-account' => '\Daycry\Auth\Views\security\confirm_delete_account',
            'security-account-delete-email'   => '\Daycry\Auth\Views\Email\account_delete_email',
            'email-change-email'              => '\Daycry\Auth\Views\Email\email_change_email',
        ];

        return setting('Auth.views')[$key] ?? $defaults[$key];
    }
}
