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

enum IdentityType: string
{
    case EMAIL_PASSWORD = 'email_password';
    case ACCESS_TOKEN   = 'access_token';
    case MAGIC_LINK     = 'magic-link';
    case EMAIL_2FA      = 'email_2fa';
    case EMAIL_ACTIVATE = 'email_activate';
    case USERNAME       = 'username';
    case JWT            = 'jwt';
}
