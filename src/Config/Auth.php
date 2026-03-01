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
use Daycry\Auth\Authentication\Actions\EmailActivator;
use Daycry\Auth\Authentication\Actions\Totp2FA;
use Daycry\Auth\Authentication\Authenticators\AccessToken;
use Daycry\Auth\Authentication\Authenticators\JWT;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Authentication\JWT\Adapters\DaycryJWTAdapter;
use Daycry\Auth\Interfaces\ActionInterface;
use Daycry\Auth\Interfaces\AuthenticatorInterface;
use Daycry\Auth\Models\UserModel;

class Auth extends BaseConfig
{
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
        'device_sessions'    => 'auth_device_sessions',
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
        'field'               => 'user',
        'allowRemembering'    => true,
        'rememberCookieName'  => 'remember',
        'rememberLength'      => 30 * DAY,
        'trackDeviceSessions' => true,
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
     * Post-authentication actions triggered after login or register.
     * Set a key to null to disable the action for that event.
     *
     * Available action classes:
     * - Email2FA::class       — sends a 6-digit code by email (login)
     * - Totp2FA::class        — validates an RFC 6238 TOTP code (login)
     * - EmailActivator::class — requires email confirmation before login (register)
     *
     * Only one action per event is supported.
     *
     * @var array<string, class-string<ActionInterface>|null>
     */
    public array $actions = [
        'register' => null,            // e.g. EmailActivator::class
        'login'    => null,            // e.g. Email2FA::class or Totp2FA::class
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
        'action_totp_2fa_verify'      => '\Daycry\Auth\Views\totp_2fa_verify',
        'action_totp_setup_show'      => '\Daycry\Auth\Views\totp_setup_show',
        'action_totp_setup_success'   => '\Daycry\Auth\Views\totp_setup_success',
        'action_email_activate_show'  => '\Daycry\Auth\Views\email_activate_show',
        'action_email_activate_email' => '\Daycry\Auth\Views\Email\email_activate_email',
        'magic-link-login'            => '\Daycry\Auth\Views\magic_link_form',
        'magic-link-message'          => '\Daycry\Auth\Views\magic_link_message',
        'magic-link-email'            => '\Daycry\Auth\Views\Email\magic_link_email',
        // Password reset
        'password-reset-request' => '\Daycry\Auth\Views\password_reset_request',
        'password-reset-message' => '\Daycry\Auth\Views\password_reset_message',
        'password-reset-form'    => '\Daycry\Auth\Views\password_reset_form',
        'password-reset-email'   => '\Daycry\Auth\Views\Email\password_reset_email',
        // Force password reset (filter redirect)
        'force-password-reset' => '\Daycry\Auth\Views\force_password_reset',
        // Email change confirmation (sent to new address)
        'email-change-email' => '\Daycry\Auth\Views\Email\email_change_email',
        // User security overview (device sessions + TOTP status)
        'security_overview' => '\Daycry\Auth\Views\profile\security',
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
        'password-reset' => [
            [
                'get',
                'password-reset',
                'PasswordResetController::requestView',
                'password-reset-request',
            ],
            [
                'post',
                'password-reset',
                'PasswordResetController::requestAction',
            ],
            [
                'get',
                'password-reset/message',
                'PasswordResetController::messageView',
                'password-reset-message',
            ],
            [
                'get',
                'password-reset/verify',
                'PasswordResetController::resetView',
                'password-reset-verify',
            ],
            [
                'post',
                'password-reset/verify',
                'PasswordResetController::resetAction',
            ],
        ],
        'force-reset' => [
            [
                'get',
                'auth/force-reset',
                'ForcePasswordResetController::showView',
                'force-reset',
            ],
            [
                'post',
                'auth/force-reset',
                'ForcePasswordResetController::resetAction',
            ],
        ],
        'jwt' => [
            [
                'post',
                'auth/jwt/login',
                'JwtController::login',
                'jwt-login',
            ],
            [
                'post',
                'auth/jwt/refresh',
                'JwtController::refresh',
                'jwt-refresh',
            ],
            [
                'post',
                'auth/jwt/logout',
                'JwtController::logout',
                'jwt-logout',
            ],
        ],
    ];

    /**
     * ////////////////////////////////////////////////////////////////////
     * DISCOVERY
     * ////////////////////////////////////////////////////////////////////
     */

    /**
     * --------------------------------------------------------------------
     * Controller Discovery
     * --------------------------------------------------------------------
     * Set to TRUE to enable auto-discovery of API controllers to fill the
     * endpoints table. Requires the daycry/jobs package.
     *
     * Ex: $namespaceScope = ['\Api\Controllers\Class', '\App\Controllers\Class'];
     */
    public bool $enableDiscovery = false;

    /**
     * @var list<string>
     */
    public array $namespaceScope = ['\Daycry\Auth\Controllers'];

    /**
     * Exclude methods from discovery.
     * Useful for trait methods or framework base methods.
     *
     * @var list<string>
     */
    public array $excludeMethods = ['initController', '_remap'];

    /**
     * Returns the URL of the login page.
     * Used to redirect back to login on error or when auth is required.
     */
    public function loginRoute(): string
    {
        return $this->getUrl('login');
    }

    /**
     * Returns the URL of the registration page.
     * Used to redirect back to register on validation errors.
     */
    public function registerRoute(): string
    {
        return $this->getUrl('register');
    }

    /**
     * Alias for loginRoute(). Returns the URL of the login page.
     */
    public function loginPage(): string
    {
        return $this->loginRoute();
    }

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
