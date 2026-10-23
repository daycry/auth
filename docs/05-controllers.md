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
```text
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
```text
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
```text
GET  /auth/a/show   → show()
POST /auth/a/handle → handle()
POST /auth/a/verify → verify()
```

This controller is called automatically by the library when `$actions['login']` or `$actions['register']` is set in `Config/Auth.php`. You do not typically extend it.

---

## MagicLinkController

Handles passwordless login via email — supports both one-time links and 6-digit OTP codes.

**Routes**:
```text
GET  /login/magic-link         → loginView()      # Email entry form (both modes)
POST /login/magic-link         → loginAction()    # Branches on delivery=link|code
GET  /login/magic-link/message → messageView()    # "check your email" confirmation page
GET  /login/verify-magic-link  → verify()         # Link mode: verify token from email link
GET  /login/magic-link/code    → codeView()       # Code mode: 6-digit code entry form
POST /login/magic-link/code    → verifyCode()     # Code mode: verify submitted code
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
```text
GET  /password-reset          → requestView()   — Show "Enter your email" form
POST /password-reset          → requestAction() — Send reset email
GET  /password-reset/message  → messageView()   — "Check your inbox" confirmation
GET  /password-reset/verify   → resetView()     — Show "Set new password" form
POST /password-reset/verify   → resetAction()   — Apply the new password
```

### How to Enable

Register the routes in `app/Config/Routes.php`:

```php
$routes->group('', ['namespace' => 'Daycry\Auth\Controllers'], static function ($routes) {
    $routes->get('password-reset',           'PasswordResetController::requestView',   ['as' => 'password-reset']);
    $routes->post('password-reset',          'PasswordResetController::requestAction');
    $routes->get('password-reset/message',   'PasswordResetController::messageView',   ['as' => 'password-reset-message']);
    $routes->get('password-reset/verify',    'PasswordResetController::resetView',     ['as' => 'password-reset-verify']);
    $routes->post('password-reset/verify',   'PasswordResetController::resetAction');
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
- The new password must satisfy the same policy as registration (`AuthSecurity::$minimumPasswordLength` and the `$passwordValidators`, checked with `Passwords::check()`). A rejected password does not consume the token, so the user can retry from the same link. Override `getResetValidationRules()` to change the form rules.
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
```text
GET  /auth/force-reset → showView()    — Show the form (requires current password)
POST /auth/force-reset → resetAction() — Validate and update password
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
$routes->get('auth/force-reset',  'Daycry\Auth\Controllers\ForcePasswordResetController::showView',    ['as' => 'force-reset']);
$routes->post('auth/force-reset', 'Daycry\Auth\Controllers\ForcePasswordResetController::resetAction');
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
- A **new password** and confirmation. The new password must satisfy the same policy as registration (`Passwords::check()`).

Validation errors return to the form itself. `Auth::$redirects['force_reset']` is only where `ForcePasswordResetFilter` sends flagged users.

This prevents someone who has briefly accessed an unlocked computer from changing another user's password.

---

## JwtController

Provides a complete stateless JWT authentication API with refresh token rotation. All three endpoints route their token CRUD through the overridable `service('jwtTokenRepository')` (a `JwtTokenRepository`):

- `login()` and `refresh()` persist a new opaque refresh token via `createRefreshToken()`.
- `refresh()` and `logout()` look the token up with `getRefreshToken()` and **soft-revoke** the old one with `softRevokeRefreshToken()` (sets `revoked_at` rather than deleting the row), tagging the reason `'rotation'` and `'logout'` respectively.

**Routes**:
```text
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
```text
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
```text
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
```text
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
| `Bannable::ban()` | Banning a user revokes their issued access **and refresh** tokens. |
| `Services\PasswordChangeRecorder::record()` | A password reset / change revokes issued access **and refresh** tokens. |

Because the access-token check is purely arithmetic against a single column, revocation is immediate and requires no denylist. The user's refresh tokens are soft-revoked at the same time, so a stolen refresh token cannot mint a fresh access token afterwards; `refresh()` also refuses banned users.

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

Self-service account and security management for signed-in users:

- device sessions (list + revoke), TOTP two-factor (set up / disable), login activity;
- **change password**, or **set a first password** on a password-less account;
- **change e-mail address**, confirmed through a link sent to the new address;
- **unlink an OAuth provider**, which is refused if it is the last way to sign in;
- **delete the account** (GDPR anonymisation).

Every route needs an authenticated session (`auth:session` filter). Keep CI4 CSRF protection on for all POST routes, and add the [`rates`](04-filters.md) filter to the mutating ones.

> When WebAuthn is enabled, the user's registered passkeys are listed inside the existing `security_overview` view rendered by this controller — the enrollment / deletion ceremonies themselves run against the JSON endpoints of [`WebAuthnController`](#webauthncontroller). See [WebAuthn / Passkeys](15-webauthn.md#frontend--javascript).

### Register the Routes

```php
// app/Config/Routes.php
$c = '\Daycry\Auth\Controllers\UserSecurityController::';

$routes->group('account/security', ['filter' => 'auth:session'], static function ($routes) use ($c) {
    $routes->get('/',                        $c . 'index',                    ['as' => 'security']);

    // Device sessions
    $routes->post('sessions/revoke',         $c . 'revokeSession',            ['as' => 'security-revoke-session']);
    $routes->post('sessions/revoke-all',     $c . 'revokeAllSessions',        ['as' => 'security-revoke-all']);

    // TOTP two-factor
    $routes->get('totp/setup',               $c . 'totpSetup',                ['as' => 'totp-setup']);
    $routes->post('totp/setup/confirm',      $c . 'totpSetupConfirm',         ['as' => 'totp-setup-confirm']);
    $routes->post('totp/disable',            $c . 'totpDisable',              ['as' => 'totp-disable']);

    // Login activity feed
    $routes->get('activity',                 $c . 'loginActivity',            ['as' => 'security-activity']);

    // Password
    $routes->get('password',                 $c . 'changePasswordView',       ['as' => 'security-change-password']);
    $routes->post('password',                $c . 'changePassword',           ['as' => 'security-change-password-action']);

    // E-mail address
    $routes->get('email',                    $c . 'changeEmailView',          ['as' => 'security-change-email']);
    $routes->post('email',                   $c . 'changeEmail',              ['as' => 'security-change-email-action']);
    $routes->get('email/confirm',            $c . 'confirmEmailChangeView',   ['as' => 'security-confirm-email']);
    $routes->post('email/confirm',           $c . 'confirmEmailChange',       ['as' => 'security-confirm-email-action']);

    // OAuth
    $routes->post('oauth/(:segment)/unlink', $c . 'unlinkOauth/$1',           ['as' => 'security-unlink-oauth']);

    // Account deletion
    $routes->get('delete',                   $c . 'deleteAccountView',        ['as' => 'security-delete-account']);
    $routes->post('delete',                  $c . 'deleteAccount',            ['as' => 'security-delete-account-action']);
    $routes->get('delete/confirm',           $c . 'confirmDeleteAccountView', ['as' => 'security-confirm-delete']);
    $routes->post('delete/confirm',          $c . 'confirmDeleteAccount',     ['as' => 'security-confirm-delete-action']);
});
```

The confirmation e-mails link to the `security-confirm-email` and `security-confirm-delete` routes, so those route names must exist.

### Password-less accounts

Accounts created through OAuth have no password. Many also have no e-mail address, because the address stays in the social identity. These accounts cannot be asked for a "current password", so:

| Action | Password account | Password-less account |
|---|---|---|
| Re-authentication | `current_password` | A **recent interactive sign-in** (within `AuthSecurity::$passwordConfirmationLifetime`, the sudo-mode window) |
| Change password | change it | **set a first password**; needs an e-mail on file |
| Delete account | immediate after `current_password` | **confirmation link** e-mailed to the account; needs an e-mail on file |

A sign-in is "interactive" when it uses a password, OAuth, a magic link or 2FA. Remember-me auto-logins do **not** count. This stops a stolen long-lived session from planting a password or moving the account to another e-mail address. The window can also be refreshed with the `password-confirm` sudo mode.

An e-mail identity whose password hash is the empty password does **not** count as a password. Such an identity is what an OAuth account gets once it confirms an e-mail address.

### changePasswordView() / changePassword()

**Form fields:**
- `current_password`: only shown and required on a password account;
- `new_password`: checked by the configured password validators;
- `new_password_confirm`: must match `new_password`.

On success:
- password history and `password_changed_at` are updated;
- every issued JWT is revoked: access tokens through `token_version`, and refresh tokens;
- every **other** device session and every remember-me token is revoked. The current session stays signed in;
- the change is audited as `password.changed`;
- a [security notice](#security-notification-e-mails) is sent and the `password-changed` event is fired.

### changeEmailView() / changeEmail()

**Form fields:** `new_email` and, on a password account, `current_password`.

The request is rejected when:
- the address is invalid;
- it is already the account's address;
- it belongs to **another** account. This makes the endpoint an authenticated-only, low-severity enumeration surface; the `rates` filter mitigates it.

Otherwise:
- a single-use `email_change` link, hashed at rest and valid for `AuthSecurity::$emailChangeLifetime`, is sent to the **new** address;
- the **current** address is warned immediately.

Nothing changes until the link is confirmed.

### confirmEmailChangeView() / confirmEmailChange()

The GET request only renders a confirmation page, because mail scanners and link prefetchers follow GET links. The page's POST applies the change, after these checks:

1. The token must belong to the signed-in user.
2. The token is consumed first (single use).
3. The address is checked again.

The `User` entity is then saved, so the **login** identity moves to the new address. Finally, the previous address is notified, `email.changed` is audited and the `email-changed` event is fired.

### unlinkOauth(string $provider)

Removes the `oauth_<provider>` identity. The request is refused (`Auth.unlinkOauthLastMethod`) unless at least one other way to sign in remains:
- a usable password;
- another linked provider;
- a passkey.

Audited as `oauth.unlinked`; fires `oauth-unlinked`.

```html
<?= form_open(url_to('security-unlink-oauth', 'google')) ?>
    <button type="submit" class="btn btn-sm btn-outline-danger">Disconnect Google</button>
<?= form_close() ?>
```

### deleteAccountView() / deleteAccount() / confirmDeleteAccountView() / confirmDeleteAccount()

The account is anonymised by `Services\UserAnonymizer`, the same code as `php spark auth:gdpr anonymize`. The steps run in **one transaction**:

1. Remove every identity, device session, remember-me token, passkey, TOTP backup code and password-history row.
2. Remove the login history: successful sign-ins by user id, and failed attempts by the login e-mail.
3. Overwrite the users row (`username = deleted_<id>`, `active = 0`).

The row itself is kept, so foreign keys and the audit trail stay intact. The deletion is audited as `user.anonymized` with `initiator: self-service`.

How it starts depends on the account:
- **Password account:** deleted right after `current_password` is verified.
- **Password-less account:** receives an `account_delete` confirmation link instead. Its confirmation GET and POST work like the e-mail change ones.
- **No password and no e-mail:** asked to add an e-mail first.

The farewell notice is sent **after** the anonymisation has committed, and then the user is signed out. If anything fails, nothing is changed and the user sees `Auth.deleteAccountFailed`.

### Views

Each screen can be overridden through `Config\Auth::$views`:

| Key | Default |
|---|---|
| `security-change-password` | `Views/security/change_password.php` |
| `security-change-email` | `Views/security/change_email.php` |
| `security-confirm-email-change` | `Views/security/confirm_email_change.php` |
| `security-delete-account` | `Views/security/delete_account.php` |
| `security-confirm-delete-account` | `Views/security/confirm_delete_account.php` |
| `email-change-email` | `Views/Email/email_change_email.php` (link to the new address) |
| `security-account-delete-email` | `Views/Email/account_delete_email.php` (deletion link) |

### Security notification e-mails

`Libraries\SecurityNotifier` (`service('securityNotifier')`) e-mails the user when:
- the password changes;
- an e-mail change is requested (sent to the current address) or completed (sent to the previous address);
- the account is deleted;
- two-factor authentication is turned off;
- a sign-in looks suspicious. This one also needs `AuthSecurity::$suspiciousLoginAlerts`.

All of them are controlled by `AuthSecurity::$securityNotificationEmails`, which defaults to `true`. Accounts without an e-mail address are skipped. Delivery is swallow-and-log: a mail failure never breaks the action that triggered it.

The notices share `Views/Email/security_notice_email.php`. Each one has its own `$views` key (`security-password-changed-email`, `security-email-change-requested-email`, `security-email-changed-email`, `security-account-deleted-email`, `security-totp-disabled-email`, `security-suspicious-login-email`). To change delivery, for example to queue the mails, override the `securityNotifier` service.

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
