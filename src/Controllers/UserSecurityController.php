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
use Daycry\Auth\Libraries\TOTP;
use Daycry\Auth\Models\DeviceSessionModel;

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

        return $this->view('Daycry\Auth\Views\profile\security', [
            'sessions'    => $sessions,
            'currentSid'  => $currentSid,
            'totpEnabled' => $user->hasTotpEnabled(),
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
     */
    public function totpSetup(): RedirectResponse|string
    {
        $user = auth()->user();

        if ($user->hasTotpEnabled()) {
            return redirect()->route('security')->with('error', 'TOTP is already enabled.');
        }

        $otpAuthUrl = $user->enableTotp();
        $secret     = $user->getTotpSecret();

        return $this->view('Daycry\Auth\Views\totp_setup_show', [
            'otpAuthUrl' => $otpAuthUrl,
            'secret'     => $secret ?? '',
            'qrCodeUrl'  => TOTP::getQRCodeUrl($otpAuthUrl),
            'confirmUrl' => url_to('totp-setup-confirm'),
        ]);
    }

    /**
     * Confirm TOTP setup by verifying the first code.
     */
    public function totpSetupConfirm(): RedirectResponse|string
    {
        $user = auth()->user();
        $code = (string) $this->request->getPost('token');

        if (! $user->verifyTotpCode($code)) {
            // Remove the just-generated secret so setup can restart cleanly
            $user->disableTotp();

            return redirect()->route('totp-setup')
                ->with('error', lang('Auth.totpSetupInvalidCode'));
        }

        return $this->view('Daycry\Auth\Views\totp_setup_success', [
            'redirectUrl' => url_to('security'),
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
}
