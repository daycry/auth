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
 * Provides TOTP (RFC 6238 / Google Authenticator) second-factor authentication.
 *
 * Usage:
 *   In Auth config set `'login' => Totp2FA::class` inside `$actions`.
 *   Users must have TOTP configured via `$user->enableTotp($issuer)` before this
 *   action is triggered.
 */
class Totp2FA implements ActionInterface
{
    use Viewable;

    /**
     * The identity type used to mark that a TOTP verification is pending.
     * The permanent TOTP secret is stored under IdentityType::TOTP_SECRET.
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

        // Remove the pending marker identity and complete login
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identityModel->deleteIdentitiesByType($user, $this->type);

        $authenticator->completeLogin($user);

        return redirect()->to(config('Auth')->loginRedirect());
    }

    /**
     * Creates a temporary pending identity so the Session authenticator
     * knows TOTP verification is required for this login attempt.
     *
     * @return string The identity secret (not used for TOTP but required by the interface)
     *
     * @throws RuntimeException if the user has not configured TOTP
     */
    public function createIdentity(User $user): string
    {
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        // Ensure the user has a permanent TOTP secret configured
        $totpSecret = $identityModel->getIdentityByType($user, IdentityType::TOTP_SECRET->value);

        if (! $totpSecret instanceof UserIdentity) {
            throw new RuntimeException(
                'User has not configured TOTP 2FA. Call $user->enableTotp($issuer) first.',
            );
        }

        // Remove any stale pending TOTP identity
        $identityModel->deleteIdentitiesByType($user, $this->type);

        // Create a marker identity (secret field is unused — verification uses the permanent secret)
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
