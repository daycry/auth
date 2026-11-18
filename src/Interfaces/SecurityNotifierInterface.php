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

/**
 * Application contract for replacing SecurityNotifier without inheritance.
 */
interface SecurityNotifierInterface
{
    /**
     * The account password was changed (or set for the first time).
     */
    public function passwordChanged(User $user): bool;

    /**
     * An e-mail change was requested: warns the CURRENT address at request
     * time, so a hijacked session cannot silently move the account away.
     */
    public function emailChangeRequested(User $user, string $newEmail): bool;

    /**
     * The e-mail change was confirmed: notifies the PREVIOUS address.
     */
    public function emailChanged(User $user, string $oldEmail): bool;

    /**
     * Farewell notice, sent right before the account is anonymised.
     */
    public function accountDeleted(User $user): bool;

    /**
     * Two-factor authentication was turned off.
     */
    public function totpDisabled(User $user): bool;

    /**
     * A successful sign-in looked unusual. Also requires
     * AuthSecurity::$suspiciousLoginAlerts (the detector only runs then).
     *
     * @param list<string> $flags
     */
    public function suspiciousLogin(User $user, array $flags, string $ipAddress, string $userAgent): bool;
}
