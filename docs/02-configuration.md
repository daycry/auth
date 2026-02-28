# ⚙️ Configuration Reference

This guide covers every configuration option available in Daycry Auth.

## The Configuration File

After running `php spark auth:setup`, a file `app/Config/Auth.php` is created that extends the library's base configuration:

```php
<?php

namespace Config;

use Daycry\Auth\Config\Auth as BaseAuth;

class Auth extends BaseAuth
{
    // Override only what you need
}
```

All options listed below can be overridden in this file.

---

## Database

### Database Group

```php
// Use the default database connection (recommended)
public ?string $DBGroup = null;

// Use a dedicated auth database
public ?string $DBGroup = 'auth_db';
```

### Table Names

Customize any table name to avoid conflicts with your existing schema:

```php
public array $tables = [
    'users'              => 'users',                    // Main users table
    'identities'         => 'auth_users_identities',    // Passwords, tokens, TOTP secrets
    'logins'             => 'auth_logins',              // Login attempt log
    'remember_tokens'    => 'auth_remember_tokens',     // "Remember me" cookies
    'groups'             => 'auth_groups',              // Group definitions
    'groups_users'       => 'auth_groups_users',        // User ↔ group assignments
    'permissions'        => 'auth_permissions',         // Permission definitions
    'permissions_users'  => 'auth_permissions_users',   // User direct permissions
    'permissions_groups' => 'auth_permissions_groups',  // Group permissions
    'logs'               => 'auth_logs',                // Activity log
    'apis'               => 'auth_apis',                // API registry (discovery)
    'controllers'        => 'auth_controllers',         // Controller registry
    'endpoints'          => 'auth_endpoints',           // Endpoint registry
    'attempts'           => 'auth_attempts',            // IP-based failed attempts
    'rates'              => 'auth_rates',               // Rate limit counters
    'device_sessions'    => 'auth_device_sessions',     // Active device sessions
];
```

---

## Authenticators

### Available Authenticators

```php
use Daycry\Auth\Authentication\Authenticators\AccessToken;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Authentication\Authenticators\JWT;
use Daycry\Auth\Authentication\Authenticators\Guest;

public array $authenticators = [
    'access_token' => AccessToken::class,
    'session'      => Session::class,
    'jwt'          => JWT::class,
    'guest'        => Guest::class,
];

// The authenticator used when you call auth() with no argument
public string $defaultAuthenticator = 'session';
```

### JWT Adapter

```php
use Daycry\Auth\Authentication\JWT\Adapters\DaycryJWTAdapter;

public string $jwtAdapter = DaycryJWTAdapter::class;
```

### Authentication Chain

The `chain` filter tries authenticators in order and stops at the first success:

```php
public array $authenticationChain = [
    'session',      // Try session first (web users)
    'access_token', // Then access tokens (API keys)
    'jwt',          // Finally JWT (Bearer tokens)
];
```

---

## Session Authenticator

```php
public array $sessionConfig = [
    'field'               => 'user',       // Session key name
    'allowRemembering'    => true,         // Enable "remember me"
    'rememberCookieName'  => 'remember',   // Cookie name for remember me
    'rememberLength'      => 30 * DAY,     // Remember me duration

    // Track active device sessions (login from each device/browser)
    'trackDeviceSessions' => false,        // Set true to enable
];
```

---

## User Settings

```php
// Allow public registration
public bool $allowRegistration = true;

// Default group assigned to every new user (must exist in auth_groups table)
public string $defaultGroup = 'user';

// Class responsible for finding users in the database
public string $userProvider = \Daycry\Auth\Models\UserModel::class;

// Which fields can be used to log in
public array $validFields = [
    'email',
    // 'username', // Uncomment to allow username login
];
```

---

## Password Settings

```php
// Minimum password length
public int $minimumPasswordLength = 8;

// Validators that run on every password
public array $passwordValidators = [
    \Daycry\Auth\Authentication\Passwords\CompositionValidator::class,     // Requires mixed chars
    \Daycry\Auth\Authentication\Passwords\NothingPersonalValidator::class, // No personal info
    \Daycry\Auth\Authentication\Passwords\DictionaryValidator::class,      // No dictionary words
    // \Daycry\Auth\Authentication\Passwords\PwnedValidator::class,        // HaveIBeenPwned check
];

// Hash algorithm (PASSWORD_DEFAULT, PASSWORD_BCRYPT, PASSWORD_ARGON2I, PASSWORD_ARGON2ID)
public string $hashAlgorithm = PASSWORD_DEFAULT;
public int    $hashCost        = 12;      // bcrypt cost (4–31)

// argon2 options
public int $hashMemoryCost = 65536;   // KB
public int $hashTimeCost   = 4;       // Iterations
public int $hashThreads    = 1;       // Threads

// Personal data that passwords should not resemble
public array $personalFields = ['firstname', 'lastname', 'phone'];

// 0–100: max allowed similarity between password and personal fields (0 = disabled)
public int $maxSimilarity = 50;
```

---

## Password Reset

```php
// How long a password reset token remains valid
public int $passwordResetLifetime = HOUR; // Default: 1 hour
```

The reset flow is handled by `PasswordResetController`. Users request a reset link, receive it by email, and click it within this window. See [Controllers](05-controllers.md#password-reset-controller) for the full setup.

---

## Per-User Account Lockout

Independent of IP-based blocking, this locks an individual account after too many failed password attempts:

```php
// Max failed attempts before locking the account (0 = disabled)
public int $userMaxAttempts = 5;

// How long to keep the account locked (seconds)
public int $userLockoutTime = 3600; // 1 hour
```

When the lockout expires, the counter resets automatically on the next login attempt. See [Logging & Monitoring](07-logging.md#per-user-account-lockout) for details and admin unlock instructions.

---

## Magic Links

```php
public bool $allowMagicLinkLogins = true;
public int  $magicLinkLifetime    = HOUR; // Token validity in seconds
```

---

## Access Tokens

```php
public bool $accessTokenEnabled = false;

// How long an unused access token remains valid
public int $unusedAccessTokenLifetime = YEAR;

// Force both API key AND session authentication (strict mode)
public bool $strictApiAndAuth = false;

// Request headers for each authenticator
public array $authenticatorHeader = [
    'access_token' => 'X-API-KEY',
    'jwt'          => 'Authorization',
];
```

---

## JWT Refresh Tokens

When using `JwtController` for stateless JWT authentication, refresh tokens allow issuing new access tokens without re-entering credentials:

```php
// How long a JWT refresh token remains valid
public int $jwtRefreshLifetime = 30 * DAY; // Default: 30 days
```

Refresh tokens are stored in `auth_users_identities` (type: `jwt_refresh`) and are one-time use (rotated on each refresh). See [Authentication — JWT Refresh Tokens](03-authentication.md#jwt-refresh-tokens).

---

## Logging & Monitoring

### Activity Logs

```php
// Write auth events to auth_logs table
public bool $enableLogs = false;

// Login attempt recording
// 0 = none, 1 = failures only, 2 = all attempts
public int $recordLoginAttempt = 2;
```

### IP-Based Failed Attempt Blocking

```php
public bool $enableInvalidAttempts = false;
public int  $maxAttempts           = 10;    // Attempts before blocking
public int  $timeBlocked           = 3600;  // Seconds
```

### Rate Limiting

```php
// Identify rate limit subject: 'IP_ADDRESS', 'USER', 'METHOD_NAME', 'ROUTED_URL'
public string $limitMethod   = 'IP_ADDRESS';
public int    $requestLimit  = 60;      // Max requests per window
public int    $timeLimit     = MINUTE;  // Window size in seconds
```

---

## Authorization Cache

Avoid repeated database queries for group/permission checks in production:

```php
public bool $permissionCacheEnabled = false; // true = use CI4 cache
public int  $permissionCacheTTL     = 300;   // Cache lifetime in seconds
```

The cache is automatically invalidated when you call `addGroup()`, `removeGroup()`, `addPermission()`, or `removePermission()`. See [Authorization — Permission Cache](06-authorization.md#permission-cache).

---

## Views

Override any built-in view with your own:

```php
public array $views = [
    // Core
    'login'                       => '\Daycry\Auth\Views\login',
    'register'                    => '\Daycry\Auth\Views\register',
    'layout'                      => '\Daycry\Auth\Views\layout',

    // Email 2FA
    'action_email_2fa'            => '\Daycry\Auth\Views\email_2fa_show',
    'action_email_2fa_verify'     => '\Daycry\Auth\Views\email_2fa_verify',
    'action_email_2fa_email'      => '\Daycry\Auth\Views\Email\email_2fa_email',

    // Email Activation
    'action_email_activate_show'  => '\Daycry\Auth\Views\email_activate_show',
    'action_email_activate_email' => '\Daycry\Auth\Views\Email\email_activate_email',

    // Magic Link
    'magic-link-login'            => '\Daycry\Auth\Views\magic_link_form',
    'magic-link-message'          => '\Daycry\Auth\Views\magic_link_message',
    'magic-link-email'            => '\Daycry\Auth\Views\Email\magic_link_email',

    // Password Reset
    'password-reset-request'      => '\Daycry\Auth\Views\password_reset_request',
    'password-reset-message'      => '\Daycry\Auth\Views\password_reset_message',
    'password-reset-form'         => '\Daycry\Auth\Views\password_reset_form',
    'password-reset-email'        => '\Daycry\Auth\Views\Email\password_reset_email',

    // Force Password Reset
    'force-password-reset'        => '\Daycry\Auth\Views\force_password_reset',

    // Email Change Confirmation
    'email-change-email'          => '\Daycry\Auth\Views\Email\email_change_email',
];
```

---

## Redirects

```php
public array $redirects = [
    'register'          => '/',        // After successful registration
    'login'             => '/',        // After successful login
    'logout'            => 'login',    // After logout (route name or URL)
    'force_reset'       => '/',        // After forced password reset
    'permission_denied' => '/',        // GroupFilter/PermissionFilter denial
    'group_denied'      => '/',        // GroupFilter denial
];
```

Override with a method for dynamic redirects:

```php
public function loginRedirect(): string
{
    $user = auth()->user();
    return $user->inGroup('admin') ? site_url('admin') : site_url('dashboard');
}
```

---

## Routes

All auth routes are defined here. Each entry is `[method, uri, controller::method, routeName?]`:

```php
public array $routes = [
    'register' => [
        ['get',  'register', 'RegisterController::registerView',  'register'],
        ['post', 'register', 'RegisterController::registerAction'],
    ],
    'login' => [
        ['get',  'login', 'LoginController::loginView',   'login'],
        ['post', 'login', 'LoginController::loginAction'],
    ],
    'magic-link' => [
        ['get',  'login/magic-link',        'MagicLinkController::loginView',  'magic-link'],
        ['post', 'login/magic-link',        'MagicLinkController::loginAction'],
        ['get',  'login/verify-magic-link', 'MagicLinkController::verify',     'verify-magic-link'],
    ],
    'logout' => [
        ['get', 'logout', 'LoginController::logoutAction', 'logout'],
    ],
    'auth-actions' => [
        ['get',  'auth/a/show',   'ActionController::show',   'auth-action-show'],
        ['post', 'auth/a/handle', 'ActionController::handle', 'auth-action-handle'],
        ['post', 'auth/a/verify', 'ActionController::verify', 'auth-action-verify'],
    ],

    // Password Reset
    'password-reset' => [
        ['get',  'password-reset',         'PasswordResetController::requestView',   'password-reset'],
        ['post', 'password-reset',         'PasswordResetController::requestAction'],
        ['get',  'password-reset/message', 'PasswordResetController::messageView',   'password-reset-message'],
        ['get',  'password-reset/(:any)',   'PasswordResetController::resetView',     'password-reset-form'],
        ['post', 'password-reset/(:any)',   'PasswordResetController::resetAction'],
    ],

    // Force Password Reset
    'force-reset' => [
        ['get',  'force-reset', 'ForcePasswordResetController::showView',    'force-reset'],
        ['post', 'force-reset', 'ForcePasswordResetController::resetAction'],
    ],

    // JWT (stateless, for APIs)
    'jwt' => [
        ['post', 'auth/jwt/login',   'JwtController::login',   'jwt-login'],
        ['post', 'auth/jwt/refresh', 'JwtController::refresh', 'jwt-refresh'],
        ['post', 'auth/jwt/logout',  'JwtController::logout',  'jwt-logout'],
    ],
];
```

---

## Post-Authentication Actions

Run additional verification steps after login or registration:

```php
use Daycry\Auth\Authentication\Actions\Email2FA;
use Daycry\Auth\Authentication\Actions\EmailActivator;
use Daycry\Auth\Authentication\Actions\Totp2FA;

public array $actions = [
    'register' => EmailActivator::class, // Require email confirmation on signup
    'login'    => Email2FA::class,        // Require email 2FA on login
    // 'login' => Totp2FA::class,         // Or: require TOTP on login (per-user)
];
```

| Action | What It Does |
|--------|-------------|
| `Email2FA` | Sends a 6-digit code to the user's email; required to complete login |
| `EmailActivator` | Sends an activation link; user must click before first login |
| `Totp2FA` | Requires a valid TOTP code (only for users who have enrolled) |

---

## Field Validation Rules

```php
public array $usernameValidationRules = [
    'label' => 'Auth.username',
    'rules' => [
        'required',
        'max_length[30]',
        'min_length[3]',
        'regex_match[/\A[a-zA-Z0-9\.]+\z/]',
    ],
];

public array $emailValidationRules = [
    'label' => 'Auth.email',
    'rules' => [
        'required',
        'max_length[254]',
        'valid_email',
    ],
];
```

---

## OAuth Providers

```php
public array $providers = [
    'google' => [
        'clientId'     => 'GOOGLE_CLIENT_ID',
        'clientSecret' => 'GOOGLE_CLIENT_SECRET',
        'redirectUri'  => 'https://yourapp.com/oauth/google/callback',
        'scopes'       => ['openid', 'email', 'profile'],
    ],
    'github' => [
        'clientId'     => 'GITHUB_CLIENT_ID',
        'clientSecret' => 'GITHUB_CLIENT_SECRET',
        'redirectUri'  => 'https://yourapp.com/oauth/github/callback',
        'scopes'       => ['user:email'],
    ],
    'azure' => [
        'clientId'     => 'AZURE_CLIENT_ID',
        'clientSecret' => 'AZURE_CLIENT_SECRET',
        'redirectUri'  => 'https://yourapp.com/oauth/azure/callback',
        'tenant'       => 'common',
        'scopes'       => ['openid', 'profile', 'email', 'offline_access'],
    ],
];
```

See [OAuth 2.0 & Social Login](09-oauth.md) for full setup instructions.

---

## Common Presets

### Web Application

```php
public bool   $allowRegistration = true;
public string $defaultAuthenticator = 'session';
public bool   $allowMagicLinkLogins = true;
public int    $userMaxAttempts   = 5;
public int    $userLockoutTime   = 1800; // 30 minutes
public array  $actions = ['register' => null, 'login' => null];
```

### API (Stateless)

```php
public string $defaultAuthenticator = 'jwt';
public bool   $accessTokenEnabled   = true;
public int    $jwtRefreshLifetime   = 30 * DAY;
public array  $authenticationChain  = ['jwt', 'access_token'];
public int    $recordLoginAttempt   = 2;
```

### High-Security

```php
public int    $minimumPasswordLength = 12;
public bool   $enableInvalidAttempts = true;
public int    $maxAttempts           = 5;
public int    $timeBlocked           = 3600;
public int    $userMaxAttempts       = 3;
public int    $userLockoutTime       = 7200;  // 2 hours
public bool   $permissionCacheEnabled = true;
public array  $actions = ['login' => \Daycry\Auth\Authentication\Actions\Totp2FA::class];
```

---

## Dynamic Configuration

Override methods for runtime-computed values:

```php
class Auth extends BaseAuth
{
    public function loginRedirect(): string
    {
        if (auth()->user()?->inGroup('admin')) {
            return site_url('admin/dashboard');
        }
        return site_url('dashboard');
    }

    public function __construct()
    {
        parent::__construct();

        // Load secrets from environment
        if (isset($_ENV['JWT_SECRET'])) {
            // Configure JWT via the jwt library's config
        }
    }
}
```

---

🔗 **Next**: [Authentication](03-authentication.md) — Use each authenticator
