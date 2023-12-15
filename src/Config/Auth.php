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
use Daycry\Auth\Authentication\Authenticators\JWT;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Authentication\JWT\Adapters\DaycryJWTAdapter;
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

    public int $recordLoginAttempt = Auth::RECORD_LOGIN_ATTEMPT_ALL;

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
        'permissions'        => 'auth_permissions',
        'permissions_users'  => 'auth_permissions_users',
        'identities'         => 'auth_identities',
        'logins'             => 'auth_logins',
        'remember_tokens'    => 'auth_remember_tokens',
        'groups'             => 'auth_groups',
        'permissions_groups' => 'auth_permissions_groups',
        'groups_users'       => 'auth_groups_users',
        'logs'               => 'auth_logs',
        'apis'               => 'auth_apis',
        'controllers'        => 'auth_controllers',
        'endpoints'          => 'auth_endpoints',
        'attempts'           => 'auth_attempts',
        'rates'             => 'auth_rates',
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
        'access_token'  => AccessToken::class,
        'session'       => Session::class,
        'jwt'           => JWT::class
    ];

    public string $jwtAdapter = DaycryJWTAdapter::class;

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
     * Name of Authenticator Header
     * --------------------------------------------------------------------
     * The name of Header that the Authorization token should be found.
     * According to the specs, this should be `Authorization`, but rare
     * circumstances might need a different header.
     */
    public array $authenticatorHeader = [
        'access_token' => 'X-API-KEY',
        'jwt'          => 'Authorization'
    ];

    /**
    *--------------------------------------------------------------------------
    * Access Token
    *--------------------------------------------------------------------------
    */
    public bool $accessTokenEnabled = false;
    public int $unusedAccessTokenLifetime = YEAR;
    public bool $strictApiAndAuth = false; // force the use of both api and auth before a valid api request is made

    /**
     * --------------------------------------------------------------------
     * Session Authenticator Configuration
     * --------------------------------------------------------------------
     * These settings only apply if you are using the Session Authenticator
     * for authentication.
     *
     * - field                  The name of the key the current user info is stored in session
     * - allowRemembering       Does the system allow use of "remember-me"
     * - rememberCookieName     The name of the cookie to use for "remember-me"
     * - rememberLength         The length of time, in seconds, to remember a user.
     *
     * @var array<string, bool|int|string>
     */
    public array $sessionConfig = [
        'field'              => 'user',
        'allowRemembering'   => true,
        'rememberCookieName' => 'remember',
        'rememberLength'     => 30 * DAY,
    ];
    
    /**
     * --------------------------------------------------------------------
     * Authentication Actions
     * --------------------------------------------------------------------
     * Specifies the class that represents an action to take after
     * the user logs in or registers a new account at the site.
     *
     * You must register actions in the order of the actions to be performed.
     *
     * Available actions with Auth:
     * - register: \Daycry\Auth\Authentication\Actions\EmailActivator::class
     * - login:    \Daycry\Auth\Authentication\Actions\Email2FA::class
     *
     * @var array<string, class-string<ActionInterface>|null>
     */
    public array $actions = [
        'register' => null,
        'login'    => null,
    ];


    /**
     * --------------------------------------------------------------------
     * View files
     * --------------------------------------------------------------------
     */
    public array $views = [
        'login'                       => '\CodeIgniter\Shield\Views\login',
        'register'                    => '\CodeIgniter\Shield\Views\register',
        'layout'                      => '\CodeIgniter\Shield\Views\layout',
        'action_email_2fa'            => '\CodeIgniter\Shield\Views\email_2fa_show',
        'action_email_2fa_verify'     => '\CodeIgniter\Shield\Views\email_2fa_verify',
        'action_email_2fa_email'      => '\CodeIgniter\Shield\Views\Email\email_2fa_email',
        'action_email_activate_show'  => '\CodeIgniter\Shield\Views\email_activate_show',
        'action_email_activate_email' => '\CodeIgniter\Shield\Views\Email\email_activate_email',
        'magic-link-login'            => '\CodeIgniter\Shield\Views\magic_link_form',
        'magic-link-message'          => '\CodeIgniter\Shield\Views\magic_link_message',
        'magic-link-email'            => '\CodeIgniter\Shield\Views\Email\magic_link_email',
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
     *--------------------------------------------------------------------------
     * Rates Control
     * --------------------------------------------------------------------------
     * When set to TRUE, the REST API will count the number of uses of each method
     * by an API key each hour. This is a general rule that can be overridden in the
     * $this->method array in each controller
     *
     * Available methods are :
     * public string $restLimitsMethod = 'IP_ADDRESS'; // Put a limit per ip address
     * public string $restLimitsMethod = 'USER'; // Put a limit per user
     * public string $restLimitsMethod = 'METHOD_NAME'; // Put a limit on method calls
     * public string $restLimitsMethod = 'ROUTED_URL';  // Put a limit on the routed URL
     */
    public string $limitMethod = 'METHOD_NAME';
    public int $requestLimit = 10;
    public int $timeLimit = MINUTE;

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

    /**
     * Returns the URL that a user should be redirected
     * to after a successful login.
     */
    public function loginRedirect(): string
    {
        $session = session();
        $url     = $session->getTempdata('beforeLoginUrl') ?? setting('Auth.redirects')['login'];

        return $this->getUrl($url);
    }

    /**
     * Returns the URL that a user should be redirected
     * to after they are logged out.
     */
    public function logoutRedirect(): string
    {
        $url = setting('Auth.redirects')['logout'];

        return $this->getUrl($url);
    }

    /**
     * Returns the URL the user should be redirected to
     * after a successful registration.
     */
    public function registerRedirect(): string
    {
        $url = setting('Auth.redirects')['register'];

        return $this->getUrl($url);
    }

    /**
     * Returns the URL the user should be redirected to
     * if force_reset identity is set to true.
     */
    public function forcePasswordResetRedirect(): string
    {
        $url = setting('Auth.redirects')['force_reset'];

        return $this->getUrl($url);
    }

    /**
     * Returns the URL the user should be redirected to
     * if permission denied.
     */
    public function permissionDeniedRedirect(): string
    {
        $url = setting('Auth.redirects')['permission_denied'];

        return $this->getUrl($url);
    }

    /**
     * Returns the URL the user should be redirected to
     * if group denied.
     */
    public function groupDeniedRedirect(): string
    {
        $url = setting('Auth.redirects')['group_denied'];

        return $this->getUrl($url);
    }

    /**
     * Accepts a string which can be an absolute URL or
     * a named route or just a URI path, and returns the
     * full path.
     *
     * @param string $url an absolute URL or a named route or just URI path
     */
    protected function getUrl(string $url): string
    {
        // To accommodate all url patterns
        $final_url = '';

        switch (true) {
            case strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0: // URL begins with 'http' or 'https'. E.g. http://example.com
                $final_url = $url;
                break;

            case route_to($url) !== false: // URL is a named-route
                $final_url = rtrim(url_to($url), '/ ');
                break;

            default: // URL is a route (URI path)
                $final_url = rtrim(site_url($url), '/ ');
                break;
        }

        return $final_url;
    }
}
