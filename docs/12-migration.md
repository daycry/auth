# 🔄 Migration Guide

This document summarises breaking changes between major versions and how to upgrade. For the full per-release changelog, see [`CHANGELOG.md`](../CHANGELOG.md).

## 📋 Index

- [Upgrading to the next release (`Unreleased`)](#upgrading-to-the-next-release-unreleased)
- [Upgrading to v5.x](#upgrading-to-v5x)
- [Upgrading to v4.x — `Config\Auth` split](#upgrading-to-v4x--configauth-split)
- [General upgrade checklist](#general-upgrade-checklist)

---

## Upgrading to the next release (`Unreleased`)

The `Unreleased` section in `CHANGELOG.md` adds 13 features and a handful of internal improvements. **No breaking changes** — all new behaviour is opt-in.

### Required steps

1. **Run migrations** — six new migrations ship with this release:

   | Migration | Adds |
   |-----------|------|
   | `2026-05-07-000001_add_identities_user_type_revoked_index` | Composite index on `auth_identities(user_id, type, revoked_at)` |
   | `2026-05-07-000002_create_audit_logs_table` | `auth_audit_logs` |
   | `2026-05-07-000003_create_totp_backup_codes_table` | `auth_totp_backup_codes` |
   | `2026-05-07-000004_add_trusted_until_to_device_sessions` | `auth_device_sessions.trusted_until` |
   | `2026-05-07-000005_create_password_history_table` | `auth_password_history` |
   | `2026-05-07-000006_add_password_changed_at_to_users` | `users.password_changed_at` |

   ```bash
   php spark migrate --all
   ```

2. **Rename test helpers (deprecated, BC kept)** — if your tests use the typo'd helpers, the corrected names are now available; the old ones still work as deprecated aliases.

   | Before (deprecated) | After |
   |---------------------|-------|
   | `$this->inkectMockAttributes(...)` | `$this->injectMockAttributes(...)` |
   | `$this->inkectMockAttributesSecurity(...)` | `$this->injectMockAttributesSecurity(...)` |
   | `$this->inkectMockAttributesOAuth(...)` | `$this->injectMockAttributesOAuth(...)` |

   The deprecated names will be removed in v6.

### Optional — opt-in to new features

Each of these defaults to "off / unchanged" — adopt only what fits your security posture.

| Feature | Where to enable | Reference |
|---------|-----------------|-----------|
| Throttle access-token `last_used_at` writes | `AuthSecurity::$tokenLastUsedThrottle = 60` | [Authentication — Access Token](03-authentication.md#access-token-authenticator) |
| Concurrent session limit | `Auth::$maxConcurrentSessions = 5` | [Device Sessions — Concurrent Limit](11-device-sessions.md#concurrent-session-limit) |
| Trusted devices (2FA bypass) | `AuthSecurity::$trustedDeviceLifetime = 30 * DAY` | [TOTP — Trust This Device](10-totp-2fa.md#trust-this-device) |
| TOTP backup codes | (automatic on TOTP confirmation) | [TOTP — Backup Codes](10-totp-2fa.md#backup-codes) |
| Compromised-password recheck on login | `AuthSecurity::$recheckPwnedOnLogin = true` | [Audit & Compliance](13-audit-and-compliance.md#compromised-password-recheck-on-login) |
| Suspicious login alerts | `AuthSecurity::$suspiciousLoginAlerts = true` + listener | [Audit & Compliance](13-audit-and-compliance.md#suspicious-login-detection) |
| Password history (no reuse) | `AuthSecurity::$passwordHistorySize = 5` + add `HistoryValidator` | [Audit & Compliance](13-audit-and-compliance.md#password-history-no-reuse) |
| Password rotation policy | `AuthSecurity::$passwordMaxAge = 90 * DAY` + apply `password-age` filter | [Audit & Compliance](13-audit-and-compliance.md#password-rotation-policy) |
| API token scope enforcement | Apply `token-scope:` filter on routes | [Filters — Token Scope](04-filters.md#3-token-scope-filter-token-scope) |
| Login activity feed | Wire `UserSecurityController::loginActivity` route | [Controllers — `loginActivity()`](05-controllers.md#loginactivity) |

### What runs automatically (no action needed)

- The audit log starts capturing events immediately for: TOTP enable/disable, password changes, lockouts, group/permission grants & revokes, token revocations, JWT logout. Use `auth:audit` to read.
- `OauthManager::handleCallback()` now compares the OAuth `state` with `hash_equals()` (timing-safe) — drop-in replacement.
- `UserLockoutManager::recordFailedAttempt()` now increments `failed_login_count` atomically — drop-in replacement.
- `DeviceSessionRecorder` no longer propagates DB errors — they are logged and swallowed so a misconfigured tracking table can't break login.

---

## Upgrading to v5.x

### What changed

- `OauthManager` now delegates all identity CRUD to a new `OAuthTokenRepository`.
- `ProfileResolverFactory::create()` accepts an optional `array $providerConfig` second argument.
- New OAuth events fire from `OauthManager::handleCallback()`:
  - `oauth-login` — `(User $user, string $providerName)`
  - `oauth-profile-fetched` — `(User $user, string $providerName, array $profileData)`
- `extra` JSON on OAuth identities now stores `scopes_granted` and `profile_fetched_at` alongside the existing `refresh_token` and `profile`.

### What you must do

| If your code… | Do this |
|---|---|
| Calls `model(UserIdentityModel::class)` to find an OAuth identity | Inject `OAuthTokenRepository` and use `findByUserAndProvider()` / `findByProviderAndSocialId()` |
| Listens for OAuth login via `auth-login` or similar custom event | Switch to the new `oauth-login` event (see [`docs/07-logging.md`](07-logging.md)) |
| Uses `'oauth_' . $provider` string concatenation | Use `IdentityType::oauthProvider($name)` |
| Relied on the legacy plain-string format in the `extra` column | No action required — `parseExtra()` handles both legacy and JSON formats |

No database migrations are required for the v4 → v5 transition. Existing `extra` columns continue to work unchanged.

---

## Upgrading to v4.x — `Config\Auth` split

### What changed

`Config\Auth` was split into three classes to keep concerns separate. Properties moved according to this table:

| Property | Old class | New class |
|----------|-----------|-----------|
| `$minimumPasswordLength`, `$passwordValidators`, `$maxSimilarity` | `Auth` | `AuthSecurity` |
| `$hashAlgorithm`, `$hashCost`, `$hashMemoryCost`, `$hashTimeCost`, `$hashThreads` | `Auth` | `AuthSecurity` |
| `$supportOldDangerousPassword` | `Auth` | `AuthSecurity` |
| `$recordLoginAttempt`, `$recordActiveDate`, `$enableLogs` | `Auth` | `AuthSecurity` |
| `$userMaxAttempts`, `$userLockoutTime` | `Auth` | `AuthSecurity` |
| `$enableInvalidAttempts`, `$maxAttempts`, `$timeBlocked` | `Auth` | `AuthSecurity` |
| `$limitMethod`, `$requestLimit`, `$timeLimit` | `Auth` | `AuthSecurity` |
| `$accessTokenEnabled`, `$unusedAccessTokenLifetime`, `$strictApiAndAuth` | `Auth` | `AuthSecurity` |
| `$allowMagicLinkLogins`, `$magicLinkLifetime` | `Auth` | `AuthSecurity` |
| `$passwordResetLifetime`, `$jwtRefreshLifetime` | `Auth` | `AuthSecurity` |
| `$totpIssuer`, `$permissionCacheEnabled`, `$permissionCacheTTL` | `Auth` | `AuthSecurity` |
| `RECORD_LOGIN_ATTEMPT_*` constants | `Auth` | `AuthSecurity` |
| `$providers` | `Auth` | `AuthOAuth` |

Constructor signatures changed:

- `Passwords` and `BaseValidator` accept `AuthSecurity` instead of `Auth`. Custom password validators extending `BaseValidator` must update their type hints.
- `OauthManager` accepts `AuthOAuth` instead of `Auth`.

### What you must do

**Step 1.** Create the two new config files in `app/Config/`:

```php
// app/Config/AuthSecurity.php
namespace Config;

use Daycry\Auth\Config\AuthSecurity as AuthSecurityConfig;

class AuthSecurity extends AuthSecurityConfig
{
    // Move every customised security/lockout/password property here.
    public int $minimumPasswordLength = 10;
    public int $userMaxAttempts        = 5;
    // ...
}
```

```php
// app/Config/AuthOAuth.php
namespace Config;

use Daycry\Auth\Config\AuthOAuth as AuthOAuthConfig;

class AuthOAuth extends AuthOAuthConfig
{
    public array $providers = [
        // Move your existing $providers array verbatim from app/Config/Auth.php.
    ];
}
```

**Step 2.** Remove the moved properties from `app/Config/Auth.php`. Anything not in the table above stays in `Auth`.

**Step 3.** Search-and-replace `setting('Auth.X')` → `setting('AuthSecurity.X')` (or `setting('AuthOAuth.X')`) for every property listed above. Common offenders:

| Before | After |
|---|---|
| `setting('Auth.recordLoginAttempt')` | `setting('AuthSecurity.recordLoginAttempt')` |
| `setting('Auth.requestLimit')` | `setting('AuthSecurity.requestLimit')` |
| `setting('Auth.userMaxAttempts')` | `setting('AuthSecurity.userMaxAttempts')` |
| `setting('Auth.totpIssuer')` | `setting('AuthSecurity.totpIssuer')` |
| `setting('Auth.providers')` | `setting('AuthOAuth.providers')` |

**Step 4.** Update custom password validators:

```php
use Daycry\Auth\Authentication\Passwords\BaseValidator;
use Daycry\Auth\Config\AuthSecurity;

class MyValidator extends BaseValidator
{
    public function __construct(AuthSecurity $config)
    {
        parent::__construct($config);
    }
}
```

**Step 5.** Run the test suite — the type system will catch most missed renames.

---

## General upgrade checklist

After any major version bump:

1. `composer update daycry/auth`
2. `php spark migrate --all` — applies any new migrations.
3. `composer test` — runs PHPUnit + code-style.
4. Review your `app/Config/Auth.php`, `app/Config/AuthSecurity.php`, `app/Config/AuthOAuth.php` against the published versions for any new options worth adopting (e.g. `permissionCacheEnabled`, `tokenLastUsedThrottle`).
5. Check `CHANGELOG.md` for any non-breaking deprecations to plan ahead of v6.
