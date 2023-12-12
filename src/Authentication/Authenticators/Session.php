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

namespace Daycry\Auth\Authentication\Authenticators;

class Session
{
    /**
     * @var string Special ID Type.
     *             `username` is stored in `users` table, so no `auth_identities` record.
     */
    public const ID_TYPE_USERNAME = 'username';

    // Identity types
    public const ID_TYPE_EMAIL_PASSWORD = 'email_password';
    public const ID_TYPE_MAGIC_LINK     = 'magic-link';
    public const ID_TYPE_EMAIL_2FA      = 'email_2fa';
    public const ID_TYPE_EMAIL_ACTIVATE = 'email_activate';

    // User states
    private const STATE_UNKNOWN   = 0; // Not checked yet.
    private const STATE_ANONYMOUS = 1;
    private const STATE_PENDING   = 2; // 2FA or Activation required.
    private const STATE_LOGGED_IN = 3;
}
