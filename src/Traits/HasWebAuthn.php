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

/**
 * WebAuthn/passkey helpers mixed into the User entity.
 */
trait HasWebAuthn
{
    /**
     * @return list<WebAuthnCredential>
     */
    public function webAuthnCredentials(): array
    {
        return service('webAuthnCredentialRepository')->activeForUser($this->id);
    }

    public function hasWebAuthnCredentials(): bool
    {
        return service('webAuthnCredentialRepository')->countActiveForUser($this->id) > 0;
    }

    public function revokeWebAuthnCredential(string $uuid): bool
    {
        return service('webAuthnCredentialRepository')->revokeByUuid($this->id, $uuid);
    }
}
