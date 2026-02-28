# 🔐 TOTP Two-Factor Authentication

Time-based One-Time Passwords (TOTP) add a powerful second layer of security to your application. After entering their password, users must provide a 6-digit code from an authenticator app such as **Google Authenticator**, **Authy**, or **1Password**.

## 📋 Table of Contents

- [How It Works](#how-it-works)
- [Configuration](#configuration)
- [User Enrollment](#user-enrollment)
- [Login Flow](#login-flow)
- [Managing TOTP in Controllers](#managing-totp-in-controllers)
- [Disabling TOTP](#disabling-totp)
- [UserSecurityController Integration](#usersecuritycontroller-integration)
- [Testing TOTP](#testing-totp)

---

## How It Works

```
User enters email + password
        ↓
Credentials verified ✅
        ↓
System detects TOTP action is required
        ↓
User is shown a "Enter your 6-digit code" form
        ↓
User opens authenticator app → copies code
        ↓
Code verified against TOTP secret ✅
        ↓
Session created — user is logged in
```

The TOTP secret is stored permanently in `auth_users_identities` with type `totp_secret`. Each login generates a temporary marker (`totp`) that is removed once verified.

---

## Configuration

### 1. Enable the TOTP Post-Login Action

In `app/Config/Auth.php`, set the `login` action to `Totp2FA`:

```php
use Daycry\Auth\Authentication\Actions\Totp2FA;

public array $actions = [
    'register' => null,
    'login'    => Totp2FA::class, // Require TOTP on every login
];
```

> **Note**: This only applies to users who have TOTP enabled. Users without a TOTP secret skip the 2FA step and log in directly.

### 2. Verify Dependencies

The TOTP library is included automatically. No extra `composer require` needed.

---

## User Enrollment

TOTP must be enabled per-user before they can use it. The typical flow is:

1. User navigates to their security settings
2. They click "Enable Two-Factor Authentication"
3. They scan a QR code with their authenticator app
4. They enter a verification code to confirm the setup

### Enable TOTP for a User

```php
<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use Daycry\Auth\Libraries\TOTP;

class SecurityController extends BaseController
{
    /**
     * Step 1: Show the QR code for scanning.
     */
    public function enableTotpView()
    {
        $user = auth()->user();

        if ($user->hasTotpSecret()) {
            return redirect()->to('security')->with('info', 'TOTP is already enabled.');
        }

        // Generate a new secret and store it temporarily in the session
        $totp   = new TOTP();
        $secret = $totp->generateSecret();

        session()->set('totp_pending_secret', $secret);

        // Build a QR code URL for the user's authenticator app
        $qrCodeUrl = $totp->getQRCodeUrl(
            issuer:   config('App')->appName,
            account:  $user->email,
            secret:   $secret
        );

        return view('security/totp_setup', [
            'qrCodeUrl' => $qrCodeUrl,
            'secret'    => $secret, // Show as plain text fallback
        ]);
    }

    /**
     * Step 2: Verify the first code — confirm the user scanned correctly.
     */
    public function enableTotpAction()
    {
        $user   = auth()->user();
        $secret = session()->get('totp_pending_secret');
        $code   = $this->request->getPost('code');

        if ($secret === null) {
            return redirect()->to('security/totp/enable')->with('error', 'Session expired. Please try again.');
        }

        $totp = new TOTP();

        if (! $totp->verify($code, $secret)) {
            return redirect()->back()->with('error', 'Invalid code. Please try again.');
        }

        // Code is valid — permanently enable TOTP for this user
        $user->enableTotp($secret);

        session()->remove('totp_pending_secret');

        return redirect()->to('security')->with('message', 'Two-factor authentication is now enabled.');
    }
}
```

### Example Setup View

```html
<!-- app/Views/security/totp_setup.php -->
<div class="container mt-4">
    <h2>Enable Two-Factor Authentication</h2>

    <div class="card mb-4">
        <div class="card-body">
            <h5>Step 1: Scan this QR Code</h5>
            <p>Open your authenticator app (Google Authenticator, Authy, etc.) and scan:</p>

            <!-- Use a QR code library, e.g. chillerlan/php-qrcode or an online API -->
            <img src="https://api.qrserver.com/v1/create-qr-code/?data=<?= urlencode($qrCodeUrl) ?>&size=200x200"
                 alt="QR Code">

            <p class="mt-2 text-muted">
                Can't scan? Enter this code manually: <code><?= esc($secret) ?></code>
            </p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h5>Step 2: Enter the 6-digit code from your app</h5>
            <?= form_open('security/totp/enable') ?>
                <div class="mb-3">
                    <input type="text"
                           name="code"
                           class="form-control"
                           maxlength="6"
                           placeholder="000000"
                           autocomplete="one-time-code"
                           required>
                </div>
                <button type="submit" class="btn btn-success">Confirm &amp; Enable</button>
            <?= form_close() ?>
        </div>
    </div>
</div>
```

---

## Login Flow

Once TOTP is enabled for a user, the flow is handled **automatically** by the `Totp2FA` action. You do not need to modify your `LoginController`.

### What Happens Automatically

1. User submits login form (email + password)
2. `Session::check()` verifies credentials
3. Session detects the `Totp2FA` action is configured
4. User is **redirected to the action show page** (not logged in yet)
5. The built-in view asks for the 6-digit code
6. `ActionController::verify()` validates the TOTP code
7. On success, the session is created and the user is redirected

### Default TOTP Views

The library provides default views. You can override them in `app/Config/Auth.php`:

```php
public array $views = [
    // ... other views
    'action_totp_show'   => '\Daycry\Auth\Views\totp_show',
    'action_totp_verify' => '\Daycry\Auth\Views\totp_verify',
];
```

### Custom TOTP Verify View

```html
<!-- app/Views/auth/totp_verify.php -->
<div class="container mt-5" style="max-width: 400px;">
    <div class="card shadow-sm">
        <div class="card-body">
            <h4 class="card-title text-center">🔐 Two-Factor Authentication</h4>
            <p class="text-muted text-center">Enter the 6-digit code from your authenticator app.</p>

            <?php if (session('error')): ?>
                <div class="alert alert-danger"><?= session('error') ?></div>
            <?php endif ?>

            <?= form_open(route_to('auth-action-verify')) ?>
                <div class="mb-3">
                    <input type="text"
                           name="code"
                           class="form-control form-control-lg text-center"
                           maxlength="6"
                           placeholder="000000"
                           autocomplete="one-time-code"
                           autofocus>
                </div>
                <button type="submit" class="btn btn-primary w-100">Verify</button>
            <?= form_close() ?>
        </div>
    </div>
</div>
```

---

## Managing TOTP in Controllers

### Check if a User Has TOTP Enabled

```php
$user = auth()->user();

if ($user->hasTotpSecret()) {
    echo "TOTP is enabled for this user.";
} else {
    echo "TOTP is not configured.";
}
```

### The `HasTotp` Trait Methods

The `User` entity uses the `HasTotp` trait, which provides:

```php
// Check if TOTP secret exists
$user->hasTotpSecret(): bool

// Enable TOTP with a given secret (call after verifying the first code)
$user->enableTotp(string $secret): void

// Disable TOTP (removes the stored secret)
$user->disableTotp(): void

// Verify a given 6-digit code against the user's secret
$user->verifyTotp(string $code): bool
```

### Example: Security Dashboard

```php
public function securityIndex()
{
    $user = auth()->user();

    return view('security/index', [
        'totpEnabled'  => $user->hasTotpSecret(),
        'deviceCount'  => count($user->getDeviceSessions()),
    ]);
}
```

---

## Disabling TOTP

```php
public function disableTotpAction()
{
    $user     = auth()->user();
    $password = $this->request->getPost('current_password');

    // Always require password confirmation before disabling 2FA
    /** @var \Daycry\Auth\Authentication\Passwords $passwords */
    $passwords = service('passwords');

    if (! $passwords->verify($password, $user->getPasswordHash())) {
        return redirect()->back()->with('error', 'Incorrect password.');
    }

    $user->disableTotp();

    return redirect()->to('security')->with('message', 'Two-factor authentication has been disabled.');
}
```

---

## UserSecurityController Integration

Daycry Auth ships with `UserSecurityController` that provides ready-to-use TOTP management endpoints. Add the routes in `app/Config/Routes.php`:

```php
$routes->group('security', ['filter' => 'session', 'namespace' => 'Daycry\Auth\Controllers'], static function ($routes) {
    // TOTP management
    $routes->get('totp/enable',  'UserSecurityController::totpEnableView',   ['as' => 'totp-enable']);
    $routes->post('totp/enable', 'UserSecurityController::totpEnableAction');
    $routes->post('totp/disable','UserSecurityController::totpDisableAction', ['as' => 'totp-disable']);
});
```

---

## Testing TOTP

```php
<?php

namespace Tests\Authentication;

use Tests\Support\DatabaseTestCase;
use Daycry\Auth\Libraries\TOTP;

class TotpTest extends DatabaseTestCase
{
    public function testEnableAndVerifyTotp(): void
    {
        $user = $this->createUser();

        $totp   = new TOTP();
        $secret = $totp->generateSecret();

        // Simulate enrollment
        $user->enableTotp($secret);

        $this->assertTrue($user->hasTotpSecret());
    }

    public function testVerifyInvalidCodeFails(): void
    {
        $user = $this->createUser();

        $totp   = new TOTP();
        $secret = $totp->generateSecret();
        $user->enableTotp($secret);

        // An obviously wrong code
        $this->assertFalse($user->verifyTotp('000000'));
    }

    public function testDisableTotpRemovesSecret(): void
    {
        $user = $this->createUser();

        $totp   = new TOTP();
        $secret = $totp->generateSecret();
        $user->enableTotp($secret);
        $user->disableTotp();

        $this->assertFalse($user->hasTotpSecret());
    }
}
```

---

## Security Tips

```{admonition} Best Practices
:class: tip

- **Always require password confirmation** before enabling or disabling TOTP.
- Explain to users that **losing access to their authenticator app** could lock them out — consider implementing backup codes.
- TOTP codes are valid for a 30-second window (with a configurable clock skew tolerance). Ensure your server clock is synchronized (NTP).
- The TOTP secret is stored in the `auth_users_identities` table. Make sure your database access is properly secured.
```

---

🔗 **See also**:
- [Device Sessions](11-device-sessions.md) — Manage trusted devices
- [Authentication](03-authentication.md) — All authentication methods
- [Filters](04-filters.md) — Protecting routes
