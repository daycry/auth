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
use Config\Services;

class FailTooManyRequestsException extends RuntimeException
{
    protected $code           = 429;
    public static $authorized = true;

    public static function forApiKeyLimit(string $key)
    {
        self::$authorized = false;
        $parser           = Services::parser();

        return new self($parser->setData(['key' => $key])->renderString(lang('Auth.textRestApiKeyTimeLimit')));
    }

    public static function forInvalidAttemptsLimit()
    {
        self::$authorized = false;

        return new self(lang('Auth.throttled'));
    }

    public static function forIpAddressTimeLimit()
    {
        self::$authorized = false;

        return new self(lang('Auth.textRestIpAddressTimeLimit'));
    }
}
