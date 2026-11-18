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

namespace Daycry\Auth\Interfaces;

use Daycry\Auth\Entities\User;
use Daycry\Auth\Entities\WebAuthnCredential;

/**
 * Application contract for replacing WebAuthnManager without inheritance.
 */
interface WebAuthnManagerInterface
{
    /**
     * @return array<string, mixed> creation options ready for JSON
     */
    public function startRegistration(User $user, ?string $label = null): array;

    /**
     * finishRegistration contract.
     */
    public function finishRegistration(User $user, string $browserJson): WebAuthnCredential;

    /**
     * @return array<string, mixed> request options ready for JSON
     */
    public function startLogin(?string $email): array;

    /**
     * finishLogin contract.
     */
    public function finishLogin(string $browserJson): User;

    /**
     * @return array<string, mixed> request options scoped to the pending user
     */
    public function startTwoFactor(User $pendingUser): array;

    /**
     * finishTwoFactor contract.
     */
    public function finishTwoFactor(User $pendingUser, string $browserJson): bool;

    /**
     * @return array<string, mixed> Single-use assertion scoped to the signed-in user.
     */
    public function startStepUp(User $user): array;

    /**
     * finishStepUp contract.
     */
    public function finishStepUp(User $user, string $browserJson): bool;

    /**
     * Only call for the authenticated user: credential IDs must not be public.
     * userId is the base64url form of user.id from creation options.
     *
     * @return array{rpId: string, userId: string, allAcceptedCredentialIds: list<string>}
     */
    public function credentialSignals(User $user): array;
}
