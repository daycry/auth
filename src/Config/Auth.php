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
use Daycry\Auth\Authentication\Authenticators\AccessToken;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Authentication\Passwords\CompositionValidator;
use Daycry\Auth\Authentication\Passwords\DictionaryValidator;
use Daycry\Auth\Authentication\Passwords\NothingPersonalValidator;
use Daycry\Auth\Models\UserModel;

class Auth extends BaseConfig
{
    /**
     * ////////////////////////////////////////////////////////////////////
     * AUTHENTICATION
     * ////////////////////////////////////////////////////////////////////
     */

    // Constants for Record Login Attempts. Do not change.
    public const RECORD_LOGIN_ATTEMPT_NONE    = 0; // Do not record at all
    public const RECORD_LOGIN_ATTEMPT_FAILURE = 1; // Record only failures
    public const RECORD_LOGIN_ATTEMPT_ALL     = 2; // Record all login attempts

    /**
     * --------------------------------------------------------------------
     * Customize the DB group used for each model
     * --------------------------------------------------------------------
     */
    public ?string $DBGroup = null;

    /**
     * --------------------------------------------------------------------
     * Customize Name of Shield Tables
     * --------------------------------------------------------------------
     * Only change if you want to rename the default Shield table names
     *
     * It may be necessary to change the names of the tables for
     * security reasons, to prevent the conflict of table names,
     * the internal policy of the companies or any other reason.
     *
     * - users                  Auth Users Table, the users info is stored.
     * - auth_identities        Auth Identities Table, Used for storage of passwords, access tokens, social login identities, etc.
     * - auth_logins            Auth Login Attempts, Table records login attempts.
     * - auth_token_logins      Auth Token Login Attempts Table, Records Bearer Token type login attempts.
     * - auth_remember_tokens   Auth Remember Tokens (remember-me) Table.
     * - auth_groups_users      Groups Users Table.
     * - auth_permissions_users Users Permissions Table.
     *
     * @var array<string, string>
     */
    public array $tables = [
        'users'              => 'users',
        'permissions_users'  => 'auth_permissions_users',
        'identities'         => 'auth_identities',
        'logins'             => 'auth_logins',
        'token_logins'       => 'auth_token_logins',
        'remember_tokens'    => 'auth_remember_tokens',
        'groups'             => 'auth_groups',
        'permissions_groups' => 'auth_permissions_groups',
        'groups_users'       => 'auth_groups_users',
        'logs'               => 'auth_logs',
        'apis'               => 'auth_apis',
        'controllers'        => 'auth_controllers',
        'endpoints'          => 'auth_endpoints',
        'attempts'           => 'auth_attempts',
        'limits'             => 'auth_limits',
    ];

    /**
     * --------------------------------------------------------------------
     * Authenticators
     * --------------------------------------------------------------------
     * The available authentication systems, listed
     * with alias and class name. These can be referenced
     * by alias in the auth helper:
     *      auth('tokens')->attempt($credentials);
     *
     * @var array<string, class-string<AuthenticatorInterface>>
     */
    public array $authenticators = [
        'tokens'  => AccessToken::class,
        'session' => Session::class,
    ];

    /**
     * --------------------------------------------------------------------
     * Default Authenticator
     * --------------------------------------------------------------------
     * The Authenticator to use when none is specified.
     * Uses the $key from the $authenticators array above.
     */
    public string $defaultAuthenticator = 'session';

    /**
     * --------------------------------------------------------------------
     * Authentication Actions
     * --------------------------------------------------------------------
     * Specifies the class that represents an action to take after
     * the user logs in or registers a new account at the site.
     *
     * You must register actions in the order of the actions to be performed.
     *
     * Available actions with Shield:
     * - register: \CodeIgniter\Shield\Authentication\Actions\EmailActivator::class
     * - login:    \CodeIgniter\Shield\Authentication\Actions\Email2FA::class
     *
     * @var array<string, class-string<ActionInterface>|null>
     */
    public array $actions = [
        'register' => null,
        'login'    => null,
    ];

    /**
     * --------------------------------------------------------------------
     * User Provider
     * --------------------------------------------------------------------
     * The name of the class that handles user persistence.
     * By default, this is the included UserModel, which
     * works with any of the database engines supported by CodeIgniter.
     * You can change it as long as they adhere to the
     * CodeIgniter\Shield\Models\UserModel.
     *
     * @var class-string<UserModel>
     */
    public string $userProvider = UserModel::class;

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
     * CodeIgniter\Shield\Authentication\Passwords\ValidatorInterface.
     *
     * @var class-string<ValidatorInterface>[]
     */
    public array $passwordValidators = [
        CompositionValidator::class,
        NothingPersonalValidator::class,
        DictionaryValidator::class,
        // PwnedValidator::class,
    ];

    /**
     * --------------------------------------------------------------------
     * Valid login fields
     * --------------------------------------------------------------------
     * Fields that are available to be used as credentials for login.
     */
    public array $validFields = [
        'email',
        // 'username',
    ];

    /**
     * --------------------------------------------------------------------
     * Additional Fields for "Nothing Personal"
     * --------------------------------------------------------------------
     * The NothingPersonalValidator prevents personal information from
     * being used in passwords. The email and username fields are always
     * considered by the validator. Do not enter those field names here.
     *
     * An extended User Entity might include other personal info such as
     * first and/or last names. $personalFields is where you can add
     * fields to be considered as "personal" by the NothingPersonalValidator.
     * For example:
     *     $personalFields = ['firstname', 'lastname'];
     */
    public array $personalFields = [];

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
     * Using values at either extreme of the *working range* (1-100) is
     * not advised. The low end is too restrictive and the high end is too permissive.
     * The suggested value for $maxSimilarity is 50.
     *
     * You may be thinking that a value of 100 should have the effect of accepting
     * everything like a value of 0 does. That's logical and probably true,
     * but is unproven and untested. Besides, 0 skips the work involved
     * making the calculation unlike when using 100.
     *
     * The (admittedly limited) testing that's been done suggests a useful working range
     * of 50 to 60. You can set it lower than 50, but site users will probably start
     * to complain about the large number of proposed passwords getting rejected.
     * At around 60 or more it starts to see pairs like 'captain joe' and 'joe*captain' as
     * perfectly acceptable which clearly they are not.
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
     * However, depending on the security needs of your application
     * and the power of your hardware, you might want to increase the
     * cost. This makes the hashing process takes longer.
     *
     * Valid range is between 4 - 31.
     */
    public int $hashCost = 12;

    /**
     * If you need to support passwords saved in versions prior to Shield v1.0.0-beta.4.
     * set this to true.
     *
     * See https://github.com/codeigniter4/shield/security/advisories/GHSA-c5vj-f36q-p9vg
     *
     * @deprecated This is only for backward compatibility.
     */
    public bool $supportOldDangerousPassword = false;
}
