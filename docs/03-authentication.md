# 🔐 Authentication — Complete Guide

Daycry Auth supports multiple authentication methods. This guide explains how to use each one, including all security features added in recent versions.

## 📋 Index

- [The `auth()` Facade](#the-auth-facade)
- [Session Authenticator](#session-authenticator)
- [Remember Me — Expiry & Theft Detection](#remember-me--expiry--theft-detection)
- [Per-User Account Lockout](#per-user-account-lockout)
- [Compromised-Password Recheck on Login](#compromised-password-recheck-on-login)
- [Login-Attempt Log & Token Fingerprints](#login-attempt-log--token-fingerprints)
- [Access Token Authenticator](#access-token-authenticator)
- [JWT Authenticator](#jwt-authenticator)
- [JWT Access-Token Revocation](#jwt-access-token-revocation)
- [JWT Refresh Tokens](#jwt-refresh-tokens)
- [Magic Link Authentication](#magic-link-authentication)
- [WebAuthn / Passkeys](#webauthn--passkeys)
- [Guest Authenticator](#guest-authenticator)
- [Password Reset](#password-reset)
- [Force Password Reset](#force-password-reset)
- [Pre-Authentication Events](#pre-authentication-events)
- [Switching Between Authenticators](#switching-between-authenticators)
- [Custom Authenticators](#custom-authenticators)
- [Why HTTP Digest Auth is not supported](#why-http-digest-auth-is-not-supported)

---

## The `auth()` Facade

`auth($alias)` returns the `Daycry\Auth\Auth` facade, which proxies most calls to the **active authenticator** via `__call()`. Two things are important to know:

### Unknown methods throw `BadMethodCallException`

If the resolved authenticator does not implement the method you call, the facade now throws `\BadMethodCallException` (previously it silently returned `null`):

```php
use BadMethodCallException;

try {
    // `remember()` only exists on the Session authenticator
    auth('jwt')->remember(true);
} catch (BadMethodCallException $e) {
    // Method Daycry\Auth\Authentication\Authenticators\JWT::remember()
    // does not exist on the "jwt" authenticator.
}
```

This matters for security: a Session-only method called through a stateless authenticator (or a simple typo) used to be misread as a falsy "not logged in" result. It now surfaces immediately.

### Common vs. Session-only methods

**Common methods** are defined by `AuthenticatorInterface` and are available on **every** authenticator (`session`, `access_token`, `jwt`, `guest`):

| Method | Signature |
|---|---|
| `attempt` | `attempt(array $credentials): Result` |
| `check` | `check(array $credentials): Result` |
| `getLogCredentials` | `getLogCredentials(array $credentials): mixed` |
| `getUser` | `getUser(): ?User` |
| `loggedIn` | `loggedIn(): bool` |
| `login` | `login(User $user, bool $actions = true): void` |
| `loginById` | `loginById(int\|string $userId): void` |
| `logout` | `logout(): void` |
| `recordActiveDate` | `recordActiveDate(): void` |

**Session-only methods** — calling these through a stateless authenticator (`access_token`, `jwt`, `guest`) throws `BadMethodCallException`:

| Method | Signature |
|---|---|
| `checkAction` | `checkAction(UserIdentity $identity, string $token): bool` |
| `completeLogin` | `completeLogin(User $user): void` |
| `forget` | `forget(?User $user = null): void` |
| `getAction` | `getAction(): ?ActionInterface` |
| `getPendingMessage` | `getPendingMessage(): string` |
| `getPendingUser` | `getPendingUser(): ?User` |
| `hasAction` | `hasAction(int\|string\|null $userId = null): bool` |
| `isAnonymous` | `isAnonymous(): bool` |
| `isPending` | `isPending(): bool` |
| `remember` | `remember(bool $shouldRemember = true): self` |
| `startLogin` | `startLogin(User $user): void` |
| `startUpAction` | `startUpAction(string $type, User $user): bool` |

> `auth()->user()` and `auth()->id()` are defined directly on the facade (not proxied) and are safe to call on any authenticator — they return `null` rather than throwing when nobody is logged in.

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
auth()->remember(true)->login($user); // With "remember me"

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

When a user logs in with remember-me enabled, a long-lived cookie is set. On future visits, even after the session expires, the user is automatically recognized and logged back in. Enable it with the fluent `remember()` method **before** calling `attempt()` (or `login()`):

```php
$remember = (bool) $this->request->getPost('remember');
auth()->remember($remember)->attempt($credentials);
```

> Note: `attempt()` takes only `$credentials` and `login()` only `($user, $actions)` — there is no `$remember` parameter on either. Persistent login is controlled exclusively by `remember()`. Expired remember-me cookies are rejected at validation time, so they cannot log a user back in (see the next section).

---

## Remember Me — Expiry & Theft Detection

The remember-me service (`Daycry\Auth\Authentication\Services\RememberMe`) follows the [Paragonie split-token design](https://paragonie.com/blog/2015/04/secure-authentication-php-with-long-term-persistence): the cookie value is `selector:validator`. The `selector` indexes the database row; the `validator` is compared with `hash_equals()` against a stored SHA-256 hash.

### Expiry is enforced at validation time

`checkRememberMeToken()` rejects an expired token **regardless of whether a purge has run**:

```php
if (Time::parse($token->expires)->getTimestamp() <= Time::now()->getTimestamp()) {
    return false; // expired cookie can no longer authenticate
}
```

This is why `AuthSecurity::$rememberMePurgeChance` now defaults to `0` (was `20`): the probabilistic on-login purge is **table maintenance only** and is never the security control. Schedule cleanup with `php spark auth:purge` instead of relying on the inline purge.

### Theft detection

If a request presents a cookie whose `selector` matches a stored row but whose `validator` does **not**, the cookie was most likely stolen or guessed. The service treats this as a likely theft (`handlePossibleTheft()`):

1. **Purges every remember-me token for that user** — `RememberModel::purgeRememberTokensByUserId($userId)`. Both the legitimate user and the attacker are logged out of persistent login and must re-authenticate.
2. **Writes an audit entry** — `AuditLogger::record(AuditLogger::EVENT_SUSPICIOUS_LOGIN, ...)`, where `EVENT_SUSPICIOUS_LOGIN = 'login.suspicious'`. The metadata records `reason => 'remember_me_validator_mismatch'` and the offending `selector`.
3. **Fires an event** — `Events::trigger('remember-me-theft', $userId, $selector)` so your app can alert the user or trigger further hardening.

```php
// app/Config/Events.php
use CodeIgniter\Events\Events;

Events::on('remember-me-theft', static function (int $userId, string $selector): void {
    // e.g. email the user: "We detected an invalid persistent-login token and
    //      signed you out of all remembered devices as a precaution."
    log_message('critical', "Remember-me theft suspected for user {$userId} (selector {$selector})");
});
```

> See [Audit & Compliance](13-audit-and-compliance.md) for how `login.suspicious` appears in the audit log.

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

### Concurrency safety

`recordFailedAttempt()` uses an atomic SQL increment expression rather than a read-modify-write pattern, so concurrent failed-login attempts cannot race past `userMaxAttempts` before the lockout fires.

---

## Compromised-Password Recheck on Login

Optional opt-in: after a successful password verification, re-test the live password against [HaveIBeenPwned](https://haveibeenpwned.com/) and flag the account for forced reset if it appears in a known breach corpus.

```php
// app/Config/AuthSecurity.php
public bool $recheckPwnedOnLogin = true;

// HIBP timeouts
public float $pwnedPasswordsConnectTimeout = 1.0;
public float $pwnedPasswordsTimeout        = 3.0;
```

### What happens on a hit

1. Login proceeds and the session is created normally — the user is **not** kicked out mid-flow.
2. The user's `email_password` identity is marked `force_reset = 1`.
3. On their next request the `force-reset` filter (or your equivalent) bounces them through the force-password-reset flow.

### What happens on HIBP failure

The recheck is wrapped in `try/catch`. Timeouts, network errors, and 5xx responses are logged at `warning` level and login proceeds normally — **the recheck never blocks login**.

> See [Audit & Compliance — Compromised-Password Recheck](13-audit-and-compliance.md#compromised-password-recheck-on-login) for the full reference.

---

## Login-Attempt Log & Token Fingerprints

Every login attempt is recorded in `auth_logins`. What gets stored in the `identifier` column depends on the authenticator, and **no raw bearer credential is ever written to the log**:

| Authenticator | `auth_logins.identifier` contains |
|---|---|
| Session | The email / username identifier (not a secret) |
| Access Token | `hash('sha256', $token)` — a non-reversible fingerprint |
| JWT | `hash('sha256', $token)` — a non-reversible fingerprint |

For the stateless authenticators the value comes from `getLogCredentials()`:

```php
// AccessToken::getLogCredentials() and JWT::getLogCredentials()
return $token === '' ? '' : hash('sha256', $token);
```

- For **access tokens** the fingerprint is the same SHA-256 the token is stored under, so a log entry can still be correlated to the issued token row without being usable to authenticate.
- For **JWTs** the full signed token is a usable bearer credential until it expires; only its SHA-256 fingerprint is logged.

Session login still logs the plaintext email/username identifier because that is not a secret — it identifies *who* attempted to log in, which is exactly what the login log is for.

---

## Access Token Authenticator

**Best for**: REST APIs, mobile apps, machine-to-machine integrations.

### Enable Access Tokens

```php
// app/Config/Auth.php
public bool $accessTokenEnabled = true;

// Unused token lifetime
public int $unusedAccessTokenLifetime = YEAR;

// Throttle `last_used_at` writes — at most one DB UPDATE per token per N
// seconds even when the same token is used for thousands of requests.
// 0 = always write (the legacy behaviour).
public int $tokenLastUsedThrottle = 60;

// Header name (in app/Config/Auth.php)
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

```text
GET /api/users?token=your_raw_token_here
```

### Protect Routes with the Token Filter

```php
// app/Config/Routes.php
$routes->group('api', ['filter' => 'auth:access_token'], static function ($routes) {
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
use Daycry\Auth\Models\AccessTokenRepository;
use Daycry\Auth\Models\UserIdentityModel;

$repo = new AccessTokenRepository(model(UserIdentityModel::class));

// By raw token
$repo->softRevokeAccessToken($user, $rawToken);

// All tokens for a user (e.g. on password change)
$repo->softRevokeAllAccessTokens($user);
```

Each soft-revocation writes an `EVENT_TOKEN_REVOKED` entry to the audit log automatically. Soft-revoked tokens are excluded from all lookups via the `revoked_at IS NULL` filter, but remain in the database for audit trail.

### Scope Enforcement

Personal access tokens carry a list of scopes (stored in the `extra` column, mapped via the `scopes` datamap). The `token-scope:` filter validates them on a per-route basis:

```php
$routes->get('api/posts',  'Posts::index',  ['filter' => 'auth:access_token,token-scope:posts.read']);
$routes->post('api/posts', 'Posts::create', ['filter' => 'auth:access_token,token-scope:posts.read,posts.write']);
```

The `*` wildcard scope satisfies any check. See [Filters — Token Scope Filter](04-filters.md#3-token-scope-filter-token-scope) for details.

### Admin CLI

Bulk-revoke all tokens for a user (useful on password compromise / staff offboarding):

```bash
php spark auth:tokens revoke -e alice@example.com
php spark auth:tokens revoke -e alice@example.com --type=access_token
```

See [CLI — `auth:tokens`](14-cli-commands.md#auth-tokens) for the full reference.

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
$routes->group('api', ['filter' => 'auth:jwt'], static function ($routes) {
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

## JWT Access-Token Revocation

JWT access tokens are stateless and self-contained, so they cannot be deleted from a server-side store. To support a "log out everywhere" / instant-invalidation capability, the package versions all of a user's access tokens with a counter.

### `users.token_version`

A new integer column `token_version` (default `0`) is added by migration `2026-05-08-000001_add_jwt_token_version_to_users`.

`JwtController` mints the access-token payload as `{uid, tv}`, where `tv` is the user's current `token_version` at mint time:

```php
// JwtController::buildTokenResponse()
$accessToken = $adapter->encode([
    'uid' => $user->id,
    'tv'  => (int) ($user->token_version ?? 0),
]);
```

On every request, the JWT authenticator's `check()` reads the embedded `tv` and compares it to the user's current `token_version`. If they differ, the token is rejected with `lang('Auth.revokedToken')` ("The token has been revoked."):

```php
// JWT::check()
if ($tokenVersion !== null && (int) ($user->token_version ?? 0) !== $tokenVersion) {
    return new Result([
        'success' => false,
        'reason'  => lang('Auth.revokedToken'),
    ]);
}
```

> **Legacy tokens** whose payload is a bare scalar user id (no `tv`) are still accepted — the version check is skipped when `tv` is absent. This keeps tokens minted before the upgrade valid until they expire.

### `revokeIssuedTokens()` — invalidate everything outstanding

`User::revokeIssuedTokens()` atomically bumps the user's `token_version`. Because every previously-issued access token carries the *old* `tv`, they all fail the next `check()` immediately:

```php
$user = auth()->user();
$user->revokeIssuedTokens(); // "log out everywhere" — every existing JWT access token is now invalid
```

It is also called **automatically** in two places, so you usually don't need to call it yourself:

| Trigger | Where |
|---|---|
| Banning a user | `Bannable::ban()` |
| Password reset / change | `Services\PasswordChangeRecorder::record()` |

This means banning a user or having them change their password invalidates all of their live JWT access tokens, not just future ones.

> **Scope note**: `revokeIssuedTokens()` invalidates JWT **access tokens** via the `tv` check. Refresh tokens are revoked separately (soft-revocation on logout — see below). Personal access tokens have their own revocation flow (see [Soft Revocation](#soft-revocation)).

---

## JWT Refresh Tokens

The built-in `JwtController` provides stateless login with automatic refresh token rotation. Each refresh token is one-time use — a new one is issued with every refresh. Login, refresh, and logout all route their refresh-token persistence through `service('jwtTokenRepository')`; logout soft-revokes the refresh token (`JwtTokenRepository::softRevokeRefreshToken()`) rather than hard-deleting it.

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

## WebAuthn / Passkeys

**Best for**: Phishing-resistant, passwordless sign-in (Face ID / Touch ID / Windows Hello / security keys), and a strong opt-in second factor.

Passkeys authenticate with public-key cryptography instead of a shared secret. The private key never leaves the device; the server stores only the public key, so a database breach exposes nothing replayable. Credentials are bound to your domain, making passkeys **phishing-resistant by design**. The feature is **opt-in per user** behind the global `AuthSecurity::$webauthnEnabled` flag (default `false`), and supports two integration models:

- **Passwordless login** (usernameless / discoverable) — the user signs in with just a passkey. A passkey verified with user verification is already multi-factor (possession + inherence), so a successful assertion **completes the session directly** via `auth()->login($user, false)` and does **not** re-run the `login` Action pipeline.
- **Passkey as a second factor** — presented *after* the password through the post-auth Action system, using `Webauthn2FA` (mutually exclusive with `Totp2FA`, since only one `login` action is supported).

The ceremonies run over JSON endpoints exposed by `WebAuthnController` (registered only when the flag is on). See [WebAuthn / Passkeys](15-webauthn.md) for configuration, enrollment, login/2FA flows, the `HasWebAuthn` trait, and the full set of security invariants.

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
$routes->group('dashboard', ['filter' => 'auth:session,force-reset'], static function ($routes) {
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
$routes->group('dashboard', ['filter' => 'auth:session'], ...);

// API clients use JWT
$routes->group('api', ['filter' => 'auth:jwt'], ...);

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

## Why HTTP Digest Auth is not supported

`daycry/auth` implements **Basic Auth** ([`BasicAuthFilter`](../src/Filters/BasicAuthFilter.php)) but **not HTTP Digest Auth** (RFC 2617 / RFC 7616). This is a deliberate architectural decision, not an oversight. The reasons:

### 1. Incompatible with bcrypt / argon2

Digest requires the server to compute `HA1 = MD5(username:realm:password)` on every challenge. That requires the server to know the password (or a value derived from it).

This package stores passwords with **bcrypt** (or argon2), a one-way hash. Once hashed, the original password is unrecoverable, so HA1 cannot be computed at validation time.

Supporting Digest would force one of:

- **Storing HA1 alongside the bcrypt hash** in a new column. HA1 is `MD5` without salt — effectively plaintext-equivalent for offline attacks. A database leak would expose Digest-authenticatable credentials, even though the bcrypt hash protects the real password.
- **Tying HA1 to the configured `realm`**. Changing `Auth.restRealm` later would invalidate every stored HA1, forcing all users to re-enter their plaintext password.
- **Breaking backward compatibility**: existing users could not use Digest until they logged in with their plaintext password again so the server could compute their HA1.

### 2. Effectively deprecated

[RFC 7616](https://www.rfc-editor.org/rfc/rfc7616) (2015) explicitly marks MD5-Digest as inadequate for production and adds SHA-256, but the ecosystem has not migrated. Modern HTTP clients, browsers and API consumers do not request Digest by default.

### 3. No security gain over Basic + TLS

- **With TLS**: the `Authorization: Basic ...` header is encrypted in transit, so Basic is safe. Digest adds nothing.
- **Without TLS**: neither scheme is safe. Digest is still vulnerable to downgrade attacks, does not protect the request body, and leaks the username in the clear.

If you are running this library in production you already need TLS — and once you have TLS, Basic + Bearer/JWT covers the same ground without the architectural cost.

### 4. Modern alternatives are already in the package

| Need | Use |
|---|---|
| Server-to-server API auth | [Access Token (Bearer)](#access-token-authenticator) or [JWT](#jwt-authenticator) |
| Web login with sessions | [Session](#session-authenticator) |
| Social login | [OAuth 2.0](09-oauth.md) |
| Passwordless | [Magic Link](#magic-link-authentication) or [WebAuthn / Passkeys](15-webauthn.md) |
| Phishing-resistant MFA | [WebAuthn / Passkeys](15-webauthn.md) |
| HTTP-level basic auth | [`BasicAuthFilter`](../src/Filters/BasicAuthFilter.php) over TLS |

### If your use case really requires Digest

Rare cases (legacy embedded clients, specific compliance requirements, etc.):

1. **Prefer changing the client.** Migrate to Bearer / JWT / Basic+TLS if you can.
2. **If you cannot**, build a separate package on top of `daycry/auth`:
   - Migration: new table `users_digest_ha1 (user_id, realm, ha1, created_at)`.
   - Endpoint: `POST /digest/enable` that accepts the user's plaintext password, computes HA1 server-side, and stores it. Per-user opt-in only.
   - Filter: a custom `DigestAuthFilter` that resolves credentials via that table, with a server-side nonce store (e.g. `Services::cache()`, TTL ~5 min) and replay protection via the `nc` (nonce-count) parameter.
   - Authenticator: extend [`Base`](../src/Authentication/Authenticators/Base.php) with a `check()` that compares the request `response` field against `MD5(HA1:nonce:nc:cnonce:qop:HA2)`.
   - Support **at minimum** `qop=auth` and SHA-256 in addition to MD5.
   - Document the database leakage risk (HA1 is not a strong hash).

The decision **not to merge this into `daycry/auth` core** is intentional — keeping HA1 out of the canonical user table is a security boundary worth preserving.

---

🔗 **See also**:
- [Filters](04-filters.md) — Protect routes with authentication filters
- [Controllers](05-controllers.md) — Password reset and force reset controllers
- [TOTP 2FA](10-totp-2fa.md) — Time-based one-time passwords
- [WebAuthn / Passkeys](15-webauthn.md) — Passwordless login and passkey 2FA
- [Device Sessions](11-device-sessions.md) — Track active logins
- [Logging & Monitoring](07-logging.md) — Events and logs
