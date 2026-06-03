# 🔄 Migration Guide

This document summarises breaking changes between major versions and how to upgrade. For the full per-release changelog, see [`CHANGELOG.md`](../CHANGELOG.md).

## 📋 Index

- [Upgrading to this release — token-version revocation & hashed ephemeral tokens](#upgrading-to-this-release--token-version-revocation--hashed-ephemeral-tokens)
- [Upgrading to the next release (`Unreleased`)](#upgrading-to-the-next-release-unreleased)
- [Upgrading to v5.x](#upgrading-to-v5x)
- [Upgrading to v4.x — `Config\Auth` split](#upgrading-to-v4x--configauth-split)
- [General upgrade checklist](#general-upgrade-checklist)

---

## Upgrading to this release — token-version revocation & hashed ephemeral tokens

This release ships one additive migration and several **behavioural** changes that upgraders must be aware of. There are **no destructive schema changes** — the only schema delta is the additive `users.token_version` column.

### Required steps

1. **Run migrations** — one new migration ships with this release:

   | Migration | Adds |
   |-----------|------|
   | `2026-05-08-000001_add_jwt_token_version_to_users` | `users.token_version` (`int`, `NOT NULL`, default `0`) |

   ```bash
   php spark migrate --all
   ```

   The column backs JWT access-token revocation (see below). `down()` simply drops the column.

2. **Schedule `php spark auth:purge`** — `AuthSecurity::$rememberMePurgeChance` now defaults to **`0`** (was `20`). Expired remember-me tokens are now rejected at validation time regardless, so the old probabilistic on-login purge is pure table maintenance. Move that maintenance to a scheduled command instead:

   ```bash
   # Run on a schedule (cron / daycry/jobs)
   php spark auth:purge            # purges expired remember-me tokens + terminated device sessions older than 30 days
   php spark auth:purge --days 7   # use a 7-day retention window for terminated device sessions
   ```

   `auth:purge` (command group `Auth`) removes expired rows from `auth_remember_tokens` and terminated `auth_device_sessions` rows older than `--days` (default `30`). If you want to keep inline purging, set `AuthSecurity::$rememberMePurgeChance` back to a non-zero value — but a scheduled command is recommended.

### Behavioural changes you must know about

| Change | What it means for upgraders |
|--------|-----------------------------|
| **Magic-link / password-reset tokens are now hashed at rest** | `TokenEmailSender` stores `hash('sha256', $token)` in `auth_users_identities.secret`; the **raw** token is e-mailed and `UserIdentityModel::getIdentityBySecret()` hashes the looked-up value before matching. This is a **storage-format change** for those ephemeral identity types only — not a schema change. **Any unconsumed magic-link / password-reset tokens issued before the upgrade become invalid.** Users simply request a new link. |
| **`auth()` throws on unknown methods** | `Daycry\Auth\Auth::__call()` now throws `\BadMethodCallException` when the resolved authenticator has no such method (previously returned `null` silently). A `Session`-only method (e.g. `startLogin`, `getPendingUser`, `remember`) called while a stateless authenticator is active will now surface immediately. Audit any code that called those methods through `auth()` regardless of the active authenticator. |
| **Access-token / JWT login logging is fingerprinted** | The login-attempt log (`auth_logins.identifier`) now stores a non-reversible `hash('sha256', $token)` **fingerprint** for `AccessToken` and `JWT` credentials — never the raw bearer token. Session login still logs the email/username identifier (not a secret). Any tooling that parsed raw tokens out of the login log must be updated. |

### JWT access-token revocation (new `token_version`)

The new `users.token_version` column powers stateless, denylist-free revocation of JWT **access** tokens:

- `JwtController` now mints the access-token payload as `{uid, tv}`, where `tv` is the user's current `token_version`. Legacy scalar payloads (a bare user id) are still accepted — the `tv` check is skipped for them.
- The `JWT` authenticator's `check()` rejects a token whose embedded `tv` does not match the user's current `token_version`, returning `lang('Auth.revokedToken')`.
- `User::revokeIssuedTokens()` bumps `token_version` atomically, invalidating **all** outstanding access tokens for that user. It is called automatically by `Bannable::ban()` and `Services\PasswordChangeRecorder::record()` (on password reset/change). Call it directly for a "log out everywhere" action:

  ```php
  $user->revokeIssuedTokens(); // every previously-issued JWT access token now fails check()
  ```

- `JwtController` routes refresh / logout / issue through `service('jwtTokenRepository')`: refresh is now one-time-use rotation, and logout soft-revokes the refresh token.

### Other behaviour now enforced automatically (no action needed)

- **Remember-me expiry & theft detection** — `RememberMe::checkRememberMeToken()` enforces expiry at validation time (an expired cookie can no longer authenticate). On theft detection (selector matches but validator does not) it purges **all** of the user's remember-me tokens, writes the `login.suspicious` audit event, and fires `Events::trigger('remember-me-theft', $userId, $selector)`.
- **TOTP lockout & anti-replay** — TOTP / backup-code verification now goes through the same per-user lockout as password login (`UserLockoutManager`), and a TOTP code is single-use within its acceptance window (a code at or below the last consumed time-step is rejected).
- **Device-session revocation actually invalidates the live session** — when `Auth::$sessionConfig['trackDeviceSessions']` is `true`, every authenticated request verifies the current PHP session maps to a non-terminated `auth_device_sessions` row (`DeviceSessionModel::isSessionActive()`). A remotely-revoked or concurrent-limit-evicted session is forced to re-authenticate. (Previously "revoke" only flipped a DB column and the cookie kept working.)

### Optional — opt-in to new behaviour

| Option | Default | Meaning |
|--------|---------|---------|
| `AuthSecurity::$activeDateThrottle` | `60` | Minimum seconds between `users.last_active` writes on the authenticated hot path. `0` = write every request (legacy behaviour). |
| `AuthSecurity::$gateFallbackToRbac` | `true` | A Gate ability whose name contains a scope (e.g. `users.edit`) with no registered closure/policy falls back to `User::can()`. Set `false` to keep Gate and RBAC fully independent. |
| `AuthOAuth` provider option `'allowUnverifiedEmailLink'` | unset (`false`) | Per provider in `$providers`. When a social account's e-mail matches an existing local (password) account, auto-linking only happens if the provider asserts the e-mail is verified. Providers that cannot assert verification (Facebook, GitHub) refuse the merge unless this is `true`; refusal throws `AuthenticationException` with `lang('Auth.oauthEmailUnverified')`. |

### New explicit OAuth account-linking flow

A logged-in user can deliberately link an additional provider via:

| Method | HTTP route | Route name | Controller |
|--------|-----------|------------|------------|
| `GET` | `oauth/link/(:segment)` | `oauth-link` | `OauthController::link($provider)` |

The route requires an authenticated user, stashes the current user (session key `oauth_link_user_id`), and the shared callback links the provider to the **current** user — no e-mail merge and no verified-email requirement, because the user is authenticated and acting deliberately. Linking a social account already bound to a different local user is refused with `lang('Auth.oauthAlreadyLinked')`.

### Filter argument changes

| Filter | New argument form | Effect |
|--------|-------------------|--------|
| `rates` | `rates:<limit>,<period>` | Overrides the global limit/time for that route. `<period>` is a number of seconds or a named unit: `SECOND`, `MINUTE`, `HOUR`, `DAY`, `WEEK`. A configured endpoint DB row still overrides. (The registered alias is `rates`.) |
| `password-confirm` | `password-confirm:<seconds>` | Requires a password confirmation no older than `<seconds>` for that route, regardless of the global `AuthSecurity::$passwordConfirmationLifetime` ("sudo mode" for the most sensitive routes). |
| `gate` | `gate:users.edit` | Honors the Gate → RBAC fallback (`$gateFallbackToRbac`), so `gate:users.edit` and `permission:users.edit` can share semantics. |

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
