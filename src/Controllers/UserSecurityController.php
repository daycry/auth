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

use CodeIgniter\Database\Exceptions\DatabaseException as FrameworkDatabaseException;
use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\RuntimeException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Authentication\Passwords;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Entities\UserIdentity;
use Daycry\Auth\Enums\IdentityType;
use Daycry\Auth\Exceptions\DatabaseException;
use Daycry\Auth\Libraries\TokenEmailSender;
use Daycry\Auth\Libraries\TOTP;
use Daycry\Auth\Models\DeviceSessionModel;
use Daycry\Auth\Models\LoginModel;
use Daycry\Auth\Models\RememberModel;
use Daycry\Auth\Models\UserIdentityModel;
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

        if (! $hasPassword && (string) $user->email === '') {
            return redirect()->back()->with('error', lang('Auth.changePasswordNeedsEmail'));
        }

        $denied = $this->reauthenticate($user);
        if ($denied instanceof RedirectResponse) {
            return $denied;
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
     * Form to request an e-mail address change.
     */
    public function changeEmailView(): string
    {
        $user = auth()->user();

        return $this->view($this->viewFor('security-change-email'), [
            'hasPassword'  => $this->hasUsablePassword($user),
            'currentEmail' => (string) $user->email,
        ]);
    }

    /**
     * Requests an e-mail change: re-authenticates (current password, or a
     * recent sign-in on a password-less account), e-mails a single-use
     * confirmation link to the NEW address and warns the CURRENT one.
     * Nothing changes until the link is confirmed.
     */
    public function changeEmail(): RedirectResponse
    {
        $user = auth()->user();

        $denied = $this->reauthenticate($user);
        if ($denied instanceof RedirectResponse) {
            return $denied;
        }

        $newEmail = strtolower(trim((string) $this->request->getPost('new_email')));

        if (filter_var($newEmail, FILTER_VALIDATE_EMAIL) === false) {
            return redirect()->back()->with('error', lang('Auth.changeEmailInvalid'));
        }

        if ($newEmail === strtolower((string) $user->email)) {
            return redirect()->back()->with('error', lang('Auth.changeEmailUnchanged'));
        }

        // Authenticated-only, low-severity enumeration surface (session +
        // CSRF; add the `rates` filter to the route).
        if ($this->emailBelongsToAnotherAccount($newEmail, $user)) {
            return redirect()->back()->with('error', lang('Auth.changeEmailAlreadyUsed'));
        }

        try {
            (new TokenEmailSender())->sendTokenEmail(
                $user,
                IdentityType::EMAIL_CHANGE->value,
                (int) setting('AuthSecurity.emailChangeLifetime'),
                lang('Auth.changeEmailSubject'),
                $this->viewFor('email-change-email'),
                [],
                null,
                $newEmail,
                ['new_email' => $newEmail],
            );
        } catch (RuntimeException $e) {
            log_message('error', 'E-mail change confirmation could not be sent: {message}', ['message' => $e->getMessage()]);

            return redirect()->back()->with('error', lang('Auth.unableSendEmailToUser', [$newEmail]));
        }

        service('securityNotifier')->emailChangeRequested($user, $newEmail);
        (new AuditLogger())->record(AuditLogger::EVENT_EMAIL_CHANGE_REQUEST, (int) $user->id);

        return redirect()->back()->with('message', lang('Auth.changeEmailSent'));
    }

    /**
     * Confirmation page for the e-mailed link. GET never mutates (mail
     * scanners and link prefetchers follow GET links); it renders a form that
     * POSTs the token to confirmEmailChange().
     */
    public function confirmEmailChangeView(): RedirectResponse|string
    {
        $token = (string) $this->request->getGet('token');

        [$identity, $error] = $this->pendingToken(IdentityType::EMAIL_CHANGE, $token, 'changeEmail');
        if ($identity === null) {
            return redirect()->route('security')->with('error', $error);
        }

        return $this->view($this->viewFor('security-confirm-email-change'), [
            'token'    => $token,
            'newEmail' => (string) ($this->identityExtra($identity)['new_email'] ?? ''),
        ]);
    }

    /**
     * Applies a confirmed e-mail change. The token is single-use and must
     * belong to the signed-in user; the new address is re-checked.
     */
    public function confirmEmailChange(): RedirectResponse
    {
        $user = auth()->user();

        [$identity, $error] = $this->pendingToken(IdentityType::EMAIL_CHANGE, (string) $this->request->getPost('token'), 'changeEmail');
        if ($identity === null) {
            return redirect()->route('security')->with('error', $error);
        }

        // Consume first: the link is single-use whatever happens next.
        model(UserIdentityModel::class)->delete($identity->id);

        $newEmail = (string) ($this->identityExtra($identity)['new_email'] ?? '');

        if (filter_var($newEmail, FILTER_VALIDATE_EMAIL) === false) {
            return redirect()->route('security')->with('error', lang('Auth.changeEmailTokenInvalid'));
        }

        if ($this->emailBelongsToAnotherAccount($newEmail, $user)) {
            return redirect()->route('security')->with('error', lang('Auth.changeEmailAlreadyUsed'));
        }

        $oldEmail = (string) $user->email;

        // Save the ENTITY: UserModel syncs the login identity (an array update
        // would skip saveEmailIdentity and leave the old login address).
        $user->setEmail($newEmail);

        try {
            model(UserModel::class)->save($user);
        } catch (DatabaseException|FrameworkDatabaseException) {
            // Lost a race on the unique (type, secret) index.
            return redirect()->route('security')->with('error', lang('Auth.changeEmailAlreadyUsed'));
        }

        (new AuditLogger())->record(AuditLogger::EVENT_EMAIL_CHANGED, (int) $user->id);

        if ($oldEmail !== '') {
            service('securityNotifier')->emailChanged($user, $oldEmail);
        }

        Events::trigger('email-changed', $user, $oldEmail);

        return redirect()->route('security')->with('message', lang('Auth.changeEmailSuccess'));
    }

    /**
     * Disconnects an OAuth provider from the account. Refused when it is the
     * last way to sign in: a usable password, another linked provider or a
     * passkey (stored in its own table) must remain.
     */
    public function unlinkOauth(string $provider): RedirectResponse
    {
        $user          = auth()->user();
        $type          = IdentityType::oauthProvider($provider);
        $identityModel = model(UserIdentityModel::class);
        $identity      = $identityModel->getIdentityByType($user, $type);

        if ($identity === null) {
            return redirect()->back()->with('error', lang('Auth.unlinkOauthNotFound', [$provider]));
        }

        $otherProviders = array_filter(
            $identityModel->getIdentities($user),
            static fn (UserIdentity $i): bool => str_starts_with((string) $i->type, 'oauth_') && $i->type !== $type,
        );

        if (! $this->hasUsablePassword($user) && $otherProviders === [] && ! $user->hasWebAuthnCredentials()) {
            return redirect()->back()->with('error', lang('Auth.unlinkOauthLastMethod'));
        }

        $identityModel->delete($identity->id);

        (new AuditLogger())->record(AuditLogger::EVENT_OAUTH_UNLINKED, (int) $user->id, ['provider' => $provider]);
        Events::trigger('oauth-unlinked', $user, $provider);

        return redirect()->back()->with('message', lang('Auth.unlinkOauthSuccess', [$provider]));
    }

    /**
     * Re-authentication for a sensitive change: the current password on a
     * password account, a recent interactive sign-in on a password-less one.
     *
     * @return RedirectResponse|null Null when the user is re-authenticated
     */
    protected function reauthenticate(User $user): ?RedirectResponse
    {
        if ($this->hasUsablePassword($user)) {
            /** @var Passwords $passwords */
            $passwords = service('passwords');

            if (! $passwords->verify((string) $this->request->getPost('current_password'), (string) $user->getPasswordHash())) {
                return redirect()->back()->with('error', lang('Auth.changePasswordCurrentInvalid'));
            }

            return null;
        }

        if (! $this->recentlyAuthenticated()) {
            return redirect()->back()->with('error', lang('Auth.reauthRequired'));
        }

        return null;
    }

    /**
     * Resolves a self-service confirmation token of the signed-in user.
     *
     * @param string $langPrefix 'changeEmail' | 'deleteAccount' (selects the *TokenInvalid / *TokenExpired line)
     *
     * @return array{0: UserIdentity|null, 1: string|null} [identity, error message]
     */
    protected function pendingToken(IdentityType $type, string $token, string $langPrefix): array
    {
        $identityModel = model(UserIdentityModel::class);
        $identity      = $token === '' ? null : $identityModel->getIdentityBySecret($type->value, $token);

        if ($identity === null || (int) $identity->user_id !== (int) auth()->id()) {
            return [null, lang('Auth.' . $langPrefix . 'TokenInvalid')];
        }

        if ($identity->expires !== null && Time::now()->isAfter($identity->expires)) {
            $identityModel->delete($identity->id);

            return [null, lang('Auth.' . $langPrefix . 'TokenExpired')];
        }

        return [$identity, null];
    }

    /**
     * @return array<string, mixed>
     */
    protected function identityExtra(UserIdentity $identity): array
    {
        $extra = json_decode((string) $identity->extra, true);

        return is_array($extra) ? $extra : [];
    }

    /**
     * Whether $email is the login address of an account other than $user.
     */
    protected function emailBelongsToAnotherAccount(string $email, User $user): bool
    {
        return model(UserIdentityModel::class)
            ->where('type', IdentityType::EMAIL_PASSWORD->value)
            ->where('secret', strtolower($email))
            ->where('user_id !=', $user->id)
            ->countAllResults() > 0;
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
