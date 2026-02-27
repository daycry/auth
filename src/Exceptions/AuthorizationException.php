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

namespace Daycry\Auth\Exceptions;

use CodeIgniter\Exceptions\RuntimeException;

class AuthorizationException extends RuntimeException
{
    /**
     * Whether the request was authorised.  Stored as an instance property
     * to avoid the race condition that a static property would introduce in
     * concurrent requests.
     */
    public bool $authorized = true;

    /**
     * HTTP 403 Forbidden — authenticated but not permitted.
     */
    protected $code = 403;

    public static function forUnknownGroup(string $group): self
    {
        return new self(lang('Auth.unknownGroup', [$group]));
    }

    public static function forUnknownPermission(string $permission): self
    {
        return new self(lang('Auth.unknownPermission', [$permission]));
    }

    public static function forUnauthorized(): self
    {
        $e             = new self(lang('Auth.notEnoughPrivilege'));
        $e->authorized = false;

        return $e;
    }
}
