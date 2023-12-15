<?php

declare(strict_types=1);

namespace Daycry\Auth\Exceptions;

use Exception;

class InvalidJWTException extends ValidationException
{
    public const INVALID_TOKEN      = 1;
    public const EXPIRED_TOKEN      = 2;
    public const BEFORE_VALID_TOKEN = 3;

    public static function forInvalidToken(Exception $e): self
    {
        return new self(lang('Auth.invalidJWT'), self::INVALID_TOKEN, $e);
    }
}