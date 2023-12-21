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

use Daycry\Exceptions\Exceptions\RuntimeException;

class AuthorizationException extends RuntimeException
{
    public static $authorized = true;
    protected $code           = 401;

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
        self::$authorized = false;

        return new self(lang('Auth.notEnoughPrivilege'));
    }
}
