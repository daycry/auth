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

namespace Daycry\Auth\Enums;

/**
 * Represents the enrollment state stored in the `name` column of the
 * `totp_secret` identity row.
 *
 * - PENDING  → secret generated but not yet confirmed by the user
 * - CONFIRMED → first code verified; TOTP is fully active
 */
enum TotpState: string
{
    case PENDING   = 'totp_pending';
    case CONFIRMED = 'totp';
}
