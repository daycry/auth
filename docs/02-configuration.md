# ⚙️ Configuration Reference

This guide covers every configuration option available in Daycry Auth.

## The Configuration Files

Configuration is split across three files to keep concerns separate. After running `php spark auth:setup`, each is published to `app/Config/`:

| File | Base class | Contains |
|------|-----------|----------|
| `app/Config/Auth.php` | `Daycry\Auth\Config\Auth` | Authenticators, actions, views, tables, routes, session/remember-me |
| `app/Config/AuthSecurity.php` | `Daycry\Auth\Config\AuthSecurity` | Passwords, lockout, rate-limit, token lifetimes, TOTP issuer, permission cache |
| `app/Config/AuthOAuth.php` | `Daycry\Auth\Config\AuthOAuth` | OAuth provider definitions |

Override only what you need in each file:

```php
// app/Config/Auth.php
namespace Config;
use Daycry\Auth\Config\Auth as BaseAuth;
class Auth extends BaseAuth { /* ... */ }

// app/Config/AuthSecurity.php
namespace Config;
use Daycry\Auth\Config\AuthSecurity as BaseAuthSecurity;
class AuthSecurity extends BaseAuthSecurity { /* ... */ }

// app/Config/AuthOAuth.php
namespace Config;
use Daycry\Auth\Config\AuthOAuth as BaseAuthOAuth;
class AuthOAuth extends BaseAuthOAuth { /* ... */ }
```

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
    'webauthn_credentials' => 'auth_webauthn_credentials', // WebAuthn / passkey credentials
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

### Remember-Me Token Purging

> **File**: `app/Config/AuthSecurity.php`

```php
// Probability (1–100) that EXPIRED remember-me tokens are purged inline on
// login. 0 = never purge on the request path (the default).
public int $rememberMePurgeChance = 0;
```

| Property | Default | Meaning |
|----------|---------|---------|
| `$rememberMePurgeChance` | `0` | Percent chance an interactive login also runs an inline purge of expired tokens. `0` disables inline purging entirely |

**Why the default is now `0`.** Token expiry is enforced at *validation* time (`RememberMe::checkRememberMeToken()`), so an expired remember-me cookie can never authenticate regardless of whether the row has been purged. Inline purging is therefore table maintenance only — not a security control — and paying a full-table scan on a fraction of interactive logins is no longer worthwhile. Schedule the [`php spark auth:purge`](#scheduled-maintenance-authpurge) command instead.

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

> **File**: `app/Config/AuthSecurity.php`

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

// HaveIBeenPwned API URL and HTTP timeouts (used when PwnedValidator is enabled).
// Short timeouts prevent registration / password-change flows from hanging when
// the API is slow or unreachable.
public string $pwnedPasswordsApiUrl         = 'https://api.pwnedpasswords.com/';
public float  $pwnedPasswordsConnectTimeout = 1.0;  // seconds
public float  $pwnedPasswordsTimeout        = 3.0;  // seconds

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

> **File**: `app/Config/AuthSecurity.php`

```php
// How long a password reset token remains valid
public int $passwordResetLifetime = HOUR; // Default: 1 hour
```

The reset flow is handled by `PasswordResetController`. Users request a reset link, receive it by email, and click it within this window. See [Controllers](05-controllers.md#passwordresetcontroller) for the full setup.

---

## Per-User Account Lockout

> **File**: `app/Config/AuthSecurity.php`

Independent of IP-based blocking, this locks an individual account after too many failed password attempts:

```php
// Max failed attempts before locking the account (0 = disabled)
public int $userMaxAttempts = 5;

// How long to keep the account locked (seconds)
public int $userLockoutTime = 3600; // 1 hour
```

When the lockout expires, the counter resets automatically on the next login attempt. See [Logging & Monitoring](07-logging.md#per-user-account-lockout) for details and admin unlock instructions.

> **Now also guards TOTP / backup-code verification.** The same per-user lockout (`UserLockoutManager`) is applied to two-factor verification: a wrong TOTP / backup code calls `recordFailedAttempt()`, an account already over `$userMaxAttempts` is blocked by `isLockedOut()`, and a correct code calls `resetOnSuccess()`. This closes the brute-force window on the 6-digit second factor. See [TOTP — Lockout & Anti-Replay](10-totp-2fa.md).

---

## Magic Links

> **File**: `app/Config/AuthSecurity.php`

```php
public bool $allowMagicLinkLogins = true;
public int  $magicLinkLifetime    = HOUR; // Token validity in seconds
```

---

## Access Tokens

> **File**: `app/Config/AuthSecurity.php`

```php
public bool $accessTokenEnabled = false;

// How long an unused access token remains valid
public int $unusedAccessTokenLifetime = YEAR;

// Force both API key AND session authentication (strict mode)
public bool $strictApiAndAuth = false;

// Minimum seconds between two consecutive `last_used_at` writes for the
// same access token. Prevents one DB UPDATE per request on high-traffic
// API tokens. Set to 0 to disable throttling (write on every request).
public int $tokenLastUsedThrottle = 60;

// Request headers for each authenticator
public array $authenticatorHeader = [
    'access_token' => 'X-API-KEY',
    'jwt'          => 'Authorization',
];
```

---

## JWT Refresh Tokens

> **File**: `app/Config/AuthSecurity.php`

When using `JwtController` for stateless JWT authentication, refresh tokens allow issuing new access tokens without re-entering credentials:

```php
// How long a JWT refresh token remains valid
public int $jwtRefreshLifetime = 30 * DAY; // Default: 30 days
```

Refresh tokens are stored in `auth_users_identities` (type: `jwt_refresh`) and are one-time use (rotated on each refresh). `JwtController` routes login / refresh / logout through `service('jwtTokenRepository')`; logout soft-revokes the refresh token. See [Authentication — JWT Refresh Tokens](03-authentication.md#jwt-refresh-tokens).

### Revoking Issued JWT Access Tokens

JWT access tokens are signed and self-contained, so they cannot be deleted server-side. To support "log out everywhere", each user carries a `users.token_version` counter (int, default `0`, added by migration `2026-05-08-000001`). `JwtController` mints the access-token payload as `{uid, tv}` where `tv` is the user's current `token_version`; the JWT authenticator's `check()` rejects any token whose embedded `tv` no longer matches the user's `token_version`, returning `lang('Auth.revokedToken')`. (Legacy scalar payloads — a bare user id — are still accepted, with the `tv` check skipped.)

Call `User::revokeIssuedTokens()` to bump `token_version` atomically and invalidate **all** outstanding access tokens at once. It is invoked automatically by `Bannable::ban()` and by `Services\PasswordChangeRecorder::record()` (password reset / change). See [Authentication — Revoking JWT Access Tokens](03-authentication.md#jwt-refresh-tokens).

---

## Logging & Monitoring

> **File**: `app/Config/AuthSecurity.php`

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
public string $limitMethod   = 'METHOD_NAME';
public int    $requestLimit  = 10;      // Max requests per window
public int    $timeLimit     = MINUTE;  // Window size in seconds
```

These values are the **global** defaults applied by the `rates` filter. They can be overridden per route — see [Per-Route Rate Limits](#per-route-rate-limits) below.

### Per-Route Rate Limits

The registered filter alias is **`rates`**. It honours per-route arguments that override the global limit / window for that route:

```php
// app/Config/Routes.php

// Use the global $requestLimit / $timeLimit
$routes->post('contact', 'Contact::send', ['filter' => 'rates']);

// Override the limit only (10 requests, global window)
$routes->post('login', 'Auth::login', ['filter' => 'rates:10']);

// Override limit AND window: 50 requests per minute
$routes->post('api/search', 'Api::search', ['filter' => 'rates:50,MINUTE']);

// The window may also be a raw number of seconds
$routes->post('api/heavy', 'Api::heavy', ['filter' => 'rates:5,90']);
```

`rates:<limit>,<period>` — `<period>` is either a number of **seconds** or a named unit. Recognised units (case-insensitive):

| Unit | Seconds |
|------|---------|
| `SECOND` | 1 |
| `MINUTE` | 60 |
| `HOUR` | 3600 |
| `DAY` | 86400 |
| `WEEK` | 604800 |

A configured endpoint DB row (runtime / admin override) still wins over both the global config and per-route arguments. Exceeding the limit returns HTTP `429` with `lang('Auth.throttled', ...)`.

---

## Authorization Cache

> **File**: `app/Config/AuthSecurity.php`

Avoid repeated database queries for group/permission checks in production:

```php
public bool $permissionCacheEnabled = false; // true = use CI4 cache
public int  $permissionCacheTTL     = 300;   // Cache lifetime in seconds
```

The cache is automatically invalidated when you call `addGroup()`, `removeGroup()`, `addPermission()`, or `removePermission()`. See [Authorization — Permission Cache](06-authorization.md#permission-cache).

### Gate → RBAC Fallback

```php
// When true, a Gate ability whose name looks like an RBAC permission
// (contains a scope, e.g. "users.edit") and has no registered closure or
// policy falls back to User::can(). This lets `gate:users.edit` and
// `permission:users.edit` share semantics.
// false = the Gate and RBAC systems stay fully independent.
public bool $gateFallbackToRbac = true;
```

The `gate` filter honours this setting, so `gate:users.edit` resolves a registered Gate ability if one exists and otherwise defers to the user's RBAC permissions. See [Authorization](06-authorization.md).

---

## Performance: Hot-Path Write Throttles

> **File**: `app/Config/AuthSecurity.php`

On every authenticated request the active authenticator records when the user was last seen. To avoid a `users`-table `UPDATE` (and an access-token `UPDATE`) on every single request, both writes are throttled:

```php
// If true, record the logged-in user's last_active timestamp on each request.
public bool $recordActiveDate = true;

// Minimum seconds between two consecutive `users.last_active` writes for the
// same user (applies only when $recordActiveDate is true). Avoids one
// users-table UPDATE per request on the authenticated hot path.
// 0 = write on every request (the legacy behaviour).
public int $activeDateThrottle = 60;

// Minimum seconds between two consecutive `last_used_at` writes for the same
// access token (see the Access Tokens section above).
// 0 = write on every request.
public int $tokenLastUsedThrottle = 60;
```

| Property | Default | Meaning |
|----------|---------|---------|
| `$recordActiveDate` | `true` | Record `users.last_active` for the authenticated user each request |
| `$activeDateThrottle` | `60` | Min seconds between `users.last_active` writes per user. `0` = every request |
| `$tokenLastUsedThrottle` | `60` | Min seconds between `last_used_at` writes per access token. `0` = every request |

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

    // WebAuthn / Passkeys (only used when AuthSecurity.$webauthnEnabled is true)
    'webauthn_setup'              => '\Daycry\Auth\Views\webauthn_setup',
    'webauthn_2fa_verify'         => '\Daycry\Auth\Views\webauthn_2fa_verify',
];
```

> The `webauthn_setup` view renders the passkey enrollment widget; `webauthn_2fa_verify` is shown by the `Webauthn2FA` login action. Both are overridable like any other view. See [WebAuthn / Passkeys](15-webauthn.md#frontend--javascript).

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

    // OAuth 2.0 / social login
    'oauth' => [
        ['get', 'oauth/login/(:segment)',    'OauthController::redirect/$1', 'oauth-login'],
        ['get', 'oauth/callback/(:segment)', 'OauthController::callback/$1', 'oauth-callback'],
        ['get', 'oauth/link/(:segment)',     'OauthController::link/$1',     'oauth-link'],
    ],

    // WebAuthn / Passkeys — registered ONLY when AuthSecurity.$webauthnEnabled is true
    'webauthn' => [
        ['post', 'webauthn/register/options',          'WebAuthnController::registerOptions'],
        ['post', 'webauthn/register/verify',           'WebAuthnController::registerVerify'],
        ['post', 'webauthn/login/options',             'WebAuthnController::loginOptions'],
        ['post', 'webauthn/login/verify',              'WebAuthnController::loginVerify'],
        ['post', 'webauthn/2fa/options',               'WebAuthnController::twoFactorOptions'],
        ['post', 'webauthn/credentials/(:segment)/delete', 'WebAuthnController::deleteCredential/$1'],
    ],
];
```

The `webauthn` route group is **auto-gated by `AuthSecurity::$webauthnEnabled`** (default `false`): `auth()->routes($routes)` registers these routes only when the flag is on, and `WebAuthnController` re-checks and returns `404` otherwise (defense in depth). See [WebAuthn / Passkeys — Routes & JSON Endpoints](15-webauthn.md#routes--json-endpoints) for the full contract.

The `oauth-link` route (`GET oauth/link/(:segment)`) is the explicit, user-initiated linking flow: it **requires an authenticated user**, stashes the current user (`oauth_link_user_id`), and the shared callback then links the provider to the *current* user — no e-mail merge and no verified-email requirement, because the user is acting deliberately. Linking a social account that is already bound to a different local user is refused with `lang('Auth.oauthAlreadyLinked')`. See [OAuth 2.0 & Social Login](09-oauth.md).

---

## Post-Authentication Actions

Run additional verification steps after login or registration:

```php
use Daycry\Auth\Authentication\Actions\Email2FA;
use Daycry\Auth\Authentication\Actions\EmailActivator;
use Daycry\Auth\Authentication\Actions\Totp2FA;
use Daycry\Auth\Authentication\Actions\Webauthn2FA;

public array $actions = [
    'register' => EmailActivator::class, // Require email confirmation on signup
    'login'    => Email2FA::class,        // Require email 2FA on login
    // 'login' => Totp2FA::class,         // Or: require TOTP on login (per-user)
    // 'login' => Webauthn2FA::class,     // Or: require a passkey on login (per-user)
];
```

| Action | What It Does |
|--------|-------------|
| `Email2FA` | Sends a 6-digit code to the user's email; required to complete login |
| `EmailActivator` | Sends an activation link; user must click before first login |
| `Totp2FA` | Requires a valid TOTP code (only for users who have enrolled) |
| `Webauthn2FA` | Requires a passkey assertion (only for users who have enrolled). Requires `AuthSecurity.$webauthnEnabled = true`. **Mutually exclusive** with `Totp2FA` — only one `login` action is supported. See [WebAuthn / Passkeys](15-webauthn.md) |

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

> **File**: `app/Config/AuthOAuth.php`

```php
public array $providers = [
    'google' => [
        'clientId'     => env('OAUTH_GOOGLE_CLIENT_ID'),
        'clientSecret' => env('OAUTH_GOOGLE_CLIENT_SECRET'),
        'redirectUri'  => 'https://yourapp.com/oauth/google/callback',
        'scopes'       => ['openid', 'email', 'profile'],
    ],
    'github' => [
        'clientId'     => env('OAUTH_GITHUB_CLIENT_ID'),
        'clientSecret' => env('OAUTH_GITHUB_CLIENT_SECRET'),
        'redirectUri'  => 'https://yourapp.com/oauth/github/callback',
        'scopes'       => ['user:email'],
        // 'allowUnverifiedEmailLink' => true, // see "Account Linking & Verified Email" below
    ],
    'azure' => [
        'clientId'     => env('OAUTH_AZURE_CLIENT_ID'),
        'clientSecret' => env('OAUTH_AZURE_CLIENT_SECRET'),
        'redirectUri'  => 'https://yourapp.com/oauth/azure/callback',
        'tenant'       => 'common',
        'scopes'       => ['openid', 'profile', 'email', 'offline_access', 'User.Read'],
        'fields'       => ['department', 'jobTitle'],  // Extra profile data from Graph API
    ],
    // Generic provider with custom profile resolver:
    // 'my_provider' => [
    //     'clientId'        => env('MY_PROVIDER_CLIENT_ID'),
    //     'clientSecret'    => env('MY_PROVIDER_CLIENT_SECRET'),
    //     'redirectUri'     => 'https://yourapp.com/oauth/my_provider/callback',
    //     'fields'          => ['role', 'team'],
    //     'fieldsEndpoint'  => 'https://api.example.com/userinfo',
    //     'profileResolver' => \App\OAuth\MyCustomResolver::class,
    // ],
];
```

### Provider Configuration Keys

| Key | Description |
|-----|-------------|
| `clientId`, `clientSecret`, `redirectUri` | Standard OAuth credentials (required) |
| `scopes` | OAuth scopes to request |
| `fields` | Extra profile fields to fetch after login |
| `fieldsEndpoint` | Custom API URL for profile fields (GenericProfileResolver) |
| `profileResolver` | Custom resolver class (must implement `ProfileResolverInterface`) |
| `tenant` | Azure-only: `'common'`, `'organizations'`, or tenant GUID |
| `allowUnverifiedEmailLink` | Opt-in (default unset / `false`): allow auto-linking to an existing local account even when the provider cannot assert the e-mail is verified |

### Account Linking & Verified Email

When a social account's e-mail matches an **existing** local (password) account, the identity is auto-linked **only if the provider asserts the e-mail is verified** (OIDC `email_verified` / Google `verified_email`). Providers that cannot assert verification (e.g. Facebook, GitHub) refuse the merge — `OauthManager` throws an `AuthenticationException` carrying `lang('Auth.oauthEmailUnverified')`.

To allow auto-linking for such a provider anyway, opt in per provider:

```php
'github' => [
    'clientId'                 => env('OAUTH_GITHUB_CLIENT_ID'),
    'clientSecret'             => env('OAUTH_GITHUB_CLIENT_SECRET'),
    'redirectUri'              => 'https://yourapp.com/oauth/github/callback',
    'allowUnverifiedEmailLink' => true, // trust this provider's e-mail
],
```

> **Security warning:** leave `allowUnverifiedEmailLink` unset unless you fully trust the provider. With it enabled, an attacker who registers a social account using a victim's e-mail address could be auto-linked into — and logged in as — the victim's local account.

For an explicit, user-initiated link (the authenticated user deliberately connects a provider, with no e-mail merge and no verified-email requirement), use the `oauth-link` route — see [Routes](#routes) and [OAuth 2.0 & Social Login](09-oauth.md).

See [OAuth 2.0 & Social Login](09-oauth.md) for full setup instructions, profile resolvers, OAuth events, and the `OAuthTokenRepository`.

---

## Sessions

> **File**: `app/Config/Auth.php`

```php
// Web session config (existing)
public array $sessionConfig = [
    'field'               => 'user',
    'allowRemembering'    => true,
    'rememberCookieName'  => 'remember',
    'rememberLength'      => 30 * DAY,
    'trackDeviceSessions' => true,
];

// Per-user concurrent session limit. When > 0, each new login terminates
// the oldest active sessions until at most this many remain.
// Requires sessionConfig.trackDeviceSessions = true.
// 0 = unlimited (default).
public int $maxConcurrentSessions = 0;
```

See [Device Sessions — Concurrent Limit](11-device-sessions.md#concurrent-session-limit) for behaviour and edge cases.

---

## Trusted Devices (2FA bypass)

> **File**: `app/Config/AuthSecurity.php`

```php
// When > 0, users can tick "Trust this device" during 2FA. Successful
// verifications mark the current device session as trusted for this many
// seconds; subsequent logins from the same device skip the 2FA challenge
// until the timestamp expires.
// 0 = feature disabled (always require 2FA when configured).
public int $trustedDeviceLifetime = 30 * DAY;
```

See [TOTP — Trust This Device](10-totp-2fa.md#trust-this-device) for the user flow.

---

## WebAuthn / Passkeys

> **File**: `app/Config/AuthSecurity.php`

Passwordless login and passkey-as-2FA are **opt-in per user behind a single global availability flag**. When `$webauthnEnabled` is `false` (the default) the feature does not exist — no `webauthn` routes are registered and every endpoint 404s.

```php
public bool    $webauthnEnabled                 = false;        // Global availability flag
public ?string $webauthnRelyingPartyId          = null;         // null → request host
public string  $webauthnRelyingPartyName        = 'Daycry Auth';
public array   $webauthnAllowedOrigins          = [];           // [] → derived from base_url()
public string  $webauthnUserVerification        = 'preferred';  // required | preferred | discouraged
public string  $webauthnResidentKey             = 'preferred';  // discoverable credential
public string  $webauthnAttestationConveyance   = 'none';       // none | indirect | direct
public ?string $webauthnAuthenticatorAttachment = null;         // null | platform | cross-platform
public int     $webauthnTimeout                 = 60000;        // ceremony timeout (ms)
public int     $webauthnChallengeTtl            = 120;          // challenge validity (seconds, single-use)
public int     $webauthnMaxCredentialsPerUser   = 10;           // per-user passkey cap
```

| Property | Default | Meaning |
|----------|---------|---------|
| `$webauthnEnabled` | `false` | Global availability flag. `false` ⇒ no routes, endpoints 404 |
| `$webauthnRelyingPartyId` | `null` | The `rpId` credentials are bound to (anti-phishing). `null` falls back to the request host |
| `$webauthnRelyingPartyName` | `'Daycry Auth'` | Display name shown in the browser passkey prompt |
| `$webauthnAllowedOrigins` | `[]` | Origins accepted during verification. `[]` derives from `base_url()`; add subdomains / native-app origins |
| `$webauthnUserVerification` | `'preferred'` | `required` \| `preferred` \| `discouraged`. Use `required` for passwordless |
| `$webauthnResidentKey` | `'preferred'` | Discoverable credential — needed for usernameless login |
| `$webauthnAttestationConveyance` | `'none'` | `none` \| `indirect` \| `direct`. `none` is best for privacy |
| `$webauthnAuthenticatorAttachment` | `null` | `null` (both) \| `platform` \| `cross-platform` |
| `$webauthnTimeout` | `60000` | Ceremony timeout in milliseconds |
| `$webauthnChallengeTtl` | `120` | Challenge validity in seconds (single-use) |
| `$webauthnMaxCredentialsPerUser` | `10` | Per-user passkey cap |

The dedicated table is `$tables['webauthn_credentials']` (`auth_webauthn_credentials`, see [Table Names](#table-names)); the auto-gated `webauthn` route group lives in [Routes](#routes); the `webauthn_setup` / `webauthn_2fa_verify` view keys live in [Views](#views); and `Webauthn2FA::class` is a [`$actions['login']`](#post-authentication-actions) option.

> Requires the `web-auth/webauthn-lib:^5.3` Composer dependency. See [WebAuthn / Passkeys](15-webauthn.md) for the full reference, enrollment / login ceremonies, and security invariants.

---

## Password Confirmation ("sudo mode")

> **File**: `app/Config/AuthSecurity.php`

The `password-confirm` filter forces an already-authenticated user to re-enter their password before reaching sensitive routes (changing email / 2FA settings, generating API tokens, account deletion). The global window is:

```php
// How long (seconds) a password confirmation stays valid before the
// `password-confirm` filter bounces the user to /auth/confirm-password.
// 0 = always require a fresh confirmation on every protected request.
// 3 * HOUR matches Laravel Fortify's default.
public int $passwordConfirmationLifetime = 3 * HOUR;
```

| Property | Default | Meaning |
|----------|---------|---------|
| `$passwordConfirmationLifetime` | `3 * HOUR` | Global max age (seconds) of a password confirmation accepted by the `password-confirm` filter. `0` = require a fresh confirmation every time |

### Per-Route Override

The filter accepts a per-route lifetime argument, `password-confirm:<seconds>`, which overrides the global value for that route — letting your most sensitive endpoints demand a fresher confirmation:

```php
// app/Config/Routes.php

// Standard sudo mode: honour the global $passwordConfirmationLifetime
$routes->post('account/email', 'Account::changeEmail', ['filter' => 'auth:session,password-confirm']);

// Stricter: require a confirmation no older than 60 seconds for this route
$routes->post('account/delete', 'Account::delete', ['filter' => 'auth:session,password-confirm:60']);
```

Apply `password-confirm` **after** an authentication filter (`session` / `auth`) on the same route — it intentionally leaves anonymous requests alone and is not a replacement for authentication.

---

## Compliance & Observability

> **File**: `app/Config/AuthSecurity.php`

These four settings are independent — enable only the ones you need. See [Audit & Compliance](13-audit-and-compliance.md) for the full reference.

```php
// Recheck the just-verified password against HIBP on every login.
// Sets force_reset = 1 on a hit. Adds 1 outbound HTTPS call per login.
public bool $recheckPwnedOnLogin = false;

// Compare each successful login's IP / User-Agent against the user's last
// 30 days of history. On anomalies fires the `suspicious-login` event +
// audit entry. Wire your own listener for the email/Slack/push delivery.
public bool $suspiciousLoginAlerts = false;

// Number of recent password hashes retained per user. The HistoryValidator
// rejects new passwords matching any retained hash.
// 0 = feature disabled.
public int $passwordHistorySize = 0;

// Force a password reset once `password_changed_at` is older than this.
// Apply the `password-age` filter on protected route groups to enforce.
// 0 = passwords never expire.
public int $passwordMaxAge = 0;
```

When `passwordHistorySize > 0`, also extend the validator chain:

```php
public array $passwordValidators = [
    \Daycry\Auth\Authentication\Passwords\CompositionValidator::class,
    \Daycry\Auth\Authentication\Passwords\NothingPersonalValidator::class,
    \Daycry\Auth\Authentication\Passwords\DictionaryValidator::class,
    \Daycry\Auth\Authentication\Passwords\HistoryValidator::class,
];
```

---

## Scheduled Maintenance (`auth:purge`)

Instead of the probabilistic on-login purge (`$rememberMePurgeChance`, now `0` by default), run the dedicated maintenance command on a schedule (cron / [daycry/jobs](https://github.com/daycry/jobs)):

```bash
# Purge expired remember-me tokens AND terminated device sessions older than 30 days
php spark auth:purge

# Override the device-session age cutoff (days)
php spark auth:purge --days 7
```

| Option | Default | Meaning |
|--------|---------|---------|
| `--days <n>` | `30` | Remove terminated `auth_device_sessions` rows older than `<n>` days. Values `<= 0` fall back to `30` |

`auth:purge` (command group: **Auth**) removes:

- **expired remember-me tokens** from `auth_remember_tokens` (all expired rows, regardless of `--days`), and
- **terminated device sessions** older than `--days` from `auth_device_sessions`.

This is table maintenance only — token expiry is enforced at validation time, so purging never affects which tokens can authenticate.

---

## Common Presets

### Web Application

```php
// app/Config/Auth.php
public bool   $allowRegistration    = true;
public string $defaultAuthenticator = 'session';
public array  $actions = ['register' => null, 'login' => null];

// app/Config/AuthSecurity.php
public bool $allowMagicLinkLogins = true;
public int  $userMaxAttempts      = 5;
public int  $userLockoutTime      = 1800; // 30 minutes
```

### API (Stateless)

```php
// app/Config/Auth.php
public string $defaultAuthenticator = 'jwt';
public array  $authenticationChain  = ['jwt', 'access_token'];

// app/Config/AuthSecurity.php
public bool $accessTokenEnabled    = true;
public int  $jwtRefreshLifetime    = 30 * DAY;
public int  $recordLoginAttempt    = 2;
public int  $tokenLastUsedThrottle = 60; // seconds
```

### High-Security (production)

```php
// app/Config/Auth.php
public array $actions                = ['login' => \Daycry\Auth\Authentication\Actions\Totp2FA::class];
public int   $maxConcurrentSessions  = 5;

// app/Config/AuthSecurity.php
public int  $minimumPasswordLength   = 12;
public bool $enableInvalidAttempts   = true;
public int  $maxAttempts             = 5;
public int  $timeBlocked             = 3600;
public int  $userMaxAttempts         = 3;
public int  $userLockoutTime         = 7200;  // 2 hours
public bool $permissionCacheEnabled  = true;
public int  $totpWindow              = 1;
public int  $trustedDeviceLifetime   = 30 * DAY;
public bool $suspiciousLoginAlerts   = true;
```

### Compliance (SOC 2 / ISO 27001)

```php
// app/Config/AuthSecurity.php
public int  $passwordHistorySize     = 5;        // no reuse of last 5
public int  $passwordMaxAge          = 90 * DAY; // rotation policy
public bool $recheckPwnedOnLogin     = true;     // ongoing HIBP check
public bool $suspiciousLoginAlerts   = true;
public bool $enableLogs              = true;
public int  $recordLoginAttempt      = AuthSecurity::RECORD_LOGIN_ATTEMPT_ALL;

public array $passwordValidators = [
    \Daycry\Auth\Authentication\Passwords\CompositionValidator::class,
    \Daycry\Auth\Authentication\Passwords\NothingPersonalValidator::class,
    \Daycry\Auth\Authentication\Passwords\DictionaryValidator::class,
    \Daycry\Auth\Authentication\Passwords\PwnedValidator::class,
    \Daycry\Auth\Authentication\Passwords\HistoryValidator::class,
];
```

Then enforce rotation on protected routes:

```php
// app/Config/Routes.php
$routes->group('app', ['filter' => 'auth:session,password-age'], static function ($routes) {
    $routes->get('/dashboard', 'Dashboard::index');
});
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
