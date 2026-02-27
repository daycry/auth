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

namespace Daycry\Auth\Traits;

use Daycry\Auth\Entities\UserIdentity;
use Daycry\Auth\Enums\IdentityType;
use Daycry\Auth\Libraries\TOTP;
use Daycry\Auth\Models\UserIdentityModel;

/**
 * Provides TOTP (Google Authenticator-compatible) 2FA management methods.
 * Intended to be used with User entities.
 */
trait HasTotp
{
    /**
     * Returns the permanent TOTP secret identity for this user, if any.
     */
    public function getTotpIdentity(): ?UserIdentity
    {
        /** @var UserIdentityModel $model */
        $model = model(UserIdentityModel::class);

        return $model->getIdentityByType($this, IdentityType::TOTP_SECRET->value);
    }

    /**
     * Returns true if the user has TOTP 2FA configured.
     */
    public function hasTotpEnabled(): bool
    {
        return $this->getTotpIdentity() instanceof UserIdentity;
    }

    /**
     * Returns the raw TOTP secret for the user, or null if TOTP is not configured.
     */
    public function getTotpSecret(): ?string
    {
        $identity = $this->getTotpIdentity();

        return $identity instanceof UserIdentity ? $identity->secret : null;
    }

    /**
     * Generates a new TOTP secret, saves it for this user (or replaces the existing one),
     * and returns the otpauth:// URL for QR code generation.
     *
     * @param string|null $issuer App name shown in the authenticator app.
     *                            Defaults to `Auth.totpIssuer` from the config file.
     */
    public function enableTotp(?string $issuer = null): string
    {
        $issuer ??= (string) service('settings')->get('Auth.totpIssuer');

        /** @var UserIdentityModel $model */
        $model = model(UserIdentityModel::class);

        // Remove any existing TOTP secret
        $model->deleteIdentitiesByType($this, IdentityType::TOTP_SECRET->value);

        $secret = TOTP::generateSecret();

        $model->insert([
            'user_id' => $this->id,
            'type'    => IdentityType::TOTP_SECRET->value,
            'name'    => 'totp',
            'secret'  => $secret,
        ]);

        $account = $this->email ?? $this->username ?? (string) $this->id;

        return TOTP::getOtpAuthUrl($secret, $account, $issuer);
    }

    /**
     * Removes the stored TOTP secret, effectively disabling TOTP 2FA for this user.
     */
    public function disableTotp(): void
    {
        /** @var UserIdentityModel $model */
        $model = model(UserIdentityModel::class);

        $model->deleteIdentitiesByType($this, IdentityType::TOTP_SECRET->value);
    }

    /**
     * Verifies the given TOTP code against this user's stored secret.
     *
     * @param string $code   6-digit TOTP code
     * @param int    $window Number of adjacent time steps to accept (default: 1)
     */
    public function verifyTotpCode(string $code, int $window = 1): bool
    {
        $secret = $this->getTotpSecret();

        if ($secret === null) {
            return false;
        }

        return TOTP::verify($secret, $code, $window);
    }
}
