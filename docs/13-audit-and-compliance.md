# 🛡️ Audit Log & Compliance

This guide covers Daycry Auth's compliance and observability features:

- **Granular audit log** — separate event-level table for sensitive account changes
- **Suspicious login detection** — IP / device anomaly alerts
- **Compromised-password recheck on login** — opt-in HIBP recheck
- **Password history** — prevent reuse of recent passwords
- **Password rotation policy** — force resets after a configurable age
- **GDPR helpers** — data export and account anonymization

These features are independent — enable only the ones you need.

## 📋 Index

- [Audit Log](#audit-log)
- [Suspicious Login Detection](#suspicious-login-detection)
- [Compromised-Password Recheck on Login](#compromised-password-recheck-on-login)
- [Password History (No Reuse)](#password-history-no-reuse)
- [Password Rotation Policy](#password-rotation-policy)
- [GDPR Export & Anonymization](#gdpr-export--anonymization)
- [Quick Configuration Reference](#quick-configuration-reference)

---

## Audit Log

A second log table — `auth_audit_logs` — captures **account-level** events that need long-term traceability, separate from `auth_logs` (request-level activity) and `auth_logins` (login attempts).

### What gets recorded

Every event written by `\Daycry\Auth\Services\AuditLogger` lands in `auth_audit_logs` with:

| Column | Description |
|--------|-------------|
| `user_id` | The user the event affects (null for system-level events). |
| `actor_id` | The user that triggered the event (admin or self). Defaults to `user_id`. |
| `event_type` | One of the `EVENT_*` constants on `AuditLogger`. |
| `ip_address` | Resolved from the active `IncomingRequest` (null in CLI). |
| `user_agent` | Resolved from the active `IncomingRequest`. |
| `metadata` | Free-form JSON with extra context. |
| `created_at` | Datetime stamp. |

### Built-in events

`AuditLogger` defines the canonical event identifiers — use these constants instead of free-form strings:

| Constant | When it fires |
|----------|---------------|
| `EVENT_TOTP_ENABLED` | `HasTotp::confirmTotp()` succeeds |
| `EVENT_TOTP_DISABLED` | `HasTotp::disableTotp()` |
| `EVENT_TOTP_ADMIN_RESET` | `auth:totp reset` CLI command |
| `EVENT_PASSWORD_CHANGED` | Password change via `ForcePasswordResetController` |
| `EVENT_PASSWORD_RESET` | Password reset via `PasswordResetController` |
| `EVENT_USER_LOCKED` | `UserLockoutManager` triggers a lockout |
| `EVENT_USER_UNLOCKED` | Lockout expires automatically |
| `EVENT_GROUP_ASSIGNED` / `EVENT_GROUP_REVOKED` | `Authorizable::addGroup()` / `removeGroup()` |
| `EVENT_PERMISSION_GRANTED` / `EVENT_PERMISSION_REVOKED` | `Authorizable::addPermission()` / `removePermission()` |
| `EVENT_TOKEN_REVOKED` | `AccessTokenRepository::softRevokeAccessToken()` / `softRevokeAllAccessTokens()` |
| `EVENT_REFRESH_TOKEN_REVOKED` | `JwtController::logout()` / `JwtTokenRepository::softRevokeRefreshToken()` |
| `EVENT_TRUSTED_DEVICE_ADDED` | User ticks "Trust this device" on 2FA |
| `EVENT_SUSPICIOUS_LOGIN` | `SuspiciousLoginDetector` flags a login |
| `EVENT_USER_ANONYMIZED` | `auth:gdpr anonymize` CLI command |
| `EVENT_OAUTH_LINKED` / `EVENT_OAUTH_UNLINKED` | Reserved for application use |
| `EVENT_EMAIL_CHANGE_REQUEST` / `EVENT_EMAIL_CHANGED` | Reserved for application use |
| `EVENT_TRUSTED_DEVICE_REMOVED` | Reserved for application use |

### Recording your own events

`AuditLogger::record()` is the single entry point — failures are logged at `warning` level but never propagate, so audit failure cannot break the user-facing flow:

```php
use Daycry\Auth\Services\AuditLogger;

(new AuditLogger())->record(
    AuditLogger::EVENT_PASSWORD_CHANGED,
    userId: $user->id,
    metadata: ['source' => 'profile_form'],
    actorId: auth()->id() // optional — defaults to $userId
);
```

### Querying the audit log

Use `AuditLogModel` directly or via `auth:audit`:

```php
use Daycry\Auth\Models\AuditLogModel;

/** @var AuditLogModel $audit */
$audit = model(AuditLogModel::class);

// Last 50 events for a user
$entries = $audit->recentForUser($userId, 50);

// All TOTP-enabled events in the last 30 days
$entries = $audit->recentByType(
    AuditLogger::EVENT_TOTP_ENABLED,
    \CodeIgniter\I18n\Time::now()->subDays(30),
);

// Each entry's metadata is decoded automatically
foreach ($entries as $entry) {
    echo $entry->event_type . ' at ' . $entry->created_at . "\n";
    var_dump($entry->getMetadata());
}
```

### CLI: `auth:audit`

```bash
# Last 7 days, 100 rows max (default)
php spark auth:audit

# Last 24 hours
php spark auth:audit --since=24h

# Filter by user
php spark auth:audit --user=alice@example.com

# Filter by event
php spark auth:audit --type=totp.enabled --since=30d --limit=200
```

`--since` accepts `Ns`, `Nm`, `Nh`, `Nd`, `Nw` (e.g. `90m`, `2d`, `1w`).

### Database

The migration `2026-05-07-000002_create_audit_logs_table.php` creates the table with composite indexes on `(user_id, created_at)` and `(event_type, created_at)`. The table name is configurable via `Auth::$tables['audit_logs']` (defaults to `auth_audit_logs`).

---

## Suspicious Login Detection

After every successful login, `SuspiciousLoginDetector` compares the new login against the user's recent history. When something looks off, it fires the `suspicious-login` event and writes an audit entry — your application listens to the event to deliver an email / Slack / push notification.

### Enable

```php
// app/Config/AuthSecurity.php
public bool $suspiciousLoginAlerts = true;
```

### Detection signals

Each login is checked against the user's history (last 30 days). The current implementation flags:

| Flag | Source | Test |
|------|--------|------|
| `new_ip` | `auth_logins` | No successful login from this IP in the lookback window |
| `new_device` | `auth_device_sessions` | No prior session row with this exact User-Agent |

The detector is forward-compatible — additional signals (geo-IP, ASN reputation, time-of-day, JA3 fingerprint) can be added without changing the integration point.

### React with an event listener

```php
// app/Config/Events.php
use CodeIgniter\Events\Events;
use Daycry\Auth\Entities\User;

Events::on('suspicious-login', static function (User $user, array $flags, string $ip, string $ua): void {
    helper('email');

    $email = emailer()
        ->setFrom(setting('Email.fromEmail'), (string) setting('Email.fromName'))
        ->setTo($user->email)
        ->setSubject(lang('Auth.suspiciousLoginSubject'))
        ->setMessage(view('Daycry\\Auth\\Views\\Email\\suspicious_login_alert', [
            'user'      => $user,
            'flags'     => $flags,
            'ipAddress' => $ip,
            'userAgent' => $ua,
            'date'      => \CodeIgniter\I18n\Time::now()->toDateTimeString(),
        ]));

    $email->send(false);
});
```

The package ships `Views/Email/suspicious_login_alert.php` as a starting template — copy it to `app/Views/` and customize.

### What lands in the audit log

Every flagged login also writes an `EVENT_SUSPICIOUS_LOGIN` row with the flags and request context as metadata, so you have a permanent record even if the email fails to send.

---

## Compromised-Password Recheck on Login

`PwnedValidator` is normally only used during registration / password change. Enable `recheckPwnedOnLogin` to also test the **current** password against [HaveIBeenPwned](https://haveibeenpwned.com/) on every successful login — useful for catching passwords that were safe when the account was created but appeared in a breach later.

### Enable

```php
// app/Config/AuthSecurity.php
public bool $recheckPwnedOnLogin = true;

// HIBP timeouts (already documented in 02-configuration.md)
public float $pwnedPasswordsConnectTimeout = 1.0;
public float $pwnedPasswordsTimeout        = 3.0;
```

### Behaviour

1. Login proceeds normally; password is verified.
2. After verification, the live password is hashed (SHA-1 prefix per HIBP's k-anonymity API) and queried.
3. If found in a breach, the user's `email_password` identity gets `force_reset = 1` — the next request bounces them through the force-reset flow (no immediate logout, so they finish what they were doing first).
4. If HIBP is unreachable / slow, the failure is logged at `warning` and login proceeds — **the recheck never blocks login**.

### Cost

One outbound HTTPS call per login when enabled. Tune `pwnedPasswordsTimeout` (default 3s) based on your latency budget. Disabled by default for that reason.

---

## Password History (No Reuse)

Forces users to pick a password that does not match any of their last N hashes. Required for SOC 2 / ISO 27001 environments.

### Enable

```php
// app/Config/AuthSecurity.php
public int $passwordHistorySize = 5; // remember last 5
```

Add the validator to your password validator chain:

```php
public array $passwordValidators = [
    \Daycry\Auth\Authentication\Passwords\CompositionValidator::class,
    \Daycry\Auth\Authentication\Passwords\NothingPersonalValidator::class,
    \Daycry\Auth\Authentication\Passwords\DictionaryValidator::class,
    \Daycry\Auth\Authentication\Passwords\HistoryValidator::class, // <— add this
];
```

### How it works

1. On every persisted password change, `PasswordChangeRecorder` writes the *previous* hash into `auth_password_history` (one row per change, indexed on `user_id, created_at`).
2. Older entries beyond the configured retention window are pruned automatically.
3. On the **next** password change, `HistoryValidator` runs `password_verify()` against each retained hash — if any match, the new password is rejected with `Auth.passwordHistoryReuse`.

### Hooks

The recorder is wired automatically into:

- `PasswordResetController::resetAction()` — token-based reset.
- `ForcePasswordResetController::resetAction()` — current-password gated reset.

For custom password change flows (e.g. an admin-side password set), call:

```php
use Daycry\Auth\Services\PasswordChangeRecorder;

$previousHash = $user->password_hash;
$user->setPassword($newPassword);
$userModel->save($user);

(new PasswordChangeRecorder())->record($user, $previousHash);
```

### Database

Migration `2026-05-07-000005_create_password_history_table.php`. Table is configurable via `Auth::$tables['password_history']`.

---

## Password Rotation Policy

Forces a password reset once `password_changed_at` is older than `passwordMaxAge` seconds.

### Enable

```php
// app/Config/AuthSecurity.php
public int $passwordMaxAge = 90 * DAY; // 90 days
```

### Wire the filter

The `password-age` alias is registered automatically by `Registrar`. Apply it alongside `auth` (or `session`) on the routes that should enforce rotation:

```php
// app/Config/Routes.php
$routes->group('app', ['filter' => 'session,password-age'], static function ($routes) {
    $routes->get('/dashboard', 'Dashboard::index');
});
```

### Behaviour

1. The filter runs after authentication.
2. If `password_changed_at` is null (never recorded) the user is left alone — older accounts grandfathered in.
3. If `password_changed_at` is older than `passwordMaxAge`, the user's `email_password` identity gets `force_reset = 1` and the request redirects to `Config\Auth::forcePasswordResetRedirect()` with `Auth.passwordExpired`.

### Database

Migration `2026-05-07-000006_add_password_changed_at_to_users.php` adds the column. `PasswordChangeRecorder` (same service used by Password History) stamps it on every persisted password change.

> **Migrating existing accounts**: rows already in `users` will have `password_changed_at = NULL`. Either grandfather them (the filter ignores nulls) or run a one-shot `UPDATE users SET password_changed_at = created_at WHERE password_changed_at IS NULL;` if you want to start the rotation clock immediately.

---

## GDPR Export & Anonymization

Two CLI subcommands satisfy the most common GDPR data-subject requests.

### `auth:gdpr export`

Emits a JSON document covering everything the system knows about a user:

```bash
# To stdout
php spark auth:gdpr export -e alice@example.com

# Save to a file (recommended for large histories)
php spark auth:gdpr export -e alice@example.com -o /tmp/alice.json
```

The payload includes:

- `user` — identity row (id, uuid, username, email, active, lockout state, password rotation timestamp).
- `identities` — every row in `auth_users_identities`, with **secrets redacted**:
  - bcrypt password hashes are marked `<redacted: bcrypt hash>`
  - access / refresh tokens marked `<redacted: hashed token>`
  - other types (magic link, OAuth, TOTP secret) include the raw `secret` column (these are short-lived or already encrypted)
- `device_sessions` — full history (active + terminated).
- `login_history` — last 500 entries from `auth_logins`.
- `audit_log` — last 500 entries from `auth_audit_logs`, with metadata decoded.
- `password_history` — count only (the bcrypt hashes themselves are not personal data and exposing them would defeat the purpose of revoking a token).
- `backup_codes` — counts of `remaining` / `used` (never the raw codes).

### `auth:gdpr anonymize`

Permanent, irreversible — keeps the user row to preserve foreign-key integrity but replaces personal fields with placeholders:

```bash
php spark auth:gdpr anonymize -e alice@example.com
```

What happens:

1. **Identities, device sessions, password history, backup codes** — `DELETE` rows for the user.
2. **User row** — `username` becomes `deleted_<id>`, `active = 0`, lockout / rotation fields cleared.
3. **Audit log** — final `EVENT_USER_ANONYMIZED` entry with `actor_id = user_id` and `metadata.initiator = cli`.

The command prompts for confirmation before any destructive action. Login attempts (`auth_logins`) are kept by default — they are append-only events, not personal data of the deleted user. Adapt to your interpretation of GDPR by extending `GdprCommand::anonymizeAction()`.

### When to use export vs anonymize

| User asks for | Use |
|---------------|-----|
| "What data do you have on me?" (Article 15 access) | `export` |
| "Send me my data" (Article 20 portability) | `export -o /path/to.json` and email the file |
| "Delete me" (Article 17 erasure) | `anonymize` (preserves FK integrity) or hard `auth:user delete` (full deletion, may break logs / FK references) |

---

## Quick Configuration Reference

All compliance features are opt-in. Here is the full set of new toggles in `app/Config/AuthSecurity.php`:

```php
namespace Config;

use Daycry\Auth\Config\AuthSecurity as AuthSecurityConfig;

class AuthSecurity extends AuthSecurityConfig
{
    // Compromised-password recheck (1 outbound HTTP call per login)
    public bool $recheckPwnedOnLogin = true;

    // Suspicious login detection (writes audit + fires event)
    public bool $suspiciousLoginAlerts = true;

    // Password history — last 5 hashes
    public int $passwordHistorySize = 5;

    // Force password rotation every 90 days
    public int $passwordMaxAge = 90 * DAY;

    // Add HistoryValidator to the chain
    public array $passwordValidators = [
        \Daycry\Auth\Authentication\Passwords\CompositionValidator::class,
        \Daycry\Auth\Authentication\Passwords\NothingPersonalValidator::class,
        \Daycry\Auth\Authentication\Passwords\DictionaryValidator::class,
        \Daycry\Auth\Authentication\Passwords\HistoryValidator::class,
    ];
}
```

And the routing-level wiring in `app/Config/Routes.php`:

```php
$routes->group('app', ['filter' => 'session,password-age'], static function ($routes) {
    // Routes that should enforce password rotation
});
```
