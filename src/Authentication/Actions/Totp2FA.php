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
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Entities\UserIdentity;
use Daycry\Auth\Enums\IdentityType;
use Daycry\Auth\Interfaces\ActionInterface;
use Daycry\Auth\Libraries\TOTP;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Traits\Viewable;

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
class Totp2FA implements ActionInterface
{
    use Viewable;

    /**
     * Identity type for the pending-login marker.
     */
    private string $type = IdentityType::TOTP->value;

    /**
     * Displays the TOTP code entry form.
     */
    public function show(): string
    {
        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        $user = $authenticator->getPendingUser();

        if ($user === null) {
            throw new RuntimeException('Cannot get the pending login User.');
        }

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
        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

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
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identityModel->deleteIdentitiesByType($user, $this->type);

        $authenticator->completeLogin($user);

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
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        // Remove any stale marker from a previous attempt
        $identityModel->deleteIdentitiesByType($user, $this->type);

        // Only activate the action for users who have confirmed TOTP
        if (! $user->hasTotpEnabled()) {
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
     * Returns the string type of this action.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Verifies the TOTP code against the user's permanent secret.
     */
    private function verifyCodeForUser(User $user, string $code): bool
    {
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        $totpSecret = $identityModel->getIdentityByType($user, IdentityType::TOTP_SECRET->value);

        if (! $totpSecret instanceof UserIdentity) {
            return false;
        }

        return TOTP::verify((string) $totpSecret->secret, $code);
    }
}
