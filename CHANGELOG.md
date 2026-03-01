# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [4.0.0] - 2026-03-01

### Breaking Changes

- **`Config\Auth` split into three classes** — security and OAuth settings have been extracted into dedicated config files. Applications that extend or override `app/Config/Auth.php` must migrate the affected properties:

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

- **`setting('Auth.X')` calls renamed** — any custom code using `setting('Auth.recordLoginAttempt')`, `setting('Auth.requestLimit')`, etc. must update to `setting('AuthSecurity.X')` or `setting('AuthOAuth.X')` accordingly.
- **`Passwords` and `BaseValidator` constructor** now accept `AuthSecurity` instead of `Auth`. Custom password validators extending `BaseValidator` must update their type hints.
- **`OauthManager` constructor** now accepts `AuthOAuth` instead of `Auth`.

### Migration

Create `app/Config/AuthSecurity.php` and `app/Config/AuthOAuth.php` extending the library classes, then move your customised properties into the respective files:

```php
// app/Config/AuthSecurity.php
namespace Config;
use Daycry\Auth\Config\AuthSecurity as AuthSecurityConfig;
class AuthSecurity extends AuthSecurityConfig
{
    public int $minimumPasswordLength = 10;
    // ...
}

// app/Config/AuthOAuth.php
namespace Config;
use Daycry\Auth\Config\AuthOAuth as AuthOAuthConfig;
class AuthOAuth extends AuthOAuthConfig
{
    public array $providers = [ /* your providers */ ];
}
```

---

## [3.1.0] - 2026-02-28

### Added

#### Authentication
- **TOTP Two-Factor Authentication** — time-based OTP compatible with Google Authenticator, Authy, and 1Password
  - `src/Libraries/TOTP.php` — secret generation, QR code URI, and code verification (RFC 6238)
  - `src/Traits/HasTotp.php` — `enableTotp()`, `disableTotp()`, `verifyTotp()` mixed into `User`
  - `src/Authentication/Actions/Totp2FA.php` — post-login action: validates TOTP code before granting session
  - `src/Views/totp_2fa_verify.php`, `totp_setup_show.php`, `totp_setup_success.php` — enrollment and verification views
- **JWT Refresh Token rotation** — stateless token renewal without re-authentication
  - `src/Controllers/JwtController.php` — `loginAction()`, `refreshAction()`, `logoutAction()`
  - Refresh token stored as hashed `access_token` identity; revoked on logout
- **Password Reset flow** — secure token-based reset with email delivery
  - `src/Controllers/PasswordResetController.php` — request, message, and reset views + actions
  - `src/Views/password_reset_request.php`, `password_reset_message.php`, `password_reset_form.php`
  - `src/Views/Email/password_reset_email.php` — HTML reset email template
- **Force Password Reset** — mandatory password change on next login
  - `src/Controllers/ForcePasswordResetController.php` — intercepts login and forces password update
  - `src/Views/force_password_reset.php` — password change form
  - `src/Filters/ForcePasswordResetFilter::class` — route filter alias `force-reset`
- **Email change confirmation** — `src/Views/Email/email_change_email.php` — confirmation link sent to new address

#### Security
- **Device Session Tracking** — see and terminate active logins per device/browser
  - `src/Database/Migrations/2026-02-26-000002_create_device_sessions_table.php`
  - `src/Models/DeviceSessionModel.php` — CRUD + cleanup helpers
  - `src/Entities/DeviceSession.php` — entity with UA parsing, IP, last active
  - `src/Traits/HasDeviceSessions.php` — `getDeviceSessions()`, `terminateDeviceSession()`, `terminateAllDeviceSessions()`
  - Session authenticator integration: creates record on login, terminates on logout
- **UUID Dual-Key Pattern** — expose `uuid` externally, keep `id` (INT) internal
  - `src/Database/Migrations/2026-02-28-000001_add_uuid_columns.php` — adds `uuid VARCHAR(36) UNIQUE` to `users` and `device_sessions`; backfills existing rows with UUID v7
  - `UserModel` and `DeviceSessionModel` — `$beforeInsert` callback generates UUID v7 via `symfony/uid`
- **Per-user account lockout** — independent of IP blocking
  - `src/Database/Migrations/2026-02-28-000002_add_security_columns.php` — adds `last_login`, `failed_login_attempts`, `last_failed_login` columns to users
  - `Session` authenticator tracks and checks per-user failed attempts
- **Performance indexes** — `src/Database/Migrations/2026-02-26-000001_add_performance_indexes.php` — indexes on `auth_users_identities`, `auth_logins`, `auth_remember_tokens`
- `hash_equals()` for timing-safe token comparison in `Session` authenticator (prevents timing attacks)
- `json_decode()` / `json_encode()` replacing `unserialize()` / `serialize()` in `SerializeCast`, `UserIdentityModel`, and `Logger` (prevents object injection)

#### Authorization
- **Permission Cache** — configurable TTL cache in `Authorizable` trait
  - `Config\Auth::$permissionCacheEnabled` (default `false`) and `$permissionCacheTTL` (default `300` seconds)
  - Auto-invalidated on any group/permission change; manual `$user->clearPermissionCache()`

#### Admin Panel (Bootstrap 5)
- `src/Controllers/Admin/DashboardController.php` — overview stats
- `src/Controllers/Admin/UsersController.php` — list, show, edit, ban/unban, force-reset
- `src/Controllers/Admin/GroupsController.php` — create, edit, delete groups; manage members
- `src/Controllers/Admin/PermissionsController.php` — create, edit, delete permissions
- `src/Controllers/Admin/LogsController.php` — paginated login attempt log viewer
- `src/Views/admin/` — Bootstrap 5 layout, dashboard, users, groups, permissions, logs views

#### OAuth2
- `src/Controllers/UserSecurityController.php` — user-facing TOTP management, device session list, OAuth provider unlinking, password and email change
- `src/Views/profile/security.php` — security settings page
- OAuth refresh token storage and retrieval via `UserIdentityModel`

#### Architecture
- `src/Authentication/Authenticators/StatelessAuthenticator.php` — abstract base for `AccessToken` and `JWT`; centralises `login()`, `logout()`, `loggedIn()`, `loginById()`, removing ~120 lines of duplication
- `src/Enums/IdentityType.php` — new values: `TotpSecret`, `Totp`, `RefreshToken`
- `src/Interfaces/UserProviderInterface.php` — added `update()` method

#### Language
- All 19 language files (`en`, `ar`, `bg`, `de`, `es`, `fa`, `fr`, `id`, `it`, `ja`, `lt`, `pt`, `pt-BR`, `ru`, `sk`, `sr`, `sv-SE`, `tr`, `uk`) updated with strings for:
  - TOTP enrollment and verification
  - Device session management
  - Password reset and force-reset flow
  - Per-user lockout messages

#### CI / Tooling
- `.github/workflows/phpunit.yml` — overhauled:
  - Fixed deprecated `::set-output` → `$GITHUB_OUTPUT`
  - Updated `actions/checkout` and `actions/cache` to `@v4`
  - Added `development` branch to push/PR triggers
  - Removed unnecessary `script -e -c` PTY wrapper
  - Separated coverage into a dedicated `coverage` job (PHP 8.3 + Xdebug only); test matrix runs with `coverage: none`
  - Per-PHP-version composer cache keys
  - Replaced manual `php-coveralls` install with `coverallsapp/github-action@v2`
- `.github/workflows/static-analysis.yml` — new pipeline with three parallel jobs:
  - `phpstan` — PHPStan level 5 with result cache
  - `cs` — PHP CS Fixer dry-run
  - `deptrac` — architecture compliance check
- `deptrac.yaml` — added `Library` to allowed dependencies of `Entity` layer (required by `HasTotp` trait)

#### Documentation
- Complete rewrite of `docs/` (11 sections):
  - `01-quick-start.md` — includes password reset routes and filter setup
  - `02-configuration.md` — all new options: `passwordResetLifetime`, `jwtRefreshLifetime`, `userMaxAttempts`, `userLockoutTime`, `permissionCacheEnabled`, `trackDeviceSessions`
  - `03-authentication.md` — JWT refresh token rotation, per-user lockout, password reset, force reset, pre-auth events
  - `05-controllers.md` — `PasswordResetController`, `ForcePasswordResetController`, `JwtController`, `UserSecurityController`
  - `06-authorization.md` — permission cache, RBAC patterns, admin panel
  - `07-logging.md` — CI4 Events table, per-user lockout, rate limiting
  - `09-oauth.md` — provider setup, refresh tokens, unlinking
  - `10-totp-2fa.md` *(new)* — full enrollment and login flow
  - `11-device-sessions.md` *(new)* — session tracking, termination, notifications
- Root `README.md` — feature tables, JWT refresh example, updated badges (Tests + Static Analysis)

### Changed

- `AccessToken` and `JWT` authenticators refactored to extend `StatelessAuthenticator`
- `Authorizable` trait centralised model access via private getter methods (reduces PHPStan `model()` warnings)
- `Activatable`, `Bannable`, `Resettable` traits: removed direct `auth()->getProvider()` calls, use internal model getter
- `GroupFilter` and `PermissionFilter` extended from `AbstractAuthFilter` (DRY refactor)
- `ExceptionHandler` simplified — removed duplicate `handle()` overloads
- PHPStan baseline regenerated: 587 → 599 suppressions (new controllers absorb `model()`/`emailer()` discouraged-call warnings)
- PHP CS Fixer: matrix test versions changed from `['8.1', '8.2', '8.3']` to `['8.2', '8.3']`

### Fixed

- Language files (18 non-English): unescaped apostrophes (`we'll`, `wasn't`) in single-quoted PHP strings causing `ParseError`
- `UserProviderInterface` missing `update()` method — caused PHPStan errors in `Session` authenticator per-user lockout code
- `ForcePasswordResetController::getValidationRules()` incorrect PHPDoc return type

[Unreleased]: https://github.com/daycry/auth/compare/v4.0.0...HEAD
[4.0.0]: https://github.com/daycry/auth/compare/v3.1.0...v4.0.0
[3.1.0]: https://github.com/daycry/auth/compare/v3.0.6...v3.1.0
