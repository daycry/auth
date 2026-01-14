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

enum AuthenticationState: int
{
    case UNKNOWN   = 0;
    case ANONYMOUS = 1;
    case PENDING   = 2;
    case LOGGED_IN = 3;
}
