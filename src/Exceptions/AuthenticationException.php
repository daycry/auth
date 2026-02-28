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
use CodeIgniter\HTTP\Exceptions\HTTPException;

class AuthenticationException extends RuntimeException
{
    /**
     * Whether the request was authorised (i.e. credentials were structurally
     * valid).  Set to false only when a specific user was rejected.
     * Stored as an instance property to avoid the race condition that a
     * static property would introduce in concurrent requests.
     */
    public bool $authorized = true;

    /**
     * HTTP 401 Unauthorized — the request lacks valid authentication credentials.
     */
    protected $code = 401;

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
        $e             = new self(lang('Auth.invalidUser'));
        $e->authorized = false;

        return $e;
    }

    public static function forBannedUser(): self
    {
        $e             = new self(lang('Auth.invalidUser'));
        $e->authorized = false;

        return $e;
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
