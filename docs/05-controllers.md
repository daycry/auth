# 🎮 Controllers — Complete Guide

This guide covers all controllers included with Daycry Auth, including the new password reset, force reset, JWT, and user security controllers.

## 📋 Index

- [BaseAuthController](#baseauthcontroller)
- [LoginController](#logincontroller)
- [RegisterController](#registercontroller)
- [ActionController](#actioncontroller)
- [MagicLinkController](#magiclinkcontroller)
- [PasswordResetController](#passwordresetcontroller)
- [ForcePasswordResetController](#forcepasswordresetcontroller)
- [JwtController](#jwtcontroller)
- [OauthController](#oauthcontroller)
- [WebAuthnController](#webauthncontroller)
- [UserSecurityController](#usersecuritycontroller)
- [Creating Custom Controllers](#creating-custom-controllers)
- [Best Practices](#best-practices)

---

## BaseAuthController

All auth controllers extend `BaseAuthController`, which provides a set of reusable helper methods.

```php
abstract class BaseAuthController extends BaseController implements AuthController
{
    use BaseControllerTrait; // Wires exception handler, attempt handler, request logger
    use Viewable;            // Provides $this->view()

    // Every subclass must declare its validation rules
    abstract protected function getValidationRules(): array;
}
```

### Available Helper Methods

| Method | Purpose |
|--------|---------|
| `getTokenArray()` | Returns CSRF token for forms |
| `redirectIfLoggedIn()` | Redirects if already authenticated |
| `getSessionAuthenticator()` | Returns the session authenticator |
| `hasPostAuthAction()` | Checks if a 2FA/activation action is pending |
| `redirectToAuthAction()` | Redirects to the action controller |
| `extractLoginCredentials()` | Reads login fields from POST |
| `shouldRememberUser()` | Checks the "remember me" checkbox |
| `validateRequest(array $data, array $rules)` | Runs CI4 validation |
| `handleValidationError(?string $route)` | Redirects back with errors |
| `handleSuccess(string $url, ?string $message)` | Redirects with success message |
| `handleError(string $route, string $error)` | Redirects back with error |
| `handleAuthResult(Result $result, string $failureRoute)` | Handles an auth result cleanly |

### End-of-request bookkeeping: `finalizeRequest()`

`BaseControllerTrait` performs request logging, invalid-attempt handling, and validator reset at the end of each request. This work lives in a single public method, `finalizeRequest()`:

```php
public function finalizeRequest(): void
```

| Property | Behaviour |
|----------|-----------|
| **Idempotent** | Guarded by an internal `$requestFinalized` flag — calling it more than once does nothing after the first call. |
| **Never throws** | The body is wrapped in `try/catch (Throwable)`; any failure is written via `log_message('error', ...)` instead of propagating. This means a logging failure can never become an uncatchable shutdown fatal. |
| **Default trigger** | `__destruct()` calls `finalizeRequest()` automatically, so the default behaviour is unchanged. |

Because it is public and idempotent, you may also invoke it from an `after` filter when you need **deterministic timing** rather than relying on object destruction. If you hold a reference to the active controller, call it directly:

```php
// Inside a controller method or wherever you have the instance:
$this->finalizeRequest(); // runs the bookkeeping now; the later __destruct() call is a no-op
```

The later automatic call from `__destruct()` is harmless because the guard flag makes it a no-op.

---

## LoginController

Handles traditional email + password login and logout.

**Routes** (from `Config/Auth.php`):
```
GET  /login  → loginView()
POST /login  → loginAction()
GET  /logout → logoutAction()
```

### Extending LoginController

```php
<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Daycry\Auth\Controllers\LoginController as BaseLoginController;

class LoginController extends BaseLoginController
{
    public function loginView(): ResponseInterface
    {
        if ($redirect = $this->redirectIfLoggedIn()) {
            return $redirect;
        }

        // Add custom data to the view
        $content = $this->view(setting('Auth.views')['login'], [
            'title'        => 'Sign In to ' . config('App')->appName,
            'socialLogins' => ['google', 'github'],
        ]);

        return $this->response->setBody($content);
    }
}
```

---

## RegisterController

Handles user registration.

**Routes**:
```
GET  /register → registerView()
POST /register → registerAction()
```

### Extending RegisterController

```php
<?php

namespace App\Controllers;

use Daycry\Auth\Controllers\RegisterController as BaseRegisterController;

class RegisterController extends BaseRegisterController
{
    protected function getValidationRules(): array
    {
        // Add extra fields on top of the defaults
        return array_merge(parent::getValidationRules(), [
            'firstname' => ['label' => 'First Name', 'rules' => 'required|max_length[50]'],
            'lastname'  => ['label' => 'Last Name',  'rules' => 'required|max_length[50]'],
        ]);
    }
}
```

---

## ActionController

Handles post-authentication actions such as email 2FA, account activation, and TOTP verification.

**Routes**:
```
GET  /auth/a/show   → show()
POST /auth/a/handle → handle()
POST /auth/a/verify → verify()
```

This controller is called automatically by the library when `$actions['login']` or `$actions['register']` is set in `Config/Auth.php`. You do not typically extend it.

---

## MagicLinkController

Handles passwordless login via one-time email links.

**Routes**:
```
GET  /login/magic-link        → loginView()
POST /login/magic-link        → loginAction()
GET  /login/verify-magic-link → verify()
```

The base controller is fully functional. Extend only if you need to customise the view or post-login redirect:

```php
use Daycry\Auth\Controllers\MagicLinkController as BaseMagicLinkController;

class MagicLinkController extends BaseMagicLinkController
{
    // Override only what you need
}
```

---

## PasswordResetController

Provides the complete password reset flow for users who have forgotten their password.

**Routes** (from `Config/Auth.php`):
```
GET  /password-reset          → requestView()   — Show "Enter your email" form
POST /password-reset          → requestAction() — Send reset email
GET  /password-reset/message  → messageView()   — "Check your inbox" confirmation
GET  /password-reset/{token}  → resetView()     — Show "Set new password" form
POST /password-reset/{token}  → resetAction()   — Apply the new password
```

### How to Enable

Register the routes in `app/Config/Routes.php`:

```php
$routes->group('', ['namespace' => 'Daycry\Auth\Controllers'], static function ($routes) {
    $routes->get('password-reset',           'PasswordResetController::requestView',   ['as' => 'password-reset']);
    $routes->post('password-reset',          'PasswordResetController::requestAction');
    $routes->get('password-reset/message',   'PasswordResetController::messageView',   ['as' => 'password-reset-message']);
    $routes->get('password-reset/(:any)',    'PasswordResetController::resetView',     ['as' => 'password-reset-form']);
    $routes->post('password-reset/(:any)',   'PasswordResetController::resetAction');
});
```

### Configuration

```php
// app/Config/Auth.php
public int $passwordResetLifetime = HOUR; // How long the token is valid
```

### Security Design

- The `requestAction()` always shows a generic "check your email" message regardless of whether the email exists — this prevents email enumeration.
- Tokens are cryptographically secure (20 random bytes).
- Tokens are deleted immediately after use.
- After a successful reset, `Events::trigger('passwordReset', $user)` fires.

### Adding a Link to Your Login Page

```html
<a href="<?= route_to('password-reset') ?>">Forgot your password?</a>
```

### Customising the Reset Email

Override the view in `Config/Auth.php`:

```php
public array $views = [
    // ...
    'password-reset-email' => '\App\Views\Email\PasswordReset',
];
```

The view receives the variable `$link` — the full reset URL with the token.

### Listen for Reset Completion

```php
// app/Config/Events.php
Events::on('passwordReset', static function (object $user): void {
    // Revoke all active sessions on password change
    $user->revokeAllAccessTokens();
});
```

---

## ForcePasswordResetController

When an administrator flags an account for a mandatory password change (e.g., after a security incident), `ForcePasswordResetFilter` intercepts the user and sends them here.

**Routes**:
```
GET  /force-reset → showView()    — Show the form (requires current password)
POST /force-reset → resetAction() — Validate and update password
```

### How to Enable the Filter

```php
// app/Config/Filters.php
public array $aliases = [
    'force-reset' => \Daycry\Auth\Filters\ForcePasswordResetFilter::class,
];

// app/Config/Routes.php — apply the filter to protected routes
$routes->group('dashboard', ['filter' => 'auth:session,force-reset'], static function ($routes) {
    $routes->get('/', 'Dashboard::index');
});

// Register the reset routes (unfiltered, so user can access them)
$routes->get('force-reset',  'Daycry\Auth\Controllers\ForcePasswordResetController::showView',    ['as' => 'force-reset']);
$routes->post('force-reset', 'Daycry\Auth\Controllers\ForcePasswordResetController::resetAction');
```

### Flag a User Programmatically

```php
use Daycry\Auth\Models\UserIdentityModel;

// Flag one user
model(UserIdentityModel::class)->forceMultiplePasswordReset([$userId]);

// Flag all users (security breach scenario)
model(UserIdentityModel::class)->forceGlobalPasswordReset();
```

### What the Form Requires

The user must enter:
- Their **current password** (verified before changing)
- A **new password** and confirmation

This prevents someone who has briefly accessed an unlocked computer from changing another user's password.

---

## JwtController

Provides a complete stateless JWT authentication API with refresh token rotation. All three endpoints route their token CRUD through the overridable `service('jwtTokenRepository')` (a `JwtTokenRepository`):

- `login()` and `refresh()` persist a new opaque refresh token via `createRefreshToken()`.
- `refresh()` and `logout()` look the token up with `getRefreshToken()` and **soft-revoke** the old one with `softRevokeRefreshToken()` (sets `revoked_at` rather than deleting the row), tagging the reason `'rotation'` and `'logout'` respectively.

**Routes**:
```
POST /auth/jwt/login   → login()   — Exchange credentials for access + refresh token
POST /auth/jwt/refresh → refresh() — Exchange refresh token for new token pair
POST /auth/jwt/logout  → logout()  — Soft-revoke the refresh token
```

### Register the Routes

```php
$routes->post('auth/jwt/login',   'Daycry\Auth\Controllers\JwtController::login',   ['as' => 'jwt-login']);
$routes->post('auth/jwt/refresh', 'Daycry\Auth\Controllers\JwtController::refresh', ['as' => 'jwt-refresh']);
$routes->post('auth/jwt/logout',  'Daycry\Auth\Controllers\JwtController::logout',  ['as' => 'jwt-logout']);
```

### Configuration

```php
// app/Config/AuthSecurity.php
public int $jwtRefreshLifetime = 30 * DAY; // Refresh token validity (seconds)
```

### login()

**Request** (`application/x-www-form-urlencoded` or JSON):
```
email=user@example.com
password=secret
```

**Response** (200):
```json
{
    "access_token":  "eyJ0eXAi...",
    "refresh_token": "a3f8c2d1...",
    "user_id":       42,
    "token_type":    "Bearer"
}
```

**Error** (401):
```json
{ "message": "Invalid credentials." }
```

The minted JWT access token carries the payload `{uid, tv}`, where `tv` is the user's current `users.token_version`. See [Access Token Revocation](#access-token-revocation-token_version) below.

### refresh()

**Request**:
```
user_id=42
refresh_token=a3f8c2d1...
```

**Response** (200):
```json
{
    "access_token":  "eyJ0eXAi...",
    "refresh_token": "newtoken...",
    "user_id":       42,
    "token_type":    "Bearer"
}
```

The **old refresh token is immediately soft-revoked** (`revoked_at` is set, reason `'rotation'`) — it is single-use and cannot be replayed. Store the new pair in your client.

A `user_id` / `refresh_token` that does not resolve to a live (non-revoked, non-expired) token returns:

```json
{ "message": "The refresh token is invalid or has expired." }
```

(`lang('Auth.invalidRefreshToken')`, HTTP 401.) If the user no longer exists, the response is `lang('Auth.invalidUser')` instead.

### logout()

**Request**:
```
user_id=42
refresh_token=a3f8c2d1...
```

**Response** (200):
```json
{ "message": "You have successfully logged out." }
```

`logout()` soft-revokes the matching refresh token (reason `'logout'`). It always returns success (`lang('Auth.successLogout')`) even when the supplied token does not match — so a logout never leaks whether a token was valid.

### Access Token Revocation (`token_version`)

JWT **access tokens** are stateless and short-lived, so they cannot be individually revoked from a server-side denylist. Instead the library carries a per-user version counter inside the token:

- The user record has a `users.token_version` column (`int`, default `0`), added by migration `2026-05-08-000001_add_jwt_token_version_to_users`.
- `JwtController` mints the access-token payload as `{uid, tv}` where `tv` is the user's `token_version` at issue time.
- On every authenticated request the JWT authenticator's `check()` compares the embedded `tv` against the user's current `token_version`. A mismatch rejects the token with `lang('Auth.revokedToken')` ("The token has been revoked.").
- Legacy scalar payloads (a bare user id with no `tv`) are still accepted — the version check is simply skipped for them.

To invalidate **all** of a user's outstanding access tokens ("log out everywhere"), bump the counter:

```php
$user->revokeIssuedTokens(); // atomically increments users.token_version
```

This is called automatically by:

| Trigger | Effect |
|---------|--------|
| `Bannable::ban()` | Banning a user revokes their issued access tokens. |
| `Services\PasswordChangeRecorder::record()` | A password reset / change revokes issued access tokens. |

Because the check is purely arithmetic against a single column, revocation is immediate and requires no denylist or token storage.

### Security Notes

- Access tokens are short-lived JWTs (configured in `Config/JWT.php`); revoke them wholesale via `token_version` (see above).
- Refresh tokens are stored hashed (SHA-256) in `auth_users_identities` with type `jwt_refresh`.
- Each refresh token is one-time use — `refresh()` soft-revokes the old token and issues a new pair on every call.
- Revoke a refresh token by calling `/logout` (soft-revokes via `JwtTokenRepository::softRevokeRefreshToken()`) or by revoking the identity record directly.
- Both refresh-token and access-token revocation are non-destructive (soft `revoked_at` / version bump), preserving an audit trail.

---

## OauthController

Handles the OAuth2 "Sign in with…" flow plus an explicit account-**linking** flow. The routes are auto-registered from `Config/Auth.php::$routes['oauth']`:

| Method | Route | Action | Route name |
|--------|-------|--------|------------|
| `GET` | `oauth/login/(:segment)` | `redirect/$1` | `oauth-login` |
| `GET` | `oauth/callback/(:segment)` | `callback/$1` | `oauth-callback` |
| `GET` | `oauth/link/(:segment)` | `link/$1` | `oauth-link` |

The `(:segment)` is the provider name (`google`, `github`, `azure`, …) configured in `Config/AuthOAuth.php::$providers`.

### redirect($provider) & callback($provider)

`redirect()` sends the visitor to the provider's consent screen; `callback()` exchanges the returned `code`/`state` via `OauthManager::handleCallback()` and signs the user in, redirecting to `loginRedirect()` on success or back to `loginPage()` with an `error` flash on failure. `handleCallback()` fires the `oauth-login` and (when profile fields are configured) `oauth-profile-fetched` events.

### link($provider)

`link()` is the **explicit linking** action: it connects a provider to the **currently authenticated** user instead of signing a new one in.

- It **requires an authenticated user** — if `auth()->loggedIn()` is false it redirects to `config('Auth')->loginPage()`.
- It stashes the current user id in the session under `oauth_link_user_id`, then redirects to the provider exactly like `redirect()`.
- When the provider returns, the shared `callback()` detects the stashed id and links the social account to that user — **deliberately**, so there is no e-mail merge and no verified-email requirement (the user is already authenticated and acting on purpose).
- If the social account is already bound to a **different** local user, linking is refused with `lang('Auth.oauthAlreadyLinked')` ("This account is already linked to a different user.").

```html
<!-- Offer the logged-in user a "Connect GitHub" button -->
<a href="<?= route_to('oauth-link', 'github') ?>" class="btn btn-outline-dark">
    Connect GitHub
</a>
```

> **Linking vs. automatic merge.** The `link()` route is the safe way to attach a provider to an existing account. The *automatic* merge that happens during a normal `oauth-login` (matching an existing local e-mail) is opt-in per provider via `'allowUnverifiedEmailLink'` and only auto-merges when the provider asserts the e-mail is verified — otherwise it is refused with `lang('Auth.oauthEmailUnverified')`. See the [OAuth guide](09-oauth.md) for that flow. Use `link()` whenever you want the user to attach a provider on demand.

### Customising

The base controller is fully functional; extend it only to change the post-callback redirect or add provider-specific handling:

```php
use Daycry\Auth\Controllers\OauthController as BaseOauthController;

class OauthController extends BaseOauthController
{
    // Override only what you need
}
```

---

## WebAuthnController

Exposes the WebAuthn / passkey ceremonies as **JSON endpoints**. The routes are auto-registered from `Config/Auth.php::$routes['webauthn']` **only when `AuthSecurity::$webauthnEnabled` is `true`**; the controller additionally re-checks the flag and returns `404` when the feature is disabled (defense in depth).

| Method | Route | Action | Access |
|--------|-------|--------|--------|
| `POST` | `webauthn/register/options` | enrollment: creation options | auth required |
| `POST` | `webauthn/register/verify` | enrollment: verify attestation | auth required |
| `POST` | `webauthn/login/options` | passwordless: request options | public |
| `POST` | `webauthn/login/verify` | passwordless: verify assertion | public |
| `POST` | `webauthn/2fa/options` | 2FA: request options for the pending user | pending login |
| `POST` | `webauthn/credentials/{uuid}/delete` | revoke a passkey | auth required |

Every endpoint returns JSON (`{status, ...}` on success, or `{status:"error", error, message}` with a 4xx code). A successful passwordless `login/verify` establishes the session and returns `{status:"ok", redirect}`. The 2FA verify step is handled by the `Webauthn2FA` action through the shared `ActionController` verify endpoint, not by `WebAuthnController`.

This is a JSON API controller — you typically do not extend it; an SPA can call the endpoints directly, while the bundled `webauthn_setup` / `webauthn_2fa_verify` views drive the browser ceremonies. See [WebAuthn / Passkeys — Routes & JSON Endpoints](15-webauthn.md#routes--json-endpoints) for the full request/response contracts and error codes.

---

## UserSecurityController

Provides self-service security management for logged-in users: change password, change email, manage device sessions, and unlink OAuth providers.

**All routes require an active session** (`filter: session`).

> When WebAuthn is enabled, the user's registered passkeys are listed inside the existing `security_overview` view rendered by this controller — the enrollment / deletion ceremonies themselves run against the JSON endpoints of [`WebAuthnController`](#webauthncontroller). See [WebAuthn / Passkeys](15-webauthn.md#frontend--javascript).

### Register the Routes

```php
// app/Config/Routes.php
$routes->group('security', ['filter' => 'auth:session', 'namespace' => 'Daycry\Auth\Controllers'], static function ($routes) {
    // Change password
    $routes->get('password',        'UserSecurityController::changePasswordView',   ['as' => 'security-password']);
    $routes->post('password',       'UserSecurityController::changePassword');

    // Change email
    $routes->get('email',           'UserSecurityController::changeEmailView',      ['as' => 'security-email']);
    $routes->post('email',          'UserSecurityController::changeEmail');
    $routes->get('email/confirm',   'UserSecurityController::confirmEmailChange',   ['as' => 'security-email-confirm']);

    // Device sessions
    $routes->get('sessions',        'UserSecurityController::deviceSessionsView',   ['as' => 'security-sessions']);
    $routes->delete('sessions/(:segment)', 'UserSecurityController::terminateDeviceSession/$1');
    $routes->delete('sessions/other/all',  'UserSecurityController::terminateOtherDeviceSessions');

    // TOTP
    $routes->get('totp/enable',     'UserSecurityController::totpEnableView',       ['as' => 'totp-enable']);
    $routes->post('totp/enable',    'UserSecurityController::totpEnableAction');
    $routes->post('totp/disable',   'UserSecurityController::totpDisableAction',    ['as' => 'totp-disable']);

    // OAuth
    $routes->post('oauth/unlink/(:segment)', 'UserSecurityController::unlinkOauth/$1', ['as' => 'oauth-unlink']);

    // Login activity feed (recent attempts)
    $routes->get('activity', 'UserSecurityController::loginActivity', ['as' => 'security-activity']);
});
```

### changePassword()

Users can change their password by providing their current password + new password.

**Form fields**:
- `current_password` — verified before changing
- `new_password`
- `new_password_confirm` — must match `new_password`

### changeEmail()

Initiates an email address change. The system sends a confirmation link to the **new** email address. The address is only updated once the link is clicked.

**Form fields**:
- `new_email` — the new address (must be a valid email not already in use)
- `current_password` — required to authorise the change

### confirmEmailChange()

Called when the user clicks the confirmation link in their email. Validates the token and updates the email address.

### unlinkOauth()

Removes an OAuth provider link from the user's account. Fails gracefully if the user would have no remaining login method.

### loginActivity()

Returns a Bootstrap-styled view (`Views/security/login_activity.php`) listing the user's recent login attempts (success + failure). Reads from `auth_logins` via `LoginModel::recentForUser()`.

**Query string parameters:**

| Param | Default | Max | Description |
|-------|---------|-----|-------------|
| `limit` | `25` | `100` | Number of entries to display. |

**Override the view** via `setting('Auth.views')['security_login_activity']`:

```php
// app/Config/Auth.php
public array $views = [
    // ...
    'security_login_activity' => 'App\Views\security\my_activity_feed',
];
```

The default view exposes these variables:

| Variable | Type | Description |
|----------|------|-------------|
| `$entries` | `list<\Daycry\Auth\Entities\Login>` | Login rows newest-first. |
| `$limit` | `int` | The applied limit. |

```html
<!-- In a security settings view -->
<?= form_open('security/oauth/unlink/google', ['method' => 'post']) ?>
    <button type="submit" class="btn btn-sm btn-outline-danger">
        Disconnect Google
    </button>
<?= form_close() ?>
```

---

## Creating Custom Controllers

### Minimal Custom Controller

Any controller that needs auth features can extend `BaseAuthController`:

```php
<?php

namespace App\Controllers;

use Daycry\Auth\Controllers\BaseAuthController;
use CodeIgniter\HTTP\ResponseInterface;

class AccountController extends BaseAuthController
{
    // Required by BaseAuthController
    protected function getValidationRules(): array
    {
        return [
            'name' => ['label' => 'Name', 'rules' => 'required|max_length[100]'],
        ];
    }

    public function update(): ResponseInterface
    {
        $postData = $this->request->getPost();

        if (! $this->validateRequest($postData, $this->getValidationRules())) {
            return $this->handleValidationError(route_to('account-edit'));
        }

        $user = auth()->user();
        $user->username = $postData['name'];

        model(\Daycry\Auth\Models\UserModel::class)->save($user);

        return $this->handleSuccess(route_to('account'), 'Profile updated successfully.');
    }
}
```

### API Controller with JWT + Access Token

```php
<?php

namespace App\Controllers\API;

use CodeIgniter\RESTful\ResourceController;

class PostsController extends ResourceController
{
    protected $format = 'json';

    public function index()
    {
        $user = auth()->user(); // Works with any authenticator (jwt, access_token)

        if (! $user->can('posts.view')) {
            return $this->failForbidden('You do not have permission to view posts.');
        }

        $posts = model(\App\Models\PostModel::class)
            ->where('user_id', $user->id)
            ->findAll();

        return $this->respond(['data' => $posts]);
    }

    public function create()
    {
        $user = auth()->user();

        if (! $user->can('posts.create')) {
            return $this->failForbidden('You cannot create posts.');
        }

        $data = $this->request->getJSON(true);

        // ... create post

        return $this->respondCreated(['message' => 'Post created.']);
    }
}
```

---

## Best Practices

### 1. Always Use BaseAuthController for Auth Logic

Avoid duplicating redirect, validation, and error-handling code. `BaseAuthController`'s helper methods handle all of this consistently.

### 2. Validate in getValidationRules(), Not in Action Methods

```php
// Good
protected function getValidationRules(): array
{
    return ['email' => ['rules' => 'required|valid_email']];
}

public function someAction()
{
    if (! $this->validateRequest($this->request->getPost(), $this->getValidationRules())) {
        return $this->handleValidationError('/form-url');
    }
    // ...
}

// Avoid
public function someAction()
{
    if (! $this->request->getPost('email')) { // Ad-hoc validation
        return redirect()->back()->with('error', 'Email required');
    }
}
```

### 3. Check Permissions Early

```php
public function adminAction()
{
    if (! auth()->user()->inGroup('admin')) {
        return redirect()->to('/')->with('error', 'Unauthorized');
    }
    // ... rest of action
}
```

### 4. Use Events for Side Effects

Don't put email sending, logging, or audit trails directly in controllers. Use CI4 Events instead:

```php
// In a controller
$user->setPassword($newPassword);
model(UserModel::class)->save($user);
Events::trigger('passwordChanged', $user); // ← clean

// In app/Config/Events.php — the side effect lives here
Events::on('passwordChanged', fn($user) => /* send notification */);
```

### 5. Return Correct HTTP Status Codes in APIs

```php
return $this->response->setStatusCode(422)->setJSON(['errors' => $errors]); // Validation
return $this->response->setStatusCode(401)->setJSON(['message' => 'Unauthenticated']);
return $this->response->setStatusCode(403)->setJSON(['message' => 'Forbidden']);
```

---

🔗 **See also**:
- [Filters](04-filters.md) — Protect routes before they reach controllers
- [Authorization](06-authorization.md) — Groups and permissions
- [Authentication](03-authentication.md) — JWT refresh tokens, password reset flow
- [TOTP 2FA](10-totp-2fa.md) — Two-factor authentication
- [Device Sessions](11-device-sessions.md) — Session management
