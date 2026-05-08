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

namespace Daycry\Auth\Authentication\Actions;

use CodeIgniter\Exceptions\RuntimeException;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Enums\IdentityType;
use Daycry\Auth\Libraries\TOTP;
use Daycry\Auth\Models\DeviceSessionModel;
use Daycry\Auth\Services\AuditLogger;
use Throwable;

/**
 * Class Totp2FA
 *
 * Optional TOTP (RFC 6238 / Google Authenticator) second-factor authentication.
 *
 * Behaviour when set as `'login' => Totp2FA::class` in Auth::$actions:
 *   - User HAS TOTP configured → code entry form is shown before completing login.
 *   - User has NO TOTP configured → action is silently skipped, login proceeds normally.
 *
 * Users enable/disable TOTP from their account security settings via
 * UserSecurityController (totpSetup / totpDisable).
 */
class Totp2FA extends AbstractAction
{
    /**
     * Cookie name carrying the trusted-device proof. Format: `<deviceUuid>.<hmac>`.
     */
    public const TRUSTED_DEVICE_COOKIE = 'auth_trusted_device';

    /**
     * Identity type for the pending-login marker.
     */
    protected string $type = IdentityType::TOTP->value;

    /**
     * Displays the TOTP code entry form.
     */
    public function show(): string
    {
        $this->requirePendingUser();

        return $this->view(setting('Auth.views')['action_totp_2fa_verify']);
    }

    /**
     * Not used for TOTP — verification happens entirely in verify().
     *
     * @return RedirectResponse|string
     */
    public function handle(IncomingRequest $request)
    {
        return redirect()->route('auth-action-show');
    }

    /**
     * Verifies the TOTP code submitted by the user.
     *
     * @return RedirectResponse|string
     */
    public function verify(IncomingRequest $request)
    {
        $authenticator = $this->getSessionAuthenticator();

        $user = $authenticator->getPendingUser();

        if ($user === null) {
            throw new RuntimeException('Cannot get the pending login User.');
        }

        $code = (string) $request->getPost('token');

        if (! $this->verifyCodeForUser($user, $code)) {
            session()->setFlashdata('error', lang('Auth.invalidTotpToken'));

            return $this->view(setting('Auth.views')['action_totp_2fa_verify']);
        }

        // Remove the pending marker and complete login
        $this->getIdentityModel()->deleteIdentitiesByType($user, $this->type);

        $authenticator->completeLogin($user);

        // Trust-this-device: only honoured *after* completeLogin() so a
        // fresh device session UUID is available.
        if ((bool) $request->getPost('trust_device')) {
            $this->markCurrentDeviceTrusted($user);
        }

        return redirect()->to(config('Auth')->loginRedirect());
    }

    /**
     * Creates the pending marker so Session knows TOTP verification is required.
     *
     * If the user has no confirmed TOTP secret the method returns early without
     * inserting a marker. Session::setAuthAction() will find nothing and
     * hasAction() will return false, so the login completes normally.
     *
     * @return string 'totp' when action is active, '' when skipped.
     */
    public function createIdentity(User $user): string
    {
        $identityModel = $this->getIdentityModel();

        // Remove any stale marker from a previous attempt
        $identityModel->deleteIdentitiesByType($user, $this->type);

        // Only activate the action for users who have confirmed TOTP
        if (! $user->hasTotpEnabled()) {
            return '';
        }

        // Trust-this-device fast path: if the user is logging in from a
        // device they previously marked as trusted (and the trust window
        // has not expired), skip the 2FA challenge.
        if ($this->isTrustedDeviceForUser($user)) {
            return '';
        }

        $identityModel->insert([
            'user_id' => $user->id,
            'type'    => $this->type,
            'name'    => 'totp_pending',
            'secret'  => 'totp',
            'extra'   => lang('Auth.needTotp'),
        ]);

        return 'totp';
    }

    /**
     * Verifies the TOTP code against the user's permanent secret.
     *
     * If the TOTP code does not match, falls back to backup codes — this
     * lets users authenticate when their authenticator app is unavailable
     * (lost phone, replaced device). A consumed backup code is marked
     * as used and cannot be reused.
     */
    private function verifyCodeForUser(User $user, string $code): bool
    {
        $secret = $user->getTotpSecret();

        if ($secret === null) {
            return false;
        }

        $window = (int) (setting('AuthSecurity.totpWindow') ?? 1);

        if (TOTP::verify($secret, $code, $window)) {
            return true;
        }

        // Backup-code fallback: hex strings are visually distinct from the
        // 6-digit TOTP, so accidental collisions are extremely unlikely.
        return $user->consumeBackupCode($code);
    }

    /**
     * Trusted-device check: returns true when the request carries a valid
     * trusted-device cookie matching an active, non-expired DeviceSession
     * row belonging to $user.
     */
    private function isTrustedDeviceForUser(User $user): bool
    {
        $lifetime = (int) (setting('AuthSecurity.trustedDeviceLifetime') ?? 0);

        if ($lifetime <= 0) {
            return false; // feature disabled
        }

        $cookieValue = $this->readTrustedCookie();

        if ($cookieValue === null) {
            return false;
        }

        try {
            /** @var DeviceSessionModel $deviceModel */
            $deviceModel = model(DeviceSessionModel::class);
            $session     = $deviceModel->findTrustedByUuid($cookieValue);

            return $session !== null && (int) $session->user_id === (int) $user->id;
        } catch (Throwable $e) {
            log_message('warning', 'Trusted-device check failed: {message}', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Marks the user's current device session as trusted for the configured
     * lifetime, sets the signed cookie carrying the device UUID, and writes
     * an audit-log entry.
     */
    private function markCurrentDeviceTrusted(User $user): void
    {
        $lifetime = (int) (setting('AuthSecurity.trustedDeviceLifetime') ?? 0);

        if ($lifetime <= 0) {
            return;
        }

        $sessionId = session_id();

        if ($sessionId === '' || $sessionId === false) {
            return;
        }

        try {
            /** @var DeviceSessionModel $deviceModel */
            $deviceModel = model(DeviceSessionModel::class);
            $session     = $deviceModel->findBySessionId($sessionId);

            if ($session === null || empty($session->uuid)) {
                return;
            }

            $deviceModel->markTrusted((string) $session->uuid, $lifetime);

            $this->writeTrustedCookie((string) $session->uuid, $lifetime);

            (new AuditLogger())->record(AuditLogger::EVENT_TRUSTED_DEVICE_ADDED, (int) $user->id, [
                'device_uuid'   => (string) $session->uuid,
                'lifetime_secs' => $lifetime,
            ]);
        } catch (Throwable $e) {
            log_message('warning', 'Trusted-device mark failed: {message}', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Reads and validates the trusted-device cookie. Returns the decrypted
     * device UUID on success, null otherwise.
     */
    private function readTrustedCookie(): ?string
    {
        try {
            /** @var IncomingRequest $request */
            $request = service('request');
            $value   = $request->getCookie(self::TRUSTED_DEVICE_COOKIE);

            if (! is_string($value) || $value === '') {
                return null;
            }

            $payload = (string) service('encrypter')->decrypt(base64_decode($value, true) ?: '');

            return $payload === '' ? null : $payload;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Encrypts the device UUID with CI4's encrypter and sets the cookie.
     */
    private function writeTrustedCookie(string $deviceUuid, int $lifetimeSeconds): void
    {
        try {
            $encrypted = base64_encode((string) service('encrypter')->encrypt($deviceUuid));

            service('response')->setCookie([
                'name'     => self::TRUSTED_DEVICE_COOKIE,
                'value'    => $encrypted,
                'expire'   => $lifetimeSeconds,
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => (bool) (setting('App.cookieSecure') ?? false),
            ]);
        } catch (Throwable $e) {
            log_message('warning', 'Trusted-device cookie write failed: {message}', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
