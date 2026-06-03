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

/**
 * Optional WebAuthn passkey second factor. Mirrors Totp2FA:
 *   - User HAS ≥1 active passkey → assertion challenge shown before login completes.
 *   - User has none → silently skipped.
 *
 * Activate with `'login' => Webauthn2FA::class` in Auth::$actions. Mutually
 * exclusive with Totp2FA (only one login action is supported).
 */
class Webauthn2FA extends AbstractAction
{
    protected string $type = IdentityType::WEBAUTHN->value;

    public function show(): string
    {
        $this->requirePendingUser();

        return $this->view(setting('Auth.views')['webauthn_2fa_verify']);
    }

    /**
     * @return RedirectResponse|string
     */
    public function handle(IncomingRequest $request)
    {
        return redirect()->route('auth-action-show');
    }

    /**
     * @return RedirectResponse|string
     */
    public function verify(IncomingRequest $request)
    {
        $authenticator = $this->getSessionAuthenticator();
        $user          = $authenticator->getPendingUser();

        if ($user === null) {
            throw new RuntimeException('Cannot get the pending login User.');
        }

        $lockoutManager = $authenticator->getLockoutManager();
        $lockoutResult  = $lockoutManager->isLockedOut($user);

        if ($lockoutResult !== null) {
            session()->setFlashdata('error', $lockoutResult->reason());

            return $this->view(setting('Auth.views')['webauthn_2fa_verify']);
        }

        $credential = (string) $request->getPost('credential');

        if ($credential === '' || ! service('webAuthnManager')->finishTwoFactor($user, $credential)) {
            $lockoutManager->recordFailedAttempt($user);
            session()->setFlashdata('error', lang('Auth.webauthnVerificationFailed'));

            return $this->view(setting('Auth.views')['webauthn_2fa_verify']);
        }

        $lockoutManager->resetOnSuccess($user);
        $this->getIdentityModel()->deleteIdentitiesByType($user, $this->type);
        $authenticator->completeLogin($user);

        return redirect()->to(config('Auth')->loginRedirect());
    }

    public function createIdentity(User $user): string
    {
        $identityModel = $this->getIdentityModel();
        $identityModel->deleteIdentitiesByType($user, $this->type);

        if (! $user->hasWebAuthnCredentials()) {
            return '';
        }

        $identityModel->insert([
            'user_id' => $user->id,
            'type'    => $this->type,
            'name'    => 'webauthn_pending',
            'secret'  => 'webauthn',
            'extra'   => lang('Auth.needWebauthn'),
        ]);

        return 'webauthn';
    }
}
