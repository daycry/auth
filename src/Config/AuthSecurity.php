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
     * Access Token last_used_at Throttle
     * --------------------------------------------------------------------
     * Minimum number of seconds between two consecutive `last_used_at`
     * writes for the same access token. Avoids one DB write per request
     * for high-traffic API tokens.
     *
     * 0 disables throttling (writes on every request).
     */
    public int $tokenLastUsedThrottle = 60;

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
     * TOTP Verification Window
     * --------------------------------------------------------------------
     * Number of 30-second steps to accept on either side of the current
     * timestamp when verifying a TOTP code. 1 = ±30s on each side
     * (RFC 6238 default), allowing for minor clock drift.
     *
     * Higher values relax verification (worse security); lower values
     * tighten it (worse UX under clock skew).
     */
    public int $totpWindow = 1;

    /**
     * --------------------------------------------------------------------
     * Trusted Device Lifetime
     * --------------------------------------------------------------------
     * When > 0, users can opt in to "trust this device" during 2FA. The
     * device's DeviceSession is marked trusted for this many seconds; on
     * subsequent logins from the same device (matched via signed cookie
     * + DB session UUID) the 2FA challenge is skipped.
     *
     * 0 = feature disabled (always require 2FA when configured).
     */
    public int $trustedDeviceLifetime = 30 * DAY;

    /**
     * --------------------------------------------------------------------
     * Permission & Group Cache
     * --------------------------------------------------------------------
     * When enabled, user groups and permissions are stored in the CI4
     * cache service to avoid repeated DB queries on every request.
     *
     * **STRONGLY RECOMMENDED in production.** With caching disabled every
     * `$user->can()`, `inGroup()`, `hasPermission()` call hits the DB
     * (groups + permissions + group permissions). The cache is invalidated
     * automatically when assignments change.
     *
     * Disabled by default to keep BC with installs that have not configured
     * a cache backend. Override in `app/Config/AuthSecurity.php`:
     *
     *     public bool $permissionCacheEnabled = true;
     *
     * - permissionCacheEnabled  Enable/disable caching (default: false)
     * - permissionCacheTTL      Seconds before the cache expires (default: 300)
     */
    public bool $permissionCacheEnabled = false;

    public int $permissionCacheTTL = 300;

    /**
     * --------------------------------------------------------------------
     * Remember-Me Purge Chance
     * --------------------------------------------------------------------
     * Probability (1–100) that old remember-me tokens are purged on login.
     * Higher values mean more frequent purging. 0 = never purge automatically.
     */
    public int $rememberMePurgeChance = 20;

    /**
     * --------------------------------------------------------------------
     * Pwned Passwords API URL
     * --------------------------------------------------------------------
     * Base URI for the Have I Been Pwned passwords range API.
     */
    public string $pwnedPasswordsApiUrl = 'https://api.pwnedpasswords.com/';

    /**
     * --------------------------------------------------------------------
     * Pwned Passwords Timeouts
     * --------------------------------------------------------------------
     * Timeouts (in seconds) for the HTTP call to the HaveIBeenPwned
     * Passwords range API. Keep these short to avoid blocking
     * registration / password-change flows when the API is slow.
     */
    public float $pwnedPasswordsConnectTimeout = 1.0;

    public float $pwnedPasswordsTimeout = 3.0;

    /**
     * --------------------------------------------------------------------
     * Recheck Pwned Password On Login
     * --------------------------------------------------------------------
     * When true, after a successful password verification on login the
     * password is rechecked against the HIBP range API. If the password
     * appears in a known breach corpus, `force_reset` is set on the user's
     * email_password identity so the next request redirects to the force
     * password reset flow.
     *
     * Disabled by default: extra HTTP call per login adds latency and a
     * dependency on an external service. Enable in production only when
     * the timeouts above are tuned and HIBP is reachable.
     */
    public bool $recheckPwnedOnLogin = false;

    /**
     * --------------------------------------------------------------------
     * Password History (prevent reuse)
     * --------------------------------------------------------------------
     * Number of recent password hashes retained per user. The
     * {@see \Daycry\Auth\Authentication\Passwords\HistoryValidator} rejects
     * any new password matching one of the last N hashes.
     *
     * 0 = feature disabled (no history kept, no reuse check).
     */
    public int $passwordHistorySize = 0;

    /**
     * --------------------------------------------------------------------
     * Password Maximum Age (rotation policy)
     * --------------------------------------------------------------------
     * When > 0, the {@see \Daycry\Auth\Filters\PasswordAgeFilter} forces a
     * password reset once a user's `password_changed_at` is older than this
     * many seconds.
     *
     * 0 = passwords never expire.
     */
    public int $passwordMaxAge = 0;

    /**
     * --------------------------------------------------------------------
     * Suspicious Login Alerts
     * --------------------------------------------------------------------
     * When true, every successful login runs the
     * {@see \Daycry\Auth\Authentication\Services\SuspiciousLoginDetector}
     * and fires `suspicious-login` event + audit log entry whenever the
     * IP or User-Agent does not match the user's recent history.
     *
     * Wire an `Events::on('suspicious-login', ...)` handler in your app to
     * email the user / alert oncall on a flag.
     */
    public bool $suspiciousLoginAlerts = false;
}
