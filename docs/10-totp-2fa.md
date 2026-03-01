# 🔐 TOTP Two-Factor Authentication

Time-based One-Time Passwords (TOTP) add a powerful second layer of security to your application. After entering their password, users must provide a 6-digit code from an authenticator app such as **Google Authenticator**, **Authy**, or **1Password**.

## 📋 Table of Contents

- [How It Works](#how-it-works)
- [Configuration](#configuration)
- [User Enrollment](#user-enrollment)
- [Login Flow](#login-flow)
- [HasTotp Trait Reference](#hastotp-trait-reference)
- [UserSecurityController Integration](#usersecuritycontroller-integration)
- [Disabling TOTP](#disabling-totp)
- [Testing TOTP](#testing-totp)
- [Security Notes](#security-notes)

---

## How It Works

```
User enters email + password
        ↓
Credentials verified ✅
        ↓
System detects Totp2FA action is required
        ↓
User is shown a "Enter your 6-digit code" form
        ↓
User opens authenticator app → copies code
        ↓
Code verified against TOTP secret ✅
        ↓
Session created — user is logged in
```

The TOTP secret is stored permanently in `auth_users_identities` with type `totp_secret`, **AES-256 encrypted** using CI4's `service('encrypter')`. The raw secret is never stored in plain text.

Enrollment follows a two-phase flow:
1. **Pending** (`name = totp_pending`) — secret generated, QR shown, user not yet confirmed
2. **Confirmed** (`name = totp`) — first code verified, TOTP fully active

The `Totp2FA` login action only challenges users whose TOTP is in the **confirmed** state.

---

## Configuration

### 1. Enable the TOTP Post-Login Action

In `app/Config/Auth.php`:

```php
use Daycry\Auth\Authentication\Actions\Totp2FA;

public array $actions = [
    'register' => null,
    'login'    => Totp2FA::class, // Require TOTP on every login
];
```

> **Note**: This only applies to users who have TOTP enabled (`hasTotpEnabled() === true`). Users who have not enrolled skip the 2FA step and log in directly.

### 2. Set the Issuer Name

In `app/Config/AuthSecurity.php`, set the app name shown in the authenticator app:

```php
public string $totpIssuer = 'My App';
```

### 3. Configure the Encryption Key

TOTP secrets are encrypted with CI4's encrypter. Make sure `app/Config/Encryption.php` has a key set (or `encryption.key` in `.env`):

```bash
# .env
encryption.key = hex2bin:your64charhexstringhere
```

---

## User Enrollment

The enrollment flow is handled by the `HasTotp` trait (mixed into `User`). It is a **two-phase** process:

### Phase 1 — Generate the QR code

```php
$user = auth()->user();

// Always generates a fresh secret (replaces any previous pending one).
// Returns the otpauth:// URI for building a QR code.
$otpAuthUrl = $user->enableTotp('My App');

// getTotpSecret() transparently decrypts the stored value.
$secret = $user->getTotpSecret();

// Build a QR code data URI (rendered locally, no external service).
$qrCodeDataUri = \Daycry\Auth\Libraries\TOTP::getQRCodeUrl($otpAuthUrl);
// $qrCodeDataUri = "data:image/png;base64,..."

return view('security/totp_setup', [
    'qrCodeDataUri' => $qrCodeDataUri,
    'secret'        => $secret, // plain-text fallback for manual entry
]);
```

> `enableTotp()` stores the secret in the **pending** state. If the user navigates away before confirming, a fresh secret is generated the next time they visit the setup page.

### Phase 2 — Confirm the first code

```php
$user = auth()->user();
$code = $this->request->getPost('token');

if (! $user->verifyTotpCode($code)) {
    return redirect()->back()->with('error', 'Invalid code. Please try again.');
}

// Upgrades the secret from PENDING → CONFIRMED. TOTP is now active.
$user->confirmTotp();

return redirect()->to('security')->with('message', 'Two-factor authentication is now enabled.');
```

### Setup View

```html
<!-- Phase 1: Show QR code -->
<h2>Enable Two-Factor Authentication</h2>

<h5>Step 1: Scan this QR Code</h5>
<p>Open your authenticator app and scan:</p>

<!-- QR code is a data URI — no external service required -->
<img src="<?= esc($qrCodeDataUri) ?>" alt="TOTP QR Code" width="200" height="200">

<p class="text-muted mt-2">
    Can't scan? Enter this code manually: <code><?= esc($secret) ?></code>
</p>

<!-- Phase 2: Confirm the code -->
<h5>Step 2: Enter the 6-digit code from your app</h5>
<?= form_open(url_to('totp-setup-confirm')) ?>
    <input type="text" name="token" maxlength="6" placeholder="000000"
           autocomplete="one-time-code" required>
    <button type="submit">Confirm &amp; Enable</button>
<?= form_close() ?>
```

---

## Login Flow

Once TOTP is **confirmed** for a user, the flow is handled **automatically** by the `Totp2FA` action. No changes to `LoginController` are needed.

### What Happens Automatically

1. User submits login form (email + password)
2. `Session::check()` verifies credentials
3. Session detects the `Totp2FA` action is configured
4. User is **redirected to the action show page** (not logged in yet)
5. The built-in view asks for the 6-digit code
6. `ActionController::verify()` decrypts the stored secret and validates the code
7. On success, `completeLogin()` clears the pending action state and creates the session

### Override the Default TOTP Views

In `app/Config/Auth.php`:

```php
public array $views = [
    // ... other views ...
    'action_totp_setup_show'    => '\Daycry\Auth\Views\totp_setup_show',    // QR setup page
    'action_totp_setup_success' => '\Daycry\Auth\Views\totp_setup_success', // Confirmation page
    'action_totp_show'          => '\Daycry\Auth\Views\totp_show',          // Login 2FA prompt
    'action_totp_verify'        => '\Daycry\Auth\Views\totp_verify',        // Login 2FA form
    'security_overview'         => '\Daycry\Auth\Views\profile\security',   // User security dashboard
];
```

---

## HasTotp Trait Reference

The `User` entity uses the `HasTotp` trait, which provides:

```php
// === Enrollment ===

// Generate a new secret (pending), returns the otpauth:// URL.
// If called again, replaces any existing secret.
$user->enableTotp(?string $issuer = null): string

// Returns true while the secret is generated but not yet confirmed.
$user->hasTotpPending(): bool

// Returns true only after confirmTotp() has been called.
$user->hasTotpEnabled(): bool

// Upgrades the identity from PENDING to CONFIRMED.
// Call only after verifyTotpCode() returns true.
$user->confirmTotp(): void

// Returns the decrypted base32 secret (or null if not set).
$user->getTotpSecret(): ?string

// === Verification ===

// Checks a 6-digit code against the user's stored secret.
$user->verifyTotpCode(string $code): bool

// === Removal ===

// Removes the TOTP secret identity entirely (both pending and confirmed).
$user->disableTotp(): void
```

### Security Dashboard Example

```php
public function securityIndex(): string
{
    $user = auth()->user();

    return view('security/index', [
        'totpEnabled'  => $user->hasTotpEnabled(),
        'totpPending'  => $user->hasTotpPending(),
        'deviceCount'  => count($user->getDeviceSessions()),
    ]);
}
```

---

## UserSecurityController Integration

Daycry Auth ships with `UserSecurityController` which provides ready-to-use TOTP management endpoints. Register the routes in `app/Config/Routes.php`:

```php
$routes->group('security', ['filter' => 'session', 'namespace' => 'Daycry\Auth\Controllers'], static function ($routes) {
    $routes->get('/',             'UserSecurityController::index',          ['as' => 'security']);
    $routes->get('totp/setup',    'UserSecurityController::totpSetup',      ['as' => 'totp-setup']);
    $routes->post('totp/confirm', 'UserSecurityController::totpSetupConfirm', ['as' => 'totp-setup-confirm']);
    $routes->post('totp/disable', 'UserSecurityController::totpDisable',    ['as' => 'totp-disable']);

    // Device session management
    $routes->post('sessions/(:num)/revoke', 'UserSecurityController::revokeSession/$1', ['as' => 'revoke-session']);
    $routes->post('sessions/revoke-all',    'UserSecurityController::revokeAllSessions', ['as' => 'revoke-all-sessions']);
});
```

The views are configured in `app/Config/Auth.php` under the `$views` array (see [Override the Default TOTP Views](#override-the-default-totp-views) above).

---

## Disabling TOTP

Always require password confirmation before disabling 2FA:

```php
public function disableTotpAction(): RedirectResponse
{
    $user     = auth()->user();
    $password = $this->request->getPost('current_password');

    $passwords = service('passwords');

    if (! $passwords->verify($password, $user->getPasswordHash())) {
        return redirect()->back()->with('error', 'Incorrect password.');
    }

    $user->disableTotp();

    return redirect()->to('security')->with('message', 'Two-factor authentication has been disabled.');
}
```

---

## Testing TOTP

`DatabaseTestCase` automatically injects a 32-byte AES encryption key, so `service('encrypter')` works without any extra setup in your tests.

```php
<?php

namespace Tests\Authentication;

use Tests\Support\DatabaseTestCase;
use Daycry\Auth\Libraries\TOTP;

class TotpTest extends DatabaseTestCase
{
    public function testEnrollAndVerifyTotp(): void
    {
        $user = fake(UserModel::class);

        // Phase 1: generate secret (creates a PENDING identity)
        $otpAuthUrl = $user->enableTotp('TestApp');

        $this->assertStringStartsWith('otpauth://totp/', $otpAuthUrl);
        $this->assertTrue($user->hasTotpPending());
        $this->assertFalse($user->hasTotpEnabled());
        $this->assertNotEmpty($user->getTotpSecret());

        // Phase 2: confirm — TOTP becomes active
        $user->confirmTotp();

        $this->assertTrue($user->hasTotpEnabled());
        $this->assertFalse($user->hasTotpPending());
    }

    public function testVerifyTotpCode(): void
    {
        $user = fake(UserModel::class);
        $user->enableTotp('TestApp');
        $user->confirmTotp();

        // An obviously wrong code should fail
        $this->assertFalse($user->verifyTotpCode('000000'));
    }

    public function testDisableTotpRemovesSecret(): void
    {
        $user = fake(UserModel::class);
        $user->enableTotp('TestApp');
        $user->confirmTotp();
        $user->disableTotp();

        $this->assertFalse($user->hasTotpEnabled());
        $this->assertNull($user->getTotpSecret());
    }

    public function testSecretIsEncryptedInDatabase(): void
    {
        $user = fake(UserModel::class);
        $user->enableTotp('TestApp');

        /** @var \Daycry\Auth\Models\UserIdentityModel $model */
        $model    = model(\Daycry\Auth\Models\UserIdentityModel::class);
        $identity = $model->where('user_id', $user->id)
                          ->where('type', 'totp_secret')
                          ->first();

        // The DB value is base64-encoded ciphertext — not the raw secret
        $this->assertNotSame($user->getTotpSecret(), $identity->secret);
        $this->assertNotEmpty(base64_decode($identity->secret, true));
    }
}
```

---

## Security Notes

- **Always require password confirmation** before enabling or disabling TOTP.
- The TOTP secret is stored **AES-256 encrypted** in `auth_users_identities`. The raw base32 secret is never in the database in plain text.
- TOTP codes are valid for a **30-second window** (±1 window tolerance for clock skew). Ensure your server clock is synchronized via NTP.
- If a user loses access to their authenticator app they will be locked out. Consider implementing **backup codes** (not included) or an admin unlock procedure.
- A user with a **pending** (unconfirmed) TOTP secret is **not** challenged at login. If they navigate away before confirming, they simply aren't enrolled yet.

---

🔗 **See also**:
- [Device Sessions](11-device-sessions.md) — Manage trusted devices
- [Authentication](03-authentication.md) — All authentication methods
- [Filters](04-filters.md) — Protecting routes
