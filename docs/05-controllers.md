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
$routes->group('dashboard', ['filter' => 'session,force-reset'], static function ($routes) {
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

Provides a complete stateless JWT authentication API with refresh token rotation.

**Routes**:
```
POST /auth/jwt/login   → login()   — Exchange credentials for access + refresh token
POST /auth/jwt/refresh → refresh() — Exchange refresh token for new token pair
POST /auth/jwt/logout  → logout()  — Revoke the refresh token
```

### Register the Routes

```php
$routes->post('auth/jwt/login',   'Daycry\Auth\Controllers\JwtController::login',   ['as' => 'jwt-login']);
$routes->post('auth/jwt/refresh', 'Daycry\Auth\Controllers\JwtController::refresh', ['as' => 'jwt-refresh']);
$routes->post('auth/jwt/logout',  'Daycry\Auth\Controllers\JwtController::logout',  ['as' => 'jwt-logout']);
```

### Configuration

```php
// app/Config/Auth.php
public int $jwtRefreshLifetime = 30 * DAY; // Refresh token validity
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

The **old refresh token is immediately revoked**. Store the new one in your client.

### logout()

**Request**:
```
user_id=42
refresh_token=a3f8c2d1...
```

**Response** (200):
```json
{ "message": "Logged out successfully." }
```

### Security Notes

- Access tokens are short-lived JWTs (configured in `Config/JWT.php`).
- Refresh tokens are stored hashed (SHA-256) in `auth_users_identities` with type `jwt_refresh`.
- Each refresh token is one-time use — a new pair is issued on every `/refresh` call.
- Revoke a refresh token by calling `/logout` or by revoking the identity record directly.

---

## UserSecurityController

Provides self-service security management for logged-in users: change password, change email, manage device sessions, and unlink OAuth providers.

**All routes require an active session** (`filter: session`).

### Register the Routes

```php
// app/Config/Routes.php
$routes->group('security', ['filter' => 'session', 'namespace' => 'Daycry\Auth\Controllers'], static function ($routes) {
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
