# 🛡️ Security Filters

Filters are the cornerstone of security in Daycry Auth. This complete guide will teach you how to use each available filter.

## 📋 Filter Index

- [🔐 Authentication Filters](#-authentication-filters)
- [👥 Authorization Filters](#-authorization-filters)
- [🔗 Chain Filters](#-chain-filters)
- [📊 Control Filters](#-control-filters)
- [🛠️ Advanced Configuration](#️-advanced-configuration)
- [🎯 Practical Examples](#-practical-examples)

## 🔧 Initial Setup

### Filter aliases are auto-registered

You do **not** register the auth filter aliases yourself. They are contributed
automatically by `Daycry\Auth\Config\Registrar::Filters()` when the package is
installed, so they are available in `app/Config/Filters.php` out of the box:

| Alias | Class | Purpose |
|---|---|---|
| `auth` | `AuthFilter` | Authenticate (default authenticator, or one passed as an argument). |
| `basic-auth` | `BasicAuthFilter` | HTTP Basic authentication. |
| `chain` | `ChainAuthFilter` | Try each authenticator in `$authenticationChain` order. |
| `group` | `GroupFilter` | Require group membership (`group:admin,editor`). |
| `permission` | `PermissionFilter` | Require a permission (`permission:users.edit`). |
| `gate` | `GateFilter` | Authorize via a Gate ability / Policy. |
| `token-scope` | `TokenScopeFilter` | Require an access-token scope. |
| `rates` | `RatesFilter` | Per-IP/user rate limiting (`rates:50,MINUTE`). |
| `force-reset` | `ForcePasswordResetFilter` | Force a password change. |
| `password-age` | `PasswordAgeFilter` | Require a password younger than the configured max age. |
| `password-confirm` | `PasswordConfirmFilter` | Require recent password re-confirmation. |

> **Selecting an authenticator.** There is no per-authenticator alias such as
> `session`/`tokens`/`jwt`. Pass the authenticator name as an argument to the
> `auth` filter instead: `auth:session`, `auth:access_token`, or `auth:jwt`.
> With no argument, `auth` uses `Config\Auth::$defaultAuthenticator`.

To enable a filter globally, reference the alias (and optional arguments) in
`app/Config/Filters.php`:

```php
public array $globals = [
    'before' => [
        // 'rates', // Global rate limiting using the configured defaults
    ],
    'after' => [],
];
```

## 🔐 Authentication Filters

### 1. **Session Filter** (`session`)

Verifies that the user is authenticated via session.

#### Basic Usage

```php
// In routes
$routes->group('dashboard', ['filter' => 'auth:session'], function($routes) {
    $routes->get('/', 'Dashboard::index');
    $routes->get('profile', 'Dashboard::profile');
});

// In controller
class Dashboard extends BaseController
{
    protected $filters = ['auth:session'];
    
    public function index()
    {
        // User guaranteed as authenticated
        $user = auth()->user();
        return view('dashboard', ['user' => $user]);
    }
}
```

#### Advanced Configuration

```php
// Apply only to specific methods
protected $filters = [
    'session' => ['except' => ['public_method']]
];

// With additional parameters
$routes->get('admin', 'Admin::index', ['filter' => 'session:admin']);
```

### 2. **Access Token Filter** (`tokens`)

Verifies authentication via access tokens.

#### Usage in APIs

```php
// API Routes
$routes->group('api/v1', ['filter' => 'auth:access_token'], function($routes) {
    $routes->get('users', 'API\Users::index');
    $routes->post('users', 'API\Users::create');
    $routes->resource('posts', ['controller' => 'API\Posts']);
});

// In API controller
class UsersAPI extends ResourceController
{
    protected $filters = ['auth:access_token'];
    
    public function index()
    {
        // Token automatically validated
        $user = auth('access_token')->user();
        
        return $this->respond([
            'user' => $user,
            'data' => $this->model->findAll()
        ]);
    }
}
```

#### Required Headers

```javascript
// In AJAX/API requests
fetch('/api/v1/users', {
    headers: {
        'X-API-KEY': 'your-access-token-here',
        'Content-Type': 'application/json'
    }
});
```

### 3. **JWT Filter** (`jwt`)

Verifies JWT tokens in the Authorization header.

#### Configuration

```php
// API with JWT
$routes->group('api/jwt', ['filter' => 'auth:jwt'], function($routes) {
    $routes->get('profile', 'API\Profile::show');
    $routes->put('profile', 'API\Profile::update');
});
```

#### JWT Headers

```javascript
// Request with JWT
fetch('/api/jwt/profile', {
    headers: {
        'Authorization': 'Bearer your-jwt-token-here',
        'Content-Type': 'application/json'
    }
});
```

### 4. **Basic Auth Filter** (`basic-auth`)

HTTP Basic authentication (RFC 7617). Reads `Authorization: Basic base64(user:pass)`, verifies the credentials against the user provider, and on success logs the user in via the `session` authenticator. Designed for **machine-to-machine endpoints** (cron jobs, health checks, webhooks, internal tooling) where managing tokens or sessions is overkill.

#### Configuration

```php
// app/Config/Auth.php — realm shown by browsers / cached by clients.
public string $basicAuthRealm = 'My App API';
```

#### Routes

```php
// Persist the auth into the session for the rest of the request lifecycle.
$routes->group('cron', ['filter' => 'basic-auth'], function ($routes) {
    $routes->get('purge-old-tokens', 'Maintenance::purgeOldTokens');
});

// Stateless: re-verify credentials on every request, do not write to session.
$routes->group('api/internal', ['filter' => 'basic-auth:once'], function ($routes) {
    $routes->get('health', 'Health::index');
});
```

#### Behaviour

| Scenario | Response |
|----------|----------|
| Missing `Authorization` header | `401 Unauthorized` + `WWW-Authenticate: Basic realm="..."` |
| Wrong scheme (e.g. `Bearer`) | `401` + challenge header |
| Malformed base64 / no colon | `401` + challenge header |
| Unknown user / wrong password | `401` + challenge header (no info leak) |
| Valid credentials | Logs the user in, request proceeds |

The identifier is matched as **email** when it parses as a valid email address (`filter_var(..., FILTER_VALIDATE_EMAIL)`), otherwise as **username**.

#### Use cases

- Cron / scheduled jobs that hit an internal endpoint:
  ```bash
  curl -u maintenance@example.com:secret https://app.example/cron/purge-old-tokens
  ```
- Health checks from monitoring systems (Prometheus blackbox, Pingdom).
- Webhooks from third-party services that only support Basic auth.
- Local CLI tooling against a deployed API.

> **Don't use Basic auth on user-facing endpoints.** Browsers prompt for credentials with native (ugly) modals and there is no logout flow. For interactive auth use the `session` filter; for APIs use `tokens` / `jwt`.

### 5. **Chain Filter** (`chain`)

Tries multiple authentication methods in order.

#### Configuration

```php
// In Auth.php
public array $authenticationChain = [
    'session',      // First session
    'access_token', // Then access token
    'jwt',          // Finally JWT
];

// Usage in routes
$routes->group('hybrid', ['filter' => 'chain'], function($routes) {
    $routes->get('data', 'Hybrid::getData'); // Accepts any auth method
});
```

#### Practical Example

```php
class HybridController extends BaseController
{
    protected $filters = ['chain'];
    
    public function getData()
    {
        // Works with session, token or JWT
        $user = auth()->user();
        
        // Detect authentication method used
        $authMethod = 'unknown';
        if (auth('session')->loggedIn()) $authMethod = 'session';
        elseif (auth('access_token')->loggedIn()) $authMethod = 'token';
        elseif (auth('jwt')->loggedIn()) $authMethod = 'jwt';
        
        return $this->respond([
            'user' => $user,
            'auth_method' => $authMethod,
            'data' => 'sensitive data here'
        ]);
    }
}
```

## 👥 Authorization Filters

### 1. **Group Filter** (`group`)

Verifies that the user belongs to one or more groups.

#### Basic Usage

```php
// Single group
$routes->group('admin', ['filter' => 'auth:session,group:admin'], function($routes) {
    $routes->get('/', 'Admin::dashboard');
    $routes->get('users', 'Admin::users');
});

// Multiple groups (OR - any of them)
$routes->get('moderator-panel', 'Moderator::panel', [
    'filter' => 'auth:session,group:admin,moderator'
]);

// In controller
class AdminController extends BaseController
{
    protected $filters = [
        'session',
        'group:admin,super-admin' // Any of these groups
    ];
}
```

#### Hierarchical Groups

```php
// Configure hierarchy in Database Seeder
$groups = [
    'super-admin' => ['permissions' => ['*']],
    'admin'       => ['permissions' => ['admin.*']],
    'moderator'   => ['permissions' => ['content.*']],
    'user'        => ['permissions' => ['user.profile']]
];

// Usage with hierarchy
$routes->group('management', ['filter' => 'auth:session,group:admin'], function($routes) {
    $routes->get('/', 'Management::index');
    
    // Only super-admin
    $routes->group('system', ['filter' => 'group:super-admin'], function($routes) {
        $routes->get('settings', 'Management::systemSettings');
    });
});
```

### 2. **Permission Filter** (`permission`)

Verifies specific granular permissions.

#### Basic Usage

```php
// Specific permission
$routes->get('admin/users/edit/(:num)', 'Admin\Users::edit/$1', [
    'filter' => 'auth:session,permission:users.edit'
]);

// Multiple permissions (AND - must have all)
$routes->delete('admin/users/(:num)', 'Admin\Users::delete/$1', [
    'filter' => 'auth:session,permission:users.delete,users.manage'
]);

// In controller
class UserManagement extends BaseController
{
    protected $filters = [
        'session',
        'permission:users.view' => ['except' => ['index']],
        'permission:users.edit' => ['only' => ['edit', 'update']],
        'permission:users.delete' => ['only' => ['delete']],
    ];
}
```

#### Granular Permission System

```php
// Example permission structure
$permissions = [
    // User management
    'users.view',
    'users.create',
    'users.edit',
    'users.delete',
    'users.manage',
    
    // Content management
    'content.view',
    'content.create',
    'content.edit',
    'content.publish',
    'content.delete',
    
    // System settings
    'system.settings',
    'system.backups',
    'system.logs',
];

// Usage in specific routes
$routes->group('admin/content', ['filter' => 'auth:session'], function($routes) {
    $routes->get('/', 'Content::index', ['filter' => 'permission:content.view']);
    $routes->get('create', 'Content::create', ['filter' => 'permission:content.create']);
    $routes->post('store', 'Content::store', ['filter' => 'permission:content.create']);
    $routes->get('(:num)/edit', 'Content::edit/$1', ['filter' => 'permission:content.edit']);
    $routes->put('(:num)', 'Content::update/$1', ['filter' => 'permission:content.edit']);
    $routes->delete('(:num)', 'Content::destroy/$1', ['filter' => 'permission:content.delete']);
});
```

### 3. **Gate Filter** (`gate`)

Authorizes a request against a Gate ability — a closure rule registered with
`Gate::define()` or a class-based policy registered with `Gate::policy()`. Apply it on
routes that map cleanly to a single ability **without** a resource argument
(`gate:dashboard.view`, `gate:billing.access`). For abilities that need a resource
instance, call the Gate API inside the controller (`Gate::authorize('post.update', $post)`).

```php
// Single ability
$routes->get('admin', 'Admin::index', ['filter' => 'auth:session,gate:admin.access']);

// Multiple abilities — AND-ed (every ability must allow)
$routes->get('billing', 'Billing::index', ['filter' => 'auth:session,gate:billing.view,billing.manage']);
```

#### Gate → RBAC fallback

The `gate` filter honors the **Gate → RBAC fallback**. When an ability looks like an RBAC
permission — it contains a scope separator, e.g. `users.edit` — and there is **no**
registered closure or policy for it, the Gate defers to the authenticated user's RBAC
permissions via `User::can()`. This lets `gate:users.edit` and `permission:users.edit`
share the same semantics.

```php
// app/Config/AuthSecurity.php
public bool $gateFallbackToRbac = true; // default
```

| Setting | Default | Meaning |
|---------|---------|---------|
| `AuthSecurity::$gateFallbackToRbac` | `true` | A scoped ability (`users.edit`) with no registered closure/policy falls back to `User::can()`. Set `false` to keep the Gate and RBAC systems fully independent (such abilities then simply deny). |

The fallback only applies to abilities that contain a `.` scope separator and only when a
`User` is authenticated. Explicit closures and policies always take precedence over the
RBAC fallback.

#### Failure response

`gate` extends `AbstractAuthFilter`, so a denied request reuses
`buildDeniedResponse()` and redirects to `Auth::permissionDeniedRedirect()` (or returns a
`403` JSON body for API requests).

### 4. **Token Scope Filter** (`token-scope`)

Validates that the **access token** used to authenticate the request grants every scope listed in the filter argument. Only meaningful after a token-based authenticator has run (`tokens`, `jwt`, or `chain`).

```php
// Single scope — token must grant `posts.read`
$routes->get('api/posts', 'Posts::index', [
    'filter' => 'auth:access_token,token-scope:posts.read',
]);

// Multiple scopes — AND-ed (token must grant BOTH)
$routes->post('api/posts', 'Posts::create', [
    'filter' => 'auth:access_token,token-scope:posts.read,posts.write',
]);
```

#### How scopes are matched

Scopes live on the `AccessToken` entity (the `extra` column, mapped via the `scopes` datamap). The filter calls `AccessToken::can($scope)` for each requested scope:

| Stored scopes | Filter argument | Result |
|---------------|-----------------|--------|
| `['posts.read']` | `posts.read` | ✅ allow |
| `['posts.read']` | `posts.write` | ❌ deny |
| `['posts.read', 'posts.write']` | `posts.read,posts.write` | ✅ allow |
| `['*']` (wildcard) | anything | ✅ allow |
| `[]` | any | ❌ deny |

#### Generating scoped tokens

```php
$token = $user->generateAccessToken('mobile-app', ['posts.read', 'posts.write']);
echo $token->raw_token; // give to the client once
```

#### Failure response

`token-scope` reuses `AbstractAuthFilter::buildDeniedResponse()`:

- API requests (`Accept: application/json`) → `403 Forbidden` JSON.
- Web requests → redirect to `Auth::permissionDeniedRedirect()` with a flash error.

> **Tip**: prefer `token-scope` over `permission:` for API tokens — it scopes the *token*, not the user. A user with `posts.write` permission can still hold a read-only token.

## 🔗 Chain Filters

### Advanced Chain Configuration

```php
// In Auth.php - order matters
public array $authenticationChain = [
    'session',      // Fastest, for web users
    'access_token', // For external APIs
    'jwt',          // For SPAs and mobile
];

// Custom chain per route
$routes->group('api/mobile', [
    'filter' => 'chain:jwt,access_token' // Only JWT and tokens
], function($routes) {
    $routes->resource('posts');
});
```

### Hybrid API Example

```php
class HybridAPI extends ResourceController
{
    protected $filters = ['chain'];
    
    public function before(RequestInterface $request, $arguments = null)
    {
        // Specific logic based on auth method
        $response = parent::before($request, $arguments);
        
        if (auth('jwt')->loggedIn()) {
            // JWT-specific configuration
            $this->format = 'json';
        } elseif (auth('session')->loggedIn()) {
            // Web session configuration
            $this->format = 'html';
        }
        
        return $response;
    }
}
```

## 📊 Control Filters

### 1. **Auth Rates Filter** (`rates`)

Request rate control per user/IP. The **registered alias is `rates`** (there is no
`auth-rates` alias).

#### Global Configuration

```php
// In app/Config/Filters.php
public array $globals = [
    'before' => [
        'rates', // Apply the global defaults to all routes
    ],
];

// In app/Config/AuthSecurity.php
public string $limitMethod = 'METHOD_NAME'; // IP_ADDRESS | USER | METHOD_NAME | ROUTED_URL
public int $requestLimit   = 10;            // requests allowed per window
public int $timeLimit      = MINUTE;        // window length (seconds)
```

| Setting | Default | Meaning |
|---------|---------|---------|
| `AuthSecurity::$limitMethod` | `'METHOD_NAME'` | Bucket key: `IP_ADDRESS`, `USER`, `METHOD_NAME`, or `ROUTED_URL`. |
| `AuthSecurity::$requestLimit` | `10` | Requests allowed per window (used when no per-route/endpoint override). |
| `AuthSecurity::$timeLimit` | `MINUTE` | Window length in seconds. |

#### Per-route arguments: `rates:<limit>,<period>`

The filter now honors per-route arguments that **override the global limit/time for
that route only**:

```php
// API-specific rate limiting: 50 requests per minute on this group.
$routes->group('api', ['filter' => 'rates:50,MINUTE'], function($routes) {
    $routes->resource('users');
});

// In a controller with custom rate limiting.
class APIController extends ResourceController
{
    protected $filters = [
        'tokens',
        'rates:200,HOUR', // 200 requests per hour
    ];
}
```

- The **first** argument is the request limit (a number).
- The **second** argument is the period. It may be a **number of seconds**, or one of
  the named units below (case-insensitive; plural and short forms are accepted):

| Period argument | Resolves to |
|-----------------|-------------|
| `SECOND` / `SECONDS` / `SEC` | 1 s |
| `MINUTE` / `MINUTES` / `MIN` | 60 s |
| `HOUR` / `HOURS` | 3 600 s |
| `DAY` / `DAYS` | 86 400 s |
| `WEEK` / `WEEKS` | 604 800 s |
| `90` (numeric) | 90 s |
| unrecognised | leaves the resolved window unchanged |

```php
// 30 requests per 5 minutes (period given in seconds):
$routes->get('reports', 'Reports::index', ['filter' => 'rates:30,300']);

// 30 requests per minute (named unit resolves to 60 seconds):
$routes->get('reports', 'Reports::index', ['filter' => 'rates:30,MINUTE']);
```

Only the limit may be supplied (`rates:25`), in which case the period falls back to the
global `timeLimit`.

#### Override precedence

A configured **endpoint database row** still wins over both the global defaults and the
per-route argument. The resolution order applied by `RatesFilter::before()` is:

1. Global `AuthSecurity::$requestLimit` / `$timeLimit`.
2. Per-route argument (`rates:<limit>,<period>`) — overrides the globals for that route.
3. A matching `Endpoint` row (runtime/admin override) — overrides everything above.

A user whose `ignore_rates` flag is set bypasses throttling entirely. When the limit is
exceeded the filter returns a `429` response with the `Auth.throttled` message.

### 2. **Force Password Reset Filter** (`force-reset`)

Forces password change when necessary.

```php
// Apply after login
$routes->group('secure', [
    'filter' => 'auth:session,force-reset'
], function($routes) {
    $routes->get('dashboard', 'Dashboard::index');
});

// In database, mark user for reset
auth()->user()->forcePasswordReset();
```

### 3. **Password Age Filter** (`password-age`)

Forces a password reset once the user's `password_changed_at` is older than `AuthSecurity::$passwordMaxAge` seconds. Apply after authentication.

```php
// app/Config/AuthSecurity.php
public int $passwordMaxAge = 90 * DAY; // 90-day rotation

// app/Config/Routes.php
$routes->group('app', ['filter' => 'auth:session,password-age'], function ($routes) {
    $routes->get('dashboard', 'Dashboard::index');
});
```

**Behaviour:**

1. Runs after authentication. If the user is not logged in, the filter no-ops.
2. If `password_changed_at` is `null` (older accounts before the migration), the filter leaves the user alone — grandfathered.
3. If the timestamp is older than `passwordMaxAge`, the filter sets `force_reset = 1` on the user's email_password identity and redirects to `Auth::forcePasswordResetRedirect()` with `Auth.passwordExpired`.

> See [Audit & Compliance — Password Rotation](13-audit-and-compliance.md#password-rotation-policy) for the full lifecycle.

### 4. **Password Confirm Filter** (`password-confirm`)

Forces the user to re-enter their password before performing sensitive actions ("sudo mode"). Inspired by Laravel Fortify's `password.confirm` middleware. Use it on routes that change critical state — disabling 2FA, generating API tokens, unlinking OAuth providers, deleting the account.

```php
// app/Config/AuthSecurity.php
public int $passwordConfirmationLifetime = 3 * HOUR; // 0 = always re-confirm
```

#### Routes

```php
// 1. Wire the confirmation form once (must be reachable WITHOUT
//    password-confirm to break the chicken-and-egg loop):
$routes->group('auth', ['filter' => 'auth:session', 'namespace' => 'Daycry\Auth\Controllers'], static function ($routes) {
    $routes->get('confirm-password',  'UserSecurityController::confirmPasswordView',   ['as' => 'password-confirm-show']);
    $routes->post('confirm-password', 'UserSecurityController::confirmPasswordAction', ['as' => 'password-confirm']);
});

// 2. Apply the filter on routes that need fresh confirmation:
$routes->group('account/security', ['filter' => 'auth:session,password-confirm'], static function ($routes) {
    $routes->post('totp/disable',       'Account::disableTotp');
    $routes->post('email/change',       'Account::changeEmail');
    $routes->post('tokens/generate',    'Account::generateApiToken');
    $routes->delete('account',          'Account::deleteAccount');
});
```

#### Per-route TTL: `password-confirm:<seconds>`

The filter honors a per-route lifetime argument. `password-confirm:<seconds>` requires a
password confirmation **no older than `<seconds>`** for that route, regardless of the
global `AuthSecurity::$passwordConfirmationLifetime`. Use it to demand a fresher
confirmation on your most sensitive routes ("sudo mode"):

```php
// The global window may be 3 hours, but these two routes demand a confirmation
// that is at most 60 seconds old.
$routes->post('account/delete',  'Account::deleteAccount', ['filter' => 'auth:session,password-confirm:60']);
$routes->post('account/disable-2fa', 'Account::disableTotp', ['filter' => 'auth:session,password-confirm:60']);
```

The argument must be numeric; non-numeric arguments are ignored and the global
`passwordConfirmationLifetime` applies. A value of `0` (global or per-route) means every
protected request requires a fresh confirmation.

#### Behaviour

1. The filter no-ops for anonymous requests — pair it with `session`/`auth` which handle the login redirect.
2. Reads `password_confirmed_at` from the session.
3. Resolves the lifetime: a numeric per-route argument overrides the global `passwordConfirmationLifetime`.
4. If the timestamp is missing or older than the resolved lifetime, stashes the current URL (`passwordConfirmIntendedUrl` tempdata) and redirects to `password-confirm-show` with the `Auth.passwordConfirmRequired` error.
5. After the user submits the form successfully, `UserSecurityController::confirmPasswordAction` stamps a fresh timestamp, writes an `EVENT_PASSWORD_CONFIRMED` audit entry, and redirects back to the originally intended URL.

#### Settings reference

| Setting / argument | Default | Effect |
|--------------------|---------|--------|
| `passwordConfirmationLifetime = 0` | — | Every protected request requires a fresh confirmation. |
| `passwordConfirmationLifetime = HOUR` | — | One confirmation valid for 1 h. |
| `passwordConfirmationLifetime = 3 * HOUR` | default | Matches Laravel Fortify. |
| `password-confirm:<seconds>` (route argument) | unset | Per-route override of the lifetime, in seconds, for that route only. |

> The view rendered by `confirmPasswordView()` is `Daycry\Auth\Views\confirm_password.php`. Override via `setting('Auth.views')['confirm_password']`.

### Request logging (not a filter)

There is **no `auth-request` filter alias**. Logging and monitoring of
authenticated requests happens automatically for controllers that extend
`Daycry\Auth\Controllers\BaseAuthController`: end-of-request bookkeeping runs in
`BaseControllerTrait::finalizeRequest()` (idempotent and exception-safe — it can
also be invoked from an `after` filter for deterministic timing). What gets
logged is controlled by the logging settings — see the
[Logging guide](07-logging.md).

## 🛠️ Advanced Configuration

### Conditional Filters

```php
class ConditionalController extends BaseController
{
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);
        
        // Apply filters conditionally
        if ($this->request->isAJAX()) {
            $this->filters['tokens'] = ['only' => ['api_method']];
        } else {
            $this->filters['session'] = ['only' => ['web_method']];
        }
    }
}
```

### Custom Filters

```php
<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class CustomAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Custom authentication logic
        if (!auth()->loggedIn()) {
            if ($request->isAJAX()) {
                return service('response')->setStatusCode(401)
                    ->setJSON(['error' => 'Unauthorized']);
            }
            
            return redirect()->to('/login');
        }
        
        // Additional validations
        $user = auth()->user();
        if (!$user->active) {
            return redirect()->to('/account-suspended');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Logic after response
        if (auth()->loggedIn()) {
            // Update last activity
            auth()->user()->updateLastActive();
        }
    }
}
```

### Filter Combination

```php
// Multiple filters in specific order
$routes->group('secure-api', [
    'filter' => 'rates,chain,permission:api.access'
], function($routes) {
    $routes->resource('sensitive-data');
});

// In controller with multiple filters
class SecureController extends BaseController
{
    protected $filters = [
        'session',                     // Must be authenticated
        'group:admin,moderator',       // Must be admin or moderator
        'permission:admin.access',     // Must have specific permission
        'force-reset',                 // Check if password reset needed
        'rates:50,HOUR',         // Maximum 50 requests per hour
    ];
}
```

## 🎯 Practical Examples

### 1. **Complete Admin Panel**

```php
// Main panel - requires authentication
$routes->group('admin', [
    'namespace' => 'App\Controllers\Admin',
    'filter' => 'auth:session'
], function($routes) {
    
    // Dashboard - basic admin access
    $routes->get('/', 'Dashboard::index', ['filter' => 'group:admin']);
    
    // User management - specific permissions
    $routes->group('users', ['filter' => 'group:admin'], function($routes) {
        $routes->get('/', 'Users::index', ['filter' => 'permission:users.view']);
        $routes->get('create', 'Users::create', ['filter' => 'permission:users.create']);
        $routes->post('/', 'Users::store', ['filter' => 'permission:users.create']);
        $routes->get('(:num)/edit', 'Users::edit/$1', ['filter' => 'permission:users.edit']);
        $routes->put('(:num)', 'Users::update/$1', ['filter' => 'permission:users.edit']);
        $routes->delete('(:num)', 'Users::destroy/$1', ['filter' => 'permission:users.delete']);
    });
    
    // System settings - super-admin only
    $routes->group('system', ['filter' => 'group:super-admin'], function($routes) {
        $routes->get('settings', 'System::settings');
        $routes->post('settings', 'System::updateSettings');
        $routes->get('backups', 'System::backups', ['filter' => 'permission:system.backups']);
    });
});
```

### 2. **RESTful API with Multiple Auth Methods**

```php
// API that accepts tokens, JWT or session
$routes->group('api/v1', [
    'namespace' => 'App\Controllers\API',
    'filter' => 'rates:1000,HOUR' // Global rate limiting
], function($routes) {
    
    // Public endpoints (no auth)
    $routes->get('status', 'Status::index');
    $routes->post('auth/login', 'Auth::login');
    
    // Authenticated endpoints (any method)
    $routes->group('', ['filter' => 'chain'], function($routes) {
        $routes->get('profile', 'Users::profile');
        $routes->put('profile', 'Users::updateProfile');
        
        // Posts - granular permissions
        $routes->get('posts', 'Posts::index', ['filter' => 'permission:posts.view']);
        $routes->post('posts', 'Posts::create', ['filter' => 'permission:posts.create']);
        $routes->put('posts/(:num)', 'Posts::update/$1', ['filter' => 'permission:posts.edit']);
        $routes->delete('posts/(:num)', 'Posts::delete/$1', ['filter' => 'permission:posts.delete']);
    });
    
    // Admin endpoints - admins only with strict rate limiting
    $routes->group('admin', [
        'filter' => 'chain,group:admin,rates:100,HOUR'
    ], function($routes) {
        $routes->get('stats', 'Admin::stats');
        $routes->get('users', 'Admin::users', ['filter' => 'permission:admin.users']);
    });
});
```

### 3. **Application with Different Access Levels**

```php
class MultiLevelController extends BaseController
{
    protected $filters = [
        'session' => ['except' => ['public']],
        'group:subscriber' => ['only' => ['basic_content']],
        'group:premium' => ['only' => ['premium_content']],
        'group:admin' => ['only' => ['admin_content']],
        'permission:content.moderate' => ['only' => ['moderate']],
    ];

    public function public()
    {
        // Public content
        return view('public_content');
    }

    public function basic_content()
    {
        // Only for users with 'subscriber' group or higher
        return view('basic_content');
    }

    public function premium_content()
    {
        // Only for premium users
        return view('premium_content');
    }

    public function admin_content()
    {
        // Only for administrators
        return view('admin_content');
    }

    public function moderate()
    {
        // Only users with specific moderation permission
        return view('moderation_panel');
    }
}
```

## 🚨 Error Handling

### Custom Responses for Filters

```php
// In app/Config/Filters.php
public array $globals = [
    'before' => [
        'rates' => ['except' => ['api/public/*']],
    ],
];

// Customize responses in events
// In app/Config/Events.php
Events::on('auth.fail', function($result) {
    if (service('request')->isAJAX()) {
        return service('response')->setStatusCode(401)
            ->setJSON([
                'error' => 'Authentication failed',
                'message' => $result->reason(),
                'redirect' => site_url('login')
            ]);
    }
});
```

## 📈 Monitoring and Debugging

### Filter Debugging

```php
// In development, enable filter logging
// In .env
CI_ENVIRONMENT = development

// Filters will automatically log their execution
// Check in writable/logs/
```

### Filter Testing

```php
// In tests
class FilterTest extends FeatureTestCase
{
    public function testAdminFilterRequiresAdminGroup()
    {
        $user = fake(UserModel::class);
        $user->addGroup('user'); // Normal group
        
        $result = $this->actingAs($user)
                      ->get('/admin');
                      
        $result->assertRedirect(); // Should redirect
        $result->assertSessionHas('error');
    }

    public function testAPITokenFilter()
    {
        $token = 'valid-token';
        
        $result = $this->withHeaders(['X-API-KEY' => $token])
                      ->get('/api/users');
                      
        $result->assertOK();
    }
}
```

---

🔗 **Next**: [Controllers](05-controllers.md) - Learn to create robust controllers with the new architecture
