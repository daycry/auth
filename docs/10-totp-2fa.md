# 🔐 TOTP Two-Factor Authentication

Time-based One-Time Passwords (TOTP) add a powerful second layer of security to your application. After entering their password, users must provide a 6-digit code from an authenticator app such as **Google Authenticator**, **Authy**, or **1Password**.

## 📋 Table of Contents

- [How It Works](#how-it-works)
- [Configuration](#configuration)
- [User Enrollment](#user-enrollment)
- [Login Flow](#login-flow)
- [HasTotp Trait Reference](#hastotp-trait-reference)
- [Brute-Force Lockout & Anti-Replay](#brute-force-lockout--anti-replay)
- [Backup Codes](#backup-codes)
- [Trust This Device](#trust-this-device)
- [UserSecurityController Integration](#usersecuritycontroller-integration)
- [Disabling TOTP](#disabling-totp)
- [Admin TOTP Reset](#admin-totp-reset)
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

> **Only one `login` action is supported at a time.** `Totp2FA` and `Webauthn2FA` (passkey second factor) are therefore **mutually exclusive** as the login second factor — pick one. See [WebAuthn / Passkeys](15-webauthn.md#passkey-as-a-second-factor).

### 2. Set the Issuer Name

In `app/Config/AuthSecurity.php`, set the app name shown in the authenticator app:

```php
public string $totpIssuer = 'My App';

// Number of 30-second steps to accept on either side of the current
// timestamp when verifying codes. 1 = ±30s window (RFC 6238 default,
// recommended). Increase only to tolerate severe clock drift; lowering
// to 0 means clients must be perfectly in sync.
public int $totpWindow = 1;
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
6. `Totp2FA::verify()` first checks the **per-user lockout** (`isLockedOut()`); if the account is locked, the form is redisplayed with the lockout message and no code is checked
7. Otherwise it validates the code (TOTP or a backup code). A wrong code **counts a failed attempt** (`recordFailedAttempt()`) — repeated failures lock the account exactly like password failures
8. On success, `resetOnSuccess()` clears the failure counter, then `completeLogin()` clears the pending action state and creates the session

> **The second factor is now rate-limited.** Before this change, an attacker who had already passed the password step could brute-force the 6-digit code indefinitely. TOTP and backup-code verification now share the same per-user lockout as password login — see [Brute-Force Lockout & Anti-Replay](#brute-force-lockout--anti-replay).

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
// $window defaults to AuthSecurity::$totpWindow when null.
// Enforces single-use (anti-replay): a code whose matched time-step
// has already been consumed is rejected even if still inside the window.
$user->verifyTotpCode(string $code, ?int $window = null): bool

// === Backup codes ===

// Replaces the user's backup codes with a fresh set and returns the
// plain-text codes (only shown once — display them to the user immediately).
$user->generateBackupCodes(int $count = 10): array

// Counts unused backup codes remaining for the user.
$user->backupCodesRemaining(): int

// Verifies + atomically consumes a single backup code.
$user->consumeBackupCode(string $code): bool

// === Removal ===

// Removes the TOTP secret identity AND purges any remaining backup codes.
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

## Brute-Force Lockout & Anti-Replay

The second factor is a 6-digit number — only one million possibilities. Without protection, an attacker who has already passed the password step could simply guess codes in a loop. Two independent mechanisms close that gap.

### 1. Per-user lockout on the second factor

`Totp2FA::verify()` reuses the **same per-user lockout** that guards password login (the `UserLockoutManager` service). This applies to **both** TOTP codes and backup codes — every wrong submission on the 2FA form counts toward the same threshold:

1. On each request, `isLockedOut($user)` is checked first. If the account is locked, the 2FA form is redisplayed with `lang('Auth.userLockedOut', [$minutesLeft])` and the submitted code is **not** evaluated.
2. A wrong TOTP/backup code calls `recordFailedAttempt($user)`, which atomically increments `users.failed_login_count`.
3. Once the count reaches `userMaxAttempts`, the account is locked for `userLockoutTime` seconds (`users.locked_until` is set).
4. A correct code calls `resetOnSuccess($user)`, which clears the counter and unlock timestamp.

This is the exact same flow as password failures — a failed code and a failed password both advance the **one** per-user counter, and the lockout is shared.

| `AuthSecurity` property | Default | Meaning |
|--------------------------|---------|---------|
| `$userMaxAttempts` | `5` | Maximum consecutive failures (password **or** 2FA) before the account is locked. `0` disables lockout entirely. |
| `$userLockoutTime` | `3600` | Seconds the account stays locked after the threshold is reached. |

```php
// app/Config/AuthSecurity.php

public int $userMaxAttempts = 5;    // lock after 5 failed attempts
public int $userLockoutTime = 3600; // stay locked for 1 hour
```

> **Note**: Setting `$userMaxAttempts = 0` disables the lockout for **both** password and 2FA verification. Leave it at a sensible value (the default `5`) in production.

### 2. TOTP codes are single-use within their window (anti-replay)

A TOTP code is valid for the whole acceptance window (`totpWindow` steps either side of "now"). Within that window the same code would normally verify multiple times — a replay risk if a code is intercepted. `verifyTotpCode()` now makes each code **single-use**:

1. `TOTP::verifyAndGetTimestep()` returns the **time-step counter** that matched (or `null` when nothing in the window matches). `TOTP::verify()` is a thin wrapper over it and behaves exactly as before.
2. The last consumed time-step is persisted in the TOTP secret identity's `extra` JSON column as `{"last_timestep": <n>}`.
3. A code whose matched time-step is **less than or equal to** the stored `last_timestep` is rejected — so the same code (and any older code still inside the window) can no longer be replayed.

```text
User submits code "123456"
        ↓
TOTP::verifyAndGetTimestep(...) → 58432109   (matched time-step)
        ↓
last_timestep stored on identity = 58432108
        ↓
58432109 > 58432108 → accepted, last_timestep updated to 58432109
        ↓
User (or attacker) replays "123456" within the same window
        ↓
TOTP::verifyAndGetTimestep(...) → 58432109
        ↓
58432109 <= 58432109 (stored) → REJECTED (already consumed)
```

> **Backup codes remain single-use as well**, enforced separately by marking the consumed row's `used_at` (see [Backup Codes](#backup-codes)). The anti-replay above applies specifically to time-based TOTP codes.

---

## Backup Codes

Backup codes let a user authenticate when their authenticator app is unavailable (lost phone, replaced device). They are **one-time use** — once consumed, the code cannot be reused.

### When they are generated

`UserSecurityController::totpSetupConfirm()` calls `$user->generateBackupCodes()` automatically right after the user confirms their first TOTP code. The plain-text codes are passed once to the success view (`Views/totp_setup_success.php`) — store them, screenshot them, or print them. **They cannot be retrieved later**.

### How they work during login

`Totp2FA::verifyCodeForUser()` first attempts to verify the input as a TOTP code. If that fails, it tries to consume a backup code:

```
User submits "abc123def4"
        ↓
TOTP::verify(...) → false (not a 6-digit code)
        ↓
$user->consumeBackupCode('abc123def4')
        ↓
hash('sha256', 'abc123def4') matches an unused row → success
        ↓
Row marked used_at = NOW() → cannot be used again
```

The 10 codes are 10-character lowercase hex strings — visually distinct from the 6-digit TOTP, so accidental collisions are essentially impossible.

### Storage

| Column | Description |
|--------|-------------|
| `id`, `user_id` | Standard. |
| `code_hash` | SHA-256 of the lowercase plain code. The plaintext never enters the database. |
| `used_at` | Datetime of consumption. Null = unused. |
| `created_at` | Generation timestamp. |

Indexes: `(user_id, used_at)` for fast unused-code lookups; `UNIQUE(user_id, code_hash)` to prevent duplicates.

### Programmatic regeneration

If a user thinks their backup codes are compromised:

```php
$newCodes = $user->generateBackupCodes(10);
return view('account/new_backup_codes', ['codes' => $newCodes]);
```

`generateBackupCodes()` always replaces the entire set — old codes (used or not) are deleted before the new ones are inserted.

### Lifecycle with TOTP

| Action | Effect on backup codes |
|--------|------------------------|
| `enableTotp()` | No effect (codes only generated on `confirmTotp()`). |
| `confirmTotp()` (first time) | Caller (typically `UserSecurityController`) generates the initial set. |
| `disableTotp()` | All codes are purged automatically. |
| `auth:totp reset` (admin) | All codes are purged automatically. |

---

## Trust This Device

Lets the user opt out of repeating 2FA on devices they own. Combines with [Device Sessions](11-device-sessions.md) — the trust flag is stored on the `auth_device_sessions` row, not in a stand-alone cookie payload.

### Enable

```php
// app/Config/AuthSecurity.php

// 30 days is a reasonable default. 0 = feature disabled (always require 2FA).
public int $trustedDeviceLifetime = 30 * DAY;
```

### User flow

1. User logs in with email + password.
2. `Totp2FA::createIdentity()` checks for an `auth_trusted_device` cookie. If the cookie maps to an active `device_sessions` row whose `trusted_until` is in the future and whose `user_id` matches, the 2FA challenge is **skipped entirely**.
3. Otherwise the standard 2FA form is shown — with a **"Trust this device for 30 days"** checkbox if `trustedDeviceLifetime > 0`.
4. After successful verification, if the checkbox was ticked:
   - `device_sessions.trusted_until = now + lifetime` for the current session.
   - The `auth_trusted_device` cookie is set with the device UUID encrypted via `service('encrypter')` (HttpOnly, SameSite=Lax, secure when `App.cookieSecure = true`).
   - An `EVENT_TRUSTED_DEVICE_ADDED` audit entry is recorded.

### Revoking trust

Trust is automatically revoked when:

- `trusted_until` passes (no longer accepted at login).
- The user revokes the device session (`UserSecurityController::revokeSession`) — `logged_out_at` is set, the row no longer matches the trusted-device check.
- The user logs out manually.
- The cookie is deleted by the browser.

To revoke trust programmatically (e.g. when the user changes their password):

```php
/** @var \Daycry\Auth\Models\DeviceSessionModel $devices */
$devices = model(\Daycry\Auth\Models\DeviceSessionModel::class);

foreach ($devices->getAllForUser($user) as $session) {
    $devices->revokeTrust((string) $session->uuid);
}
```

### Security properties

- The cookie carries the device UUID encrypted with the application key. An attacker who steals the cookie alone still needs:
  - The corresponding active `device_sessions` row (joined to the same `user_id`).
  - `trusted_until` to be in the future.
- Stealing only the cookie or only the DB row is not enough.
- Revoking the device session immediately invalidates the trust regardless of cookie validity.

### When NOT to use

- Shared computers / kiosks → keep `trustedDeviceLifetime = 0`.
- Strict regulatory environments (PCI-DSS Level 1, HIPAA in some interpretations) → review whether bypassing 2FA per device is acceptable.

---

## UserSecurityController Integration

Daycry Auth ships with `UserSecurityController` which provides ready-to-use TOTP management endpoints. Register the routes in `app/Config/Routes.php`:

```php
$routes->group('security', ['filter' => 'auth:session', 'namespace' => 'Daycry\Auth\Controllers'], static function ($routes) {
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

## Admin TOTP Reset

When a user has lost both their authenticator and all backup codes, an administrator can reset TOTP from the CLI:

```bash
php spark auth:totp reset -e alice@example.com
```

This:

1. Calls `$user->disableTotp()` — removes the TOTP secret + every backup code.
2. Writes an `EVENT_TOTP_ADMIN_RESET` audit entry with `metadata.initiator = cli`.

The user re-enrolls TOTP from scratch the next time they visit `/security/totp/setup`. See [CLI Commands — `auth:totp`](14-cli-commands.md#auth-totp) for full options.

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
- TOTP codes are valid for a **30-second window** (±`totpWindow` steps tolerance for clock skew). Ensure your server clock is synchronized via NTP.
- **The second factor is brute-force protected.** TOTP and backup-code verification share the same per-user lockout as password login (`userMaxAttempts` / `userLockoutTime`). See [Brute-Force Lockout & Anti-Replay](#brute-force-lockout--anti-replay).
- **TOTP codes are single-use within their acceptance window** (anti-replay): once a code's time-step is consumed, that code — and any older code still inside the window — is rejected. Backup codes are likewise single-use (one-time `used_at`).
- If a user loses access to their authenticator app, they can use a [backup code](#backup-codes) (generated automatically on TOTP confirmation) or an [admin reset](#admin-totp-reset).
- A user with a **pending** (unconfirmed) TOTP secret is **not** challenged at login. If they navigate away before confirming, they simply aren't enrolled yet.
- **`Webauthn2FA` is an alternative second factor.** A passkey can replace TOTP as the login second factor, but only one `login` action is supported — `Totp2FA` and `Webauthn2FA` are mutually exclusive. See [WebAuthn / Passkeys](15-webauthn.md).

---

🔗 **See also**:
- [Device Sessions](11-device-sessions.md) — Manage trusted devices
- [Authentication](03-authentication.md) — All authentication methods
- [WebAuthn / Passkeys](15-webauthn.md) — Passkey second factor (alternative to TOTP)
- [Filters](04-filters.md) — Protecting routes
