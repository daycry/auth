# 🔐 Authentication — Complete Guide

Daycry Auth supports multiple authentication methods. This guide explains how to use each one, including all security features added in recent versions.

## 📋 Index

- [Session Authenticator](#session-authenticator)
- [Per-User Account Lockout](#per-user-account-lockout)
- [Access Token Authenticator](#access-token-authenticator)
- [JWT Authenticator](#jwt-authenticator)
- [JWT Refresh Tokens](#jwt-refresh-tokens)
- [Magic Link Authentication](#magic-link-authentication)
- [Guest Authenticator](#guest-authenticator)
- [Password Reset](#password-reset)
- [Force Password Reset](#force-password-reset)
- [Pre-Authentication Events](#pre-authentication-events)
- [Switching Between Authenticators](#switching-between-authenticators)
- [Custom Authenticators](#custom-authenticators)

---

## Session Authenticator

**Best for**: Traditional web applications using server-side sessions.

### Basic Usage

```php
// Login attempt
$credentials = [
    'email'    => $this->request->getPost('email'),
    'password' => $this->request->getPost('password'),
];

$result = auth('session')->attempt($credentials);

if ($result->isOK()) {
    return redirect()->to('/dashboard');
}

return redirect()->back()->with('error', $result->reason());
```

### Helper Functions

```php
// Check if authenticated
if (auth()->loggedIn()) { ... }

// Get current user
$user = auth()->user();

// Programmatic login (skip credential check)
auth()->login($user);
auth()->login($user, remember: true); // With "remember me"

// Login by user ID
auth()->loginById(42);

// Check credentials without logging in
$result = auth()->check($credentials);
if ($result->isOK()) {
    $user = $result->extraInfo(); // The matched User object
}

// Logout
auth()->logout();
```

### Session Configuration

```php
// app/Config/Auth.php
public array $sessionConfig = [
    'field'               => 'user',       // Key stored in $_SESSION
    'allowRemembering'    => true,         // Enable "remember me" cookie
    'rememberCookieName'  => 'remember',
    'rememberLength'      => 30 * DAY,
    'trackDeviceSessions' => false,        // See Device Sessions guide
];
```

### Remember Me

When a user logs in with `remember: true`, a long-lived cookie is set. On future visits, even after the session expires, the user is automatically recognized and logged back in.

```php
$remember = (bool) $this->request->getPost('remember');
auth()->attempt($credentials, $remember);
```

---

## Per-User Account Lockout

After a configurable number of failed password attempts, the user's account is temporarily locked. This is independent of IP-based blocking.

### Configuration

```php
// app/Config/Auth.php
public int $userMaxAttempts = 5;     // Attempts before lockout (0 = disabled)
public int $userLockoutTime  = 3600; // Lockout duration in seconds
```

### How It Works

1. Wrong password → `failed_login_count` increments
2. Reaches `userMaxAttempts` → `locked_until` is set on the user record
3. Any further login attempt before `locked_until` → error with minutes remaining
4. Lockout expires → counter resets automatically on next attempt
5. Successful login → counter always resets to 0

### Unlocking Manually (Admin)

```php
model(\Daycry\Auth\Models\UserModel::class)->update($userId, [
    'failed_login_count' => 0,
    'locked_until'       => null,
]);
```

---

## Access Token Authenticator

**Best for**: REST APIs, mobile apps, machine-to-machine integrations.

### Enable Access Tokens

```php
// app/Config/Auth.php
public bool $accessTokenEnabled = true;

// Unused token lifetime
public int $unusedAccessTokenLifetime = YEAR;

// Header name
public array $authenticatorHeader = [
    'access_token' => 'X-API-KEY',
];
```

### Generate a Token

```php
// Typically done in a login controller after verifying credentials
$token = auth()->user()->generateAccessToken('mobile-app');

return $this->response->setJSON([
    'token'      => $token->raw_token, // Return the raw token ONCE — it's never stored in plaintext
    'token_type' => 'Bearer',
]);
```

### Use the Token in Requests

```http
GET /api/users HTTP/1.1
X-API-KEY: your_raw_token_here
```

Or via query string:

```
GET /api/users?token=your_raw_token_here
```

### Protect Routes with the Token Filter

```php
// app/Config/Routes.php
$routes->group('api', ['filter' => 'tokens'], static function ($routes) {
    $routes->get('users', 'API\UsersController::index');
    $routes->get('profile', 'API\ProfileController::show');
});
```

### Token Management

```php
$user = auth()->user();

// Generate with name and optional scopes
$token = $user->generateAccessToken('dashboard', ['posts.read', 'posts.write']);

// List all tokens
$tokens = $user->accessTokens();

// Revoke a specific token
$user->revokeAccessToken($token);

// Revoke all tokens (e.g., on password change)
$user->revokeAllAccessTokens();

// Check permission scope on the current token
$currentToken = auth('access_token')->user()->currentAccessToken();
if ($currentToken->can('posts.write')) { ... }
```

### Soft Revocation

Tokens can be soft-revoked (marked with a `revoked_at` timestamp) without being deleted:

```php
use Daycry\Auth\Models\UserIdentityModel;

model(UserIdentityModel::class)->revokeIdentityById($tokenId);
```

Soft-revoked tokens are excluded from all lookups automatically.

---

## JWT Authenticator

**Best for**: Stateless APIs, microservices, single-page applications.

### Configuration

JWT configuration is managed by the `daycry/jwt` library. In `app/Config/Auth.php`:

```php
use Daycry\Auth\Authentication\JWT\Adapters\DaycryJWTAdapter;

public string $jwtAdapter = DaycryJWTAdapter::class;
```

Configure the JWT library in `app/Config/JWT.php`:

```php
public string $algorithmUsed    = 'HS256';
public string $secretKey        = 'your-secret-key'; // Use env('JWT_SECRET') in production
public string $issuer           = 'your-app';
public string $audience         = 'your-users';
public int    $timeToLive       = HOUR;      // Access token TTL
public int    $allowedClockSkew = 60;        // Tolerance in seconds
```

### Use the JWT Filter

```php
// app/Config/Routes.php
$routes->group('api', ['filter' => 'jwt'], static function ($routes) {
    $routes->get('profile', 'API\ProfileController::show');
    $routes->get('posts',   'API\PostsController::index');
});
```

### Authorization Header

```http
GET /api/profile HTTP/1.1
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

---

## JWT Refresh Tokens

The built-in `JwtController` provides stateless login with automatic refresh token rotation. Each refresh token is one-time use — a new one is issued with every refresh.

### Register the JWT Routes

```php
// app/Config/Routes.php
$routes->post('auth/jwt/login',   'Daycry\Auth\Controllers\JwtController::login',   ['as' => 'jwt-login']);
$routes->post('auth/jwt/refresh', 'Daycry\Auth\Controllers\JwtController::refresh', ['as' => 'jwt-refresh']);
$routes->post('auth/jwt/logout',  'Daycry\Auth\Controllers\JwtController::logout',  ['as' => 'jwt-logout']);
```

Or use the routes from `app/Config/Auth.php`:

```php
// Already included in the 'jwt' route group
```

### Configure Refresh Token Lifetime

```php
// app/Config/Auth.php
public int $jwtRefreshLifetime = 30 * DAY; // Refresh token validity
```

### Login

```http
POST /auth/jwt/login
Content-Type: application/x-www-form-urlencoded

email=user@example.com&password=secret
```

**Response:**

```json
{
    "access_token":  "eyJ0eXAiOiJKV1Qi...",
    "refresh_token": "a3f8c2d1e4b7...",
    "user_id":       42,
    "token_type":    "Bearer"
}
```

### Refresh an Expired Access Token

```http
POST /auth/jwt/refresh
Content-Type: application/x-www-form-urlencoded

user_id=42&refresh_token=a3f8c2d1e4b7...
```

**Response:**

```json
{
    "access_token":  "eyJ0eXAiOiJKV1Qi...",
    "refresh_token": "newrefreshtoken...",
    "user_id":       42,
    "token_type":    "Bearer"
}
```

> The old refresh token is immediately revoked. Store the new one.

### Logout (Revoke Refresh Token)

```http
POST /auth/jwt/logout
Content-Type: application/x-www-form-urlencoded

user_id=42&refresh_token=a3f8c2d1e4b7...
```

### Client-Side Flow (JavaScript example)

```javascript
// On 401: try refreshing before giving up
async function apiFetch(url, options = {}) {
    let response = await fetch(url, {
        ...options,
        headers: {
            ...options.headers,
            Authorization: `Bearer ${localStorage.getItem('access_token')}`,
        },
    });

    if (response.status === 401) {
        // Try to refresh
        const refreshResponse = await fetch('/auth/jwt/refresh', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                user_id:       localStorage.getItem('user_id'),
                refresh_token: localStorage.getItem('refresh_token'),
            }),
        });

        if (refreshResponse.ok) {
            const data = await refreshResponse.json();
            localStorage.setItem('access_token',  data.access_token);
            localStorage.setItem('refresh_token', data.refresh_token);

            // Retry original request with new token
            response = await apiFetch(url, options);
        } else {
            // Redirect to login
            window.location.href = '/login';
        }
    }

    return response;
}
```

---

## Magic Link Authentication

**Best for**: Passwordless login, reducing friction for new users.

### Enable Magic Links

```php
// app/Config/Auth.php
public bool $allowMagicLinkLogins = true;
public int  $magicLinkLifetime    = HOUR;
```

### Complete Flow

1. User enters email on `/login/magic-link`
2. System generates a one-time token and emails a link
3. User clicks the link within 1 hour
4. Token is verified → session created → user redirected

### Routes

```php
$routes->get('login/magic-link',        'MagicLinkController::loginView',  ['as' => 'magic-link']);
$routes->post('login/magic-link',       'MagicLinkController::loginAction');
$routes->get('login/verify-magic-link', 'MagicLinkController::verify',     ['as' => 'verify-magic-link']);
```

---

## Guest Authenticator

**Best for**: Routes that work for both authenticated users and anonymous visitors.

```php
// Returns null if not logged in — never fails or redirects
$user = auth('guest')->user();

if ($user !== null) {
    echo "Hello, {$user->email}!";
} else {
    echo "Hello, guest!";
}
```

---

## Password Reset

Users who have forgotten their password can request a reset link by email.

### How It Works

1. User visits `/password-reset` and enters their email
2. If the email matches an account, a secure token is emailed
3. User clicks the link → sees a "Set new password" form
4. On success, `Events::trigger('passwordReset', $user)` fires

### Routes (already in Config/Auth.php)

```php
// GET  /password-reset          → requestView()
// POST /password-reset          → requestAction()
// GET  /password-reset/message  → messageView()
// GET  /password-reset/{token}  → resetView()
// POST /password-reset/{token}  → resetAction()
```

### Configuration

```php
// app/Config/Auth.php
public int $passwordResetLifetime = HOUR; // Token validity
```

### Listen for Reset Completion

```php
// app/Config/Events.php
Events::on('passwordReset', static function (object $user): void {
    // Revoke all access tokens for security
    $user->revokeAllAccessTokens();
    log_message('notice', "Password reset for {$user->email}");
});
```

---

## Force Password Reset

Administrators can flag user accounts to require a password change on next login.

### Flag a User for Password Reset

```php
// Force a specific user to reset their password
$user = model(\Daycry\Auth\Models\UserModel::class)->find($userId);

model(\Daycry\Auth\Models\UserIdentityModel::class)
    ->forceMultiplePasswordReset([$userId]);

// Force ALL users (e.g., after a security breach)
model(\Daycry\Auth\Models\UserIdentityModel::class)
    ->forceGlobalPasswordReset();
```

### How It Works

Once flagged, the `ForcePasswordResetFilter` intercepts any request from that user and redirects them to `/force-reset`. After a successful password change, the flag is cleared and they proceed normally.

### Apply the Filter

```php
// app/Config/Filters.php
public array $aliases = [
    'force-reset' => \Daycry\Auth\Filters\ForcePasswordResetFilter::class,
];

// app/Config/Routes.php — apply to all authenticated routes
$routes->group('dashboard', ['filter' => 'session,force-reset'], static function ($routes) {
    $routes->get('/', 'Dashboard::index');
});
```

---

## Pre-Authentication Events

Two events fire **before** any database check occurs, letting you inspect or log the incoming data:

```php
// app/Config/Events.php
use CodeIgniter\Events\Events;

// Fires before credentials are checked during login
Events::on('pre-login', static function (array $credentials): void {
    log_message('debug', 'Login attempt: ' . ($credentials['email'] ?? '?'));
});

// Fires before registration is processed
Events::on('pre-register', static function (array $postData): void {
    log_message('debug', 'Registration attempt: ' . ($postData['email'] ?? '?'));
});
```

See [Logging & Monitoring](07-logging.md) for more event examples.

---

## Switching Between Authenticators

### Multiple Authenticators in One App

```php
// Web users use sessions
$routes->group('dashboard', ['filter' => 'session'], ...);

// API clients use JWT
$routes->group('api', ['filter' => 'jwt'], ...);

// Try all methods in order (chain filter)
$routes->group('flexible', ['filter' => 'chain'], ...);
```

### Chain Authenticator

The `chain` filter tries authenticators in order (configured in `$authenticationChain`) and stops at the first successful one:

```php
// app/Config/Auth.php
public array $authenticationChain = ['session', 'access_token', 'jwt'];
```

### Runtime Detection

```php
if ($this->request->hasHeader('Authorization')) {
    $user = auth('jwt')->user();
} elseif ($this->request->hasHeader('X-API-KEY')) {
    $user = auth('access_token')->user();
} else {
    $user = auth('session')->user();
}
```

---

## Custom Authenticators

Implement `AuthenticatorInterface` and use the `instance()` factory method for dependency injection:

```php
<?php

namespace App\Authentication\Authenticators;

use Daycry\Auth\Authentication\Authenticators\Base;
use Daycry\Auth\Interfaces\AuthenticatorInterface;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\LoginModel;

class ApiKeyAuthenticator extends Base implements AuthenticatorInterface
{
    public const ID_TYPE_APIKEY = 'apikey';

    public static function instance(UserModel $provider): static
    {
        return new static(
            $provider,
            service('request'),
            model(UserIdentityModel::class),
            model(LoginModel::class),
        );
    }

    // ... implement abstract methods from Base
}
```

Register it:

```php
// app/Config/Auth.php
public array $authenticators = [
    // ... existing
    'apikey' => \App\Authentication\Authenticators\ApiKeyAuthenticator::class,
];
```

---

🔗 **See also**:
- [Filters](04-filters.md) — Protect routes with authentication filters
- [Controllers](05-controllers.md) — Password reset and force reset controllers
- [TOTP 2FA](10-totp-2fa.md) — Time-based one-time passwords
- [Device Sessions](11-device-sessions.md) — Track active logins
- [Logging & Monitoring](07-logging.md) — Events and logs
