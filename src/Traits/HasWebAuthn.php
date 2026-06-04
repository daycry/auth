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

use Daycry\Auth\Entities\WebAuthnCredential;
use Daycry\Auth\Models\WebAuthnCredentialModel;

/**
 * WebAuthn/passkey helpers mixed into the User entity.
 */
trait HasWebAuthn
{
    /**
     * Returns the WebAuthnCredentialModel instance (shared service from the CI4 container).
     */
    private function webAuthnModel(): WebAuthnCredentialModel
    {
        /** @var WebAuthnCredentialModel */
        return model(WebAuthnCredentialModel::class);
    }

    /**
     * @return list<WebAuthnCredential>
     */
    public function webAuthnCredentials(): array
    {
        return $this->webAuthnModel()->activeForUser($this->id);
    }

    public function hasWebAuthnCredentials(): bool
    {
        return $this->webAuthnModel()->countActiveForUser($this->id) > 0;
    }

    public function revokeWebAuthnCredential(string $uuid): bool
    {
        return service('webAuthnCredentialRepository')->revokeByUuid($this->id, $uuid);
    }
}
