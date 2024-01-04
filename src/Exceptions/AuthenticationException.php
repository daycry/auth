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

use CodeIgniter\HTTP\Exceptions\HTTPException;
use Daycry\Exceptions\Exceptions\RuntimeException;

class AuthenticationException extends RuntimeException
{
    public static $authorized = true;
    protected $code           = 403;

    /**
     * @param string $alias Authenticator alias
     */
    public static function forUnknownAuthenticator(string $alias): self
    {
        return new self(lang('Auth.unknownAuthenticator', [$alias]));
    }

    public static function forInvalidLibraryImplementation(): self
    {
        return new self(lang('Auth.invalidLibraryImplementation'));
    }

    public static function forUnknownUserProvider(): self
    {
        return new self(lang('Auth.unknownUserProvider'));
    }

    public static function forInvalidUser(): self
    {
        self::$authorized = false;

        return new self(lang('Auth.invalidUser'));
    }

    public static function forBannedUser(): self
    {
        self::$authorized = false;

        return new self(lang('Auth.invalidUser'));
    }

    public static function forNoEntityProvided(): self
    {
        return new self(lang('Auth.noUserEntity'), 500);
    }

    /**
     * Fires when no minimumPasswordLength has been set
     * in the Auth config file.
     */
    public static function forUnsetPasswordLength(): self
    {
        return new self(lang('Auth.unsetPasswordLength'), 500);
    }

    /**
     * When the cURL request (to Have I Been Pwned) in PwnedValidator
     * throws a HTTPException it is re-thrown as this one
     */
    public static function forHIBPCurlFail(HTTPException $e): self
    {
        return new self($e->getMessage(), $e->getCode(), $e);
    }
}
