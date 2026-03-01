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

namespace Daycry\Auth\Config;

use CodeIgniter\Config\BaseConfig;
use Daycry\Auth\Authentication\Passwords\CompositionValidator;
use Daycry\Auth\Authentication\Passwords\DictionaryValidator;
use Daycry\Auth\Authentication\Passwords\NothingPersonalValidator;
use Daycry\Auth\Interfaces\PasswordValidatorInterface;

class AuthSecurity extends BaseConfig
{
    /**
     * ////////////////////////////////////////////////////////////////////
     * LOGGING
     * ////////////////////////////////////////////////////////////////////
     */

    // Constants for Record Login Attempts. Do not change.
    public const RECORD_LOGIN_ATTEMPT_NONE    = 0; // Do not record at all
    public const RECORD_LOGIN_ATTEMPT_FAILURE = 1; // Record only failures
    public const RECORD_LOGIN_ATTEMPT_ALL     = 2; // Record all login attempts

    public int $recordLoginAttempt = AuthSecurity::RECORD_LOGIN_ATTEMPT_ALL;

    /**
     * --------------------------------------------------------------------
     * Record Last Active Date
     * --------------------------------------------------------------------
     * If true, will always update the `last_active` datetime for the
     * logged-in user on every page request.
     * This feature only works when session/tokens filter is active.
     */
    public bool $recordActiveDate = true;

    /**
     * --------------------------------------------------------------------
     * AUTH Logs
     * --------------------------------------------------------------------
     * When set to TRUE, auth events are written to the database log.
     */
    public bool $enableLogs = false;

    /**
     * ////////////////////////////////////////////////////////////////////
     * PASSWORD POLICY
     * ////////////////////////////////////////////////////////////////////
     */

    /**
     * --------------------------------------------------------------------
     * Minimum Password Length
     * --------------------------------------------------------------------
     * The minimum length that a password must be to be accepted.
     * Recommended minimum value by NIST = 8 characters.
     */
    public int $minimumPasswordLength = 8;

    /**
     * --------------------------------------------------------------------
     * Password Check Helpers
     * --------------------------------------------------------------------
     * The PasswordValidator class runs the password through all of these
     * classes, each getting the opportunity to pass/fail the password.
     * You can add custom classes as long as they adhere to the
     * Daycry\Auth\Interfaces\PasswordValidatorInterface.
     *
     * @var list<class-string<PasswordValidatorInterface>>
     */
    public array $passwordValidators = [
        CompositionValidator::class,
        NothingPersonalValidator::class,
        DictionaryValidator::class,
        // PwnedValidator::class,
    ];

    /**
     * --------------------------------------------------------------------
     * Password / Username Similarity
     * --------------------------------------------------------------------
     * Among other things, the NothingPersonalValidator checks the
     * amount of sameness between the password and username.
     * Passwords that are too much like the username are invalid.
     *
     * The value set for $maxSimilarity represents the maximum percentage
     * of similarity at which the password will be accepted. In other words, any
     * calculated similarity equal to, or greater than $maxSimilarity
     * is rejected.
     *
     * The accepted range is 0-100, with 0 (zero) meaning don't check similarity.
     * The suggested value for $maxSimilarity is 50.
     *
     * To disable similarity checking set the value to 0.
     *     public $maxSimilarity = 0;
     */
    public int $maxSimilarity = 50;

    /**
     * --------------------------------------------------------------------
     * Hashing Algorithm to use
     * --------------------------------------------------------------------
     * Valid values are
     * - PASSWORD_DEFAULT (default)
     * - PASSWORD_BCRYPT
     * - PASSWORD_ARGON2I  - As of PHP 7.2 only if compiled with support for it
     * - PASSWORD_ARGON2ID - As of PHP 7.3 only if compiled with support for it
     */
    public string $hashAlgorithm = PASSWORD_DEFAULT;

    /**
     * --------------------------------------------------------------------
     * ARGON2I/ARGON2ID Algorithm options
     * --------------------------------------------------------------------
     * The ARGON2I method of hashing allows you to define the "memory_cost",
     * the "time_cost" and the number of "threads", whenever a password hash is
     * created.
     */
    public int $hashMemoryCost = 65536; // PASSWORD_ARGON2_DEFAULT_MEMORY_COST;

    public int $hashTimeCost = 4;   // PASSWORD_ARGON2_DEFAULT_TIME_COST;
    public int $hashThreads  = 1;   // PASSWORD_ARGON2_DEFAULT_THREADS;

    /**
     * --------------------------------------------------------------------
     * BCRYPT Algorithm options
     * --------------------------------------------------------------------
     * The BCRYPT method of hashing allows you to define the "cost"
     * or number of iterations made, whenever a password hash is created.
     * This defaults to a value of 12 which is an acceptable number.
     *
     * Valid range is between 4 - 31.
     */
    public int $hashCost = 12;

    /**
     * If you need to support passwords saved in versions prior to Shield v1.0.0-beta.4.
     * set this to true.
     *
     * @deprecated This is only for backward compatibility.
     */
    public bool $supportOldDangerousPassword = false;

    /**
     * ////////////////////////////////////////////////////////////////////
     * ACCOUNT LOCKOUT
     * ////////////////////////////////////////////////////////////////////
     */

    /**
     * --------------------------------------------------------------------
     * Per-user Login Lockout
     * --------------------------------------------------------------------
     * Locks an individual user account after N consecutive failed login
     * attempts, regardless of the source IP address.
     *
     * $userMaxAttempts  Maximum consecutive failures before the account
     *                   is locked. 0 = disabled.
     * $userLockoutTime  Seconds the account stays locked after lockout.
     */
    public int $userMaxAttempts = 5;

    public int $userLockoutTime = 3600;

    /**
     * --------------------------------------------------------------------
     * IP-Based Invalid Attempt Blocking
     * --------------------------------------------------------------------
     * Blocks IPs that exceed the maximum number of failed login attempts.
     */
    public bool $enableInvalidAttempts = false;

    public int $maxAttempts = 10;
    public int $timeBlocked = 3600;

    /**
     * ////////////////////////////////////////////////////////////////////
     * RATE LIMITING
     * ////////////////////////////////////////////////////////////////////
     */

    /**
     * --------------------------------------------------------------------
     * Rate Limiting Control
     * --------------------------------------------------------------------
     * Available methods:
     * 'IP_ADDRESS'   — limit per IP address
     * 'USER'         — limit per user
     * 'METHOD_NAME'  — limit on method calls
     * 'ROUTED_URL'   — limit on the routed URL
     */
    public string $limitMethod = 'METHOD_NAME';

    public int $requestLimit = 10;
    public int $timeLimit    = MINUTE;

    /**
     * ////////////////////////////////////////////////////////////////////
     * TOKEN & LINK LIFETIMES
     * ////////////////////////////////////////////////////////////////////
     */

    /**
     * --------------------------------------------------------------------
     * Access Token
     * --------------------------------------------------------------------
     */
    public bool $accessTokenEnabled = false;

    public int $unusedAccessTokenLifetime = YEAR;
    public bool $strictApiAndAuth         = false;

    /**
     * --------------------------------------------------------------------
     * Allow Magic Link Logins
     * --------------------------------------------------------------------
     * If true, will allow the use of "magic links" sent via the email
     * as a way to log a user in without the need for a password.
     */
    public bool $allowMagicLinkLogins = true;

    /**
     * --------------------------------------------------------------------
     * Magic Link Lifetime
     * --------------------------------------------------------------------
     * Specifies the amount of time, in seconds, that a magic link is valid.
     */
    public int $magicLinkLifetime = HOUR;

    /**
     * --------------------------------------------------------------------
     * Password Reset Lifetime
     * --------------------------------------------------------------------
     * Specifies the amount of time, in seconds, that a password reset
     * token is valid.
     */
    public int $passwordResetLifetime = HOUR;

    /**
     * --------------------------------------------------------------------
     * JWT Refresh Token Lifetime
     * --------------------------------------------------------------------
     * Specifies how long (in seconds) a JWT refresh token is valid.
     */
    public int $jwtRefreshLifetime = 30 * DAY;

    /**
     * ////////////////////////////////////////////////////////////////////
     * TOTP & PERMISSION CACHE
     * ////////////////////////////////////////////////////////////////////
     */

    /**
     * --------------------------------------------------------------------
     * TOTP 2FA
     * --------------------------------------------------------------------
     * Name shown in the authenticator app next to the account.
     * Usually your application or company name.
     */
    public string $totpIssuer = 'My App';

    /**
     * --------------------------------------------------------------------
     * Permission & Group Cache
     * --------------------------------------------------------------------
     * When enabled, user groups and permissions are stored in the CI4
     * cache service to avoid repeated DB queries on every request.
     *
     * - permissionCacheEnabled  Enable/disable caching (default: false)
     * - permissionCacheTTL      Seconds before the cache expires (default: 300)
     */
    public bool $permissionCacheEnabled = false;

    public int $permissionCacheTTL = 300;
}
