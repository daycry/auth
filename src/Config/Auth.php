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
use Daycry\Auth\Authentication\Actions\Email2FA;
use Daycry\Auth\Authentication\Authenticators\AccessToken;
use Daycry\Auth\Authentication\Authenticators\Guest;
use Daycry\Auth\Authentication\Authenticators\JWT;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Authentication\JWT\Adapters\DaycryJWTAdapter;
use Daycry\Auth\Authentication\Passwords\CompositionValidator;
use Daycry\Auth\Authentication\Passwords\DictionaryValidator;
use Daycry\Auth\Authentication\Passwords\NothingPersonalValidator;
use Daycry\Auth\Interfaces\ActionInterface;
use Daycry\Auth\Interfaces\AuthenticatorInterface;
use Daycry\Auth\Interfaces\PasswordValidatorInterface;
use Daycry\Auth\Models\UserModel;

class Auth extends BaseConfig
{
    /**
     * ////////////////////////////////////////////////////////////////////
     * LOGGING & RATE LIMITING
     * ////////////////////////////////////////////////////////////////////
     */

    // Constants for Record Login Attempts. Do not change.
    public const RECORD_LOGIN_ATTEMPT_NONE    = 0; // Do not record at all
    public const RECORD_LOGIN_ATTEMPT_FAILURE = 1; // Record only failures
    public const RECORD_LOGIN_ATTEMPT_ALL     = 2; // Record all login attempts
    /**
     * ////////////////////////////////////////////////////////////////////
     * DATABASE
     * ////////////////////////////////////////////////////////////////////
     */

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
        'identities'         => 'auth_users_identities',
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
        'rates'              => 'auth_rates',
    ];

    /**
     * ////////////////////////////////////////////////////////////////////
     * AUTHENTICATION CONFIGURATION
     * ////////////////////////////////////////////////////////////////////
     */

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
        'access_token' => AccessToken::class,
        'session'      => Session::class,
        'jwt'          => JWT::class,
        'guest'        => Guest::class,
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
     * Authentication Chain
     * --------------------------------------------------------------------
     * The Authenticators to test logged in status against
     * when using the 'chain' filter. Each Authenticator listed will be checked.
     * If no match is found, then the next in the chain will be checked.
     *
     * @var         list<string>
     * @phpstan-var list<string>
     */
    public array $authenticationChain = [
        'session',
        'access_token',
        'jwt',
    ];

    public string $jwtAdapter = DaycryJWTAdapter::class;

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
        'jwt'          => 'Authorization',
    ];

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
     *--------------------------------------------------------------------------
     * Access Token
     *--------------------------------------------------------------------------
     */
    public bool $accessTokenEnabled = false;

    public int $unusedAccessTokenLifetime = YEAR;
    public bool $strictApiAndAuth         = false; // force the use of both api and auth before a valid api request is made

    /**
     * --------------------------------------------------------------------
     * Allow Magic Link Logins
     * --------------------------------------------------------------------
     * If true, will allow the use of "magic links" sent via the email
     * as a way to log a user in without the need for a password.
     * By default, this is used in place of a password reset flow, but
     * could be modified as the only method of login once an account
     * has been set up.
     */
    public bool $allowMagicLinkLogins = true;

    /**
     * --------------------------------------------------------------------
     * Magic Link Lifetime
     * --------------------------------------------------------------------
     * Specifies the amount of time, in seconds, that a magic link is valid.
     * You can use Time Constants or any desired number.
     */
    public int $magicLinkLifetime = HOUR;

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
        'login'    => Email2FA::class,
    ];

    /**
     * ////////////////////////////////////////////////////////////////////
     * USER & REGISTRATION
     * ////////////////////////////////////////////////////////////////////
     */

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
     * Allows Registration
     * --------------------------------------------------------------------
     * Determines whether users can register for the site.
     */
    public bool $allowRegistration = true;

    /**
     * --------------------------------------------------------------------
     * Default Group
     * --------------------------------------------------------------------
     * The group that a newly registered user is added to.
     */
    public string $defaultGroup = 'user';

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
     * Personal Fields
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
     * The validation rules for username
     * --------------------------------------------------------------------
     *
     * Do not use string rules like `required|valid_email`.
     *
     * @var array<string, array<int, string>|string>
     */
    public array $usernameValidationRules = [
        'label' => 'Auth.username',
        'rules' => [
            'required',
            'max_length[30]',
            'min_length[3]',
            'regex_match[/\A[a-zA-Z0-9\.]+\z/]',
        ],
    ];

    /**
     * --------------------------------------------------------------------
     * The validation rules for email
     * --------------------------------------------------------------------
     *
     * Do not use string rules like `required|valid_email`.
     *
     * @var array<string, array<int, string>|string>
     */
    public array $emailValidationRules = [
        'label' => 'Auth.email',
        'rules' => [
            'required',
            'max_length[254]',
            'valid_email',
        ],
    ];

    /**
     * ////////////////////////////////////////////////////////////////////
     * PASSWORD & SECURITY
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

    public int $recordLoginAttempt = Auth::RECORD_LOGIN_ATTEMPT_ALL;

    /**
     * --------------------------------------------------------------------
     * Record Last Active Date
     * --------------------------------------------------------------------
     * If true, will always update the `last_active` datetime for the
     * logged-in user on every page request.
     * This feature only works when session/tokens filter is active.
     *
     * @see https://codeigniter4.github.io/shield/quick_start_guide/using_session_auth/#protecting-pages for set filters.
     */
    public bool $recordActiveDate = true;

    /**
     *--------------------------------------------------------------------------
     * AUTH Logs
     * --------------------------------------------------------------------------
     * When set to TRUE, the REST API will save requests
     */
    public bool $enableLogs = false;

    /**
     * --------------------------------------------------------------------------
     * Enable block Invalid Attempts
     * --------------------------------------------------------------------------
     *
     * IP blocking on consecutive failed attempts
     */
    public bool $enableInvalidAttempts = false;

    public int $maxAttempts = 10;
    public int $timeBlocked = 3600;

    /**
     *--------------------------------------------------------------------------
     * Rate Limiting Control
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
    public int $timeLimit    = MINUTE;

    /**
     * ////////////////////////////////////////////////////////////////////
     * OAUTH
     * ////////////////////////////////////////////////////////////////////
     */

    /**
     * --------------------------------------------------------------------------
     * OAuth Providers
     * --------------------------------------------------------------------------
     *
     * The available OAuth providers.
     * key = provider alias (e.g. 'azure', 'google')
     * value = configuration array or ClassName::class
     */
    public array $providers = [
        'azure' => [
            'clientId'                => 'YOUR_CLIENT_ID',
            'clientSecret'            => 'YOUR_CLIENT_SECRET',
            'redirectUri'             => 'http://localhost:8080/auth/oauth/azure/callback',
            'urlAuthorize'            => 'https://login.microsoftonline.com/YOUR_TENANT_ID/oauth2/v2.0/authorize',
            'urlAccessToken'          => 'https://login.microsoftonline.com/YOUR_TENANT_ID/oauth2/v2.0/token',
            'urlResourceOwnerDetails' => '',
            'scopes'                  => ['openid', 'profile', 'email', 'offline_access', 'User.Read'],
            'defaultEndPointVersion'  => '2.0',
            'tenant'                  => 'common',
        ],
        // 'google' => [ ... ]
    ];

    /**
     * ////////////////////////////////////////////////////////////////////
     * VIEWS & URLS
     * ////////////////////////////////////////////////////////////////////
     */

    /**
     * --------------------------------------------------------------------
     * View files
     * --------------------------------------------------------------------
     */
    public array $views = [
        'login'                       => '\Daycry\Auth\Views\login',
        'register'                    => '\Daycry\Auth\Views\register',
        'layout'                      => '\Daycry\Auth\Views\layout',
        'action_email_2fa'            => '\Daycry\Auth\Views\email_2fa_show',
        'action_email_2fa_verify'     => '\Daycry\Auth\Views\email_2fa_verify',
        'action_email_2fa_email'      => '\Daycry\Auth\Views\Email\email_2fa_email',
        'action_email_activate_show'  => '\Daycry\Auth\Views\email_activate_show',
        'action_email_activate_email' => '\Daycry\Auth\Views\Email\email_activate_email',
        'magic-link-login'            => '\Daycry\Auth\Views\magic_link_form',
        'magic-link-message'          => '\Daycry\Auth\Views\magic_link_message',
        'magic-link-email'            => '\Daycry\Auth\Views\Email\magic_link_email',
    ];

    /**
     * --------------------------------------------------------------------
     * Redirect URLs
     * --------------------------------------------------------------------
     * The default URL that a user will be redirected to after various auth
     * actions. This can be either of the following:
     *
     * 1. An absolute URL. E.g. http://example.com OR https://example.com
     * 2. A named route that can be accessed using `route_to()` or `url_to()`
     * 3. A URI path within the application. e.g 'admin', 'login', 'expath'
     *
     * If you need more flexibility you can override the `getUrl()` method
     * to apply any logic you may need.
     */
    public array $redirects = [
        'register'          => '/',
        'login'             => '/',
        'logout'            => 'login',
        'force_reset'       => '/',
        'permission_denied' => '/',
        'group_denied'      => '/',
    ];

    /**
     * Routes definition
     */
    public array $routes = [
        'register' => [
            [
                'get',
                'register',
                'RegisterController::registerView',
                'register', // Route name
            ],
            [
                'post',
                'register',
                'RegisterController::registerAction',
            ],
        ],
        'login' => [
            [
                'get',
                'login',
                'LoginController::loginView',
                'login', // Route name
            ],
            [
                'post',
                'login',
                'LoginController::loginAction',
            ],
        ],
        'magic-link' => [
            [
                'get',
                'login/magic-link',
                'MagicLinkController::loginView',
                'magic-link',        // Route name
            ],
            [
                'post',
                'login/magic-link',
                'MagicLinkController::loginAction',
            ],
            [
                'get',
                'login/verify-magic-link',
                'MagicLinkController::verify',
                'verify-magic-link', // Route name
            ],
        ],
        'logout' => [
            [
                'get',
                'logout',
                'LoginController::logoutAction',
                'logout', // Route name
            ],
        ],
        'auth-actions' => [
            [
                'get',
                'auth/a/show',
                'ActionController::show',
                'auth-action-show', // Route name
            ],
            [
                'post',
                'auth/a/handle',
                'ActionController::handle',
                'auth-action-handle', // Route name
            ],
            [
                'post',
                'auth/a/verify',
                'ActionController::verify',
                'auth-action-verify', // Route name
            ],
        ],
        'oauth' => [
            [
                'get',
                'oauth/login/(:segment)', // Provider (azure, google, etc)
                'OauthController::redirect/$1',
                'oauth-login',
            ],
            [
                'get',
                'oauth/callback/(:segment)', // Provider (azure, google, etc)
                'OauthController::callback/$1',
                'oauth-callback',
            ],
        ],
    ];

    /**
     * ////////////////////////////////////////////////////////////////////
     * DISCOVERY & CRON
     * ////////////////////////////////////////////////////////////////////
     */

    /**
     *--------------------------------------------------------------------------
     * Cronjob
     *--------------------------------------------------------------------------
     *
     * Set to TRUE to enable Cronjob for fill the table petitions with your API classes
     * $restNamespaceScope \Namespace\Class or \Namespace\Folder\Class or \Namespace example: \App\Controllers
     *
     * This feature use Daycry\CronJob vendor
     * for more information: https://github.com/daycry/cronjob
     */
    public bool $enableDiscovery = false;

    /**
     * Ex: $namespaceScope = ['\Api\Controllers\Class', '\App\Controllers\Class'];
     */
    public array $namespaceScope = ['\Daycry\Auth\Controllers'];

    /**
     * Exclude methods in discovering
     *
     * This is useful when you use traits or the class extends the initController method
     *
     * Ex: doLogin is a Authenticable trait method and initController is a method of ResourceController class
     */
    public array $excludeMethods = ['initController', '_remap'];

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
            case str_starts_with($url, 'http://') || str_starts_with($url, 'https://')  : // URL begins with 'http' or 'https'. E.g. http://example.com
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
