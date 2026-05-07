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
use Daycry\Auth\Enums\TotpState;
use Daycry\Auth\Libraries\TOTP;
use Daycry\Auth\Models\TotpBackupCodeModel;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Services\AuditLogger;

/**
 * Provides TOTP (Google Authenticator-compatible) 2FA management methods.
 * Intended to be used with User entities.
 */
trait HasTotp
{
    /**
     * Returns the UserIdentityModel instance (shared service from the CI4 container).
     */
    private function totpIdentityModel(): UserIdentityModel
    {
        /** @var UserIdentityModel */
        return model(UserIdentityModel::class);
    }

    /**
     * Returns the permanent TOTP secret identity for this user, if any.
     */
    public function getTotpIdentity(): ?UserIdentity
    {
        return $this->totpIdentityModel()->getIdentityByType($this, IdentityType::TOTP_SECRET->value);
    }

    /**
     * Returns true if the user has a confirmed TOTP 2FA secret.
     * A secret stored during enrollment but not yet confirmed (name='totp_pending')
     * is NOT considered enabled.
     */
    public function hasTotpEnabled(): bool
    {
        $identity = $this->getTotpIdentity();

        return $identity instanceof UserIdentity && $identity->name === TotpState::CONFIRMED->value;
    }

    /**
     * Returns true if a TOTP secret exists but has not yet been confirmed by the user.
     * This happens when the setup QR has been shown but the first code not yet verified.
     */
    public function hasTotpPending(): bool
    {
        $identity = $this->getTotpIdentity();

        return $identity instanceof UserIdentity && $identity->name === TotpState::PENDING->value;
    }

    /**
     * Returns the decrypted TOTP secret for the user, or null if TOTP is not configured.
     *
     * The value stored in the database is AES-encrypted + base64-encoded.
     * This method transparently decrypts and returns the original base32 secret.
     */
    public function getTotpSecret(): ?string
    {
        $identity = $this->getTotpIdentity();

        if (! $identity instanceof UserIdentity || $identity->secret === null) {
            return null;
        }

        $decoded = base64_decode((string) $identity->secret, true);

        if ($decoded === false) {
            return null;
        }

        return (string) service('encrypter')->decrypt($decoded);
    }

    /**
     * Generates a new TOTP secret, saves it for this user (or replaces the existing one),
     * and returns the otpauth:// URL for QR code generation.
     *
     * The secret is stored encrypted (symmetric AES) using CI4's Encryption service.
     * Use getTotpSecret() to retrieve the decrypted value.
     *
     * @param string|null $issuer App name shown in the authenticator app.
     *                            Defaults to `Auth.totpIssuer` from the config file.
     */
    public function enableTotp(?string $issuer = null): string
    {
        $issuer ??= (string) service('settings')->get('AuthSecurity.totpIssuer');

        $model = $this->totpIdentityModel();

        // Remove any existing TOTP secret
        $model->deleteIdentitiesByType($this, IdentityType::TOTP_SECRET->value);

        $secret          = TOTP::generateSecret();
        $encryptedSecret = base64_encode(service('encrypter')->encrypt($secret));

        $model->insert([
            'user_id' => $this->id,
            'type'    => IdentityType::TOTP_SECRET->value,
            'name'    => TotpState::PENDING->value,
            'secret'  => $encryptedSecret,
        ]);

        $account = $this->email ?? $this->username ?? (string) $this->id;

        return TOTP::getOtpAuthUrl($secret, $account, $issuer);
    }

    /**
     * Confirms a pending TOTP enrollment by marking the secret as verified.
     * Must be called after the user successfully verifies the first code from
     * the authenticator app. Before this call, hasTotpEnabled() returns false.
     */
    public function confirmTotp(): void
    {
        $model    = $this->totpIdentityModel();
        $identity = $this->getTotpIdentity();

        if ($identity instanceof UserIdentity && $identity->name === TotpState::PENDING->value) {
            $identity->name = TotpState::CONFIRMED->value;
            $model->save($identity);

            (new AuditLogger())->record(AuditLogger::EVENT_TOTP_ENABLED, (int) $this->id);
        }
    }

    /**
     * Removes the stored TOTP secret, effectively disabling TOTP 2FA for this user.
     */
    public function disableTotp(): void
    {
        $hadTotp = $this->getTotpIdentity() instanceof UserIdentity;

        $this->totpIdentityModel()->deleteIdentitiesByType($this, IdentityType::TOTP_SECRET->value);

        // Backup codes are useless once TOTP is gone — purge them too.
        $this->totpBackupCodeModel()->purgeForUser($this);

        if ($hadTotp) {
            (new AuditLogger())->record(AuditLogger::EVENT_TOTP_DISABLED, (int) $this->id);
        }
    }

    /**
     * Returns the {@see TotpBackupCodeModel} instance.
     */
    private function totpBackupCodeModel(): TotpBackupCodeModel
    {
        /** @var TotpBackupCodeModel */
        return model(TotpBackupCodeModel::class);
    }

    /**
     * Generates a fresh set of one-time backup codes for this user, replacing
     * any existing ones. Returns the plain-text codes — these MUST be shown
     * to the user immediately, as they cannot be retrieved later.
     *
     * @return list<string>
     */
    public function generateBackupCodes(int $count = 10): array
    {
        return $this->totpBackupCodeModel()->regenerateForUser($this, $count);
    }

    /**
     * Counts how many unused backup codes the user has left.
     */
    public function backupCodesRemaining(): int
    {
        return $this->totpBackupCodeModel()->remainingCount($this);
    }

    /**
     * Verifies and consumes a backup code. Returns true on success — the
     * code is then marked as used and cannot be reused.
     */
    public function consumeBackupCode(string $code): bool
    {
        return $this->totpBackupCodeModel()->consume($this, $code);
    }

    /**
     * Verifies the given TOTP code against this user's stored secret.
     *
     * @param string   $code   6-digit TOTP code
     * @param int|null $window Number of adjacent time steps to accept.
     *                         When null, falls back to the configured
     *                         AuthSecurity.totpWindow setting (default: 1).
     */
    public function verifyTotpCode(string $code, ?int $window = null): bool
    {
        $secret = $this->getTotpSecret();

        if ($secret === null) {
            return false;
        }

        $window ??= (int) (setting('AuthSecurity.totpWindow') ?? 1);

        return TOTP::verify($secret, $code, $window);
    }
}
