# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

`daycry/auth` is a comprehensive Authentication/Authorization library for CodeIgniter 4 (PHP 8.2+). It provides multiple authentication methods (Session, Access Token, JWT, OAuth) and an RBAC authorization system. Full documentation lives in `docs/`.

## Commands

```bash
# Run full test suite (PHPUnit + code style)
composer test

# Run PHPUnit only (with coverage)
vendor/bin/phpunit

# Run PHPUnit without coverage (much faster)
vendor/bin/phpunit --no-coverage

# Run a single test file
vendor/bin/phpunit tests/Authentication/Authenticators/SessionAuthenticatorTest.php

# Run a specific test method
vendor/bin/phpunit --filter testMethodName

# Run only the language test suite
vendor/bin/phpunit --testsuite lang

# Fix code style
composer cs-fix

# Check code style without fixing
composer cs

# Static analysis (PHPStan level 5)
composer phpstan:check

# Regenerate PHPStan baseline after fixing real errors
vendor/bin/phpstan analyse --generate-baseline phpstan-baseline.neon

# Full analysis (PHPStan + Rector dry-run)
composer analyze

# Architecture compliance check (deptrac)
composer inspect

# Full CI pipeline (cs + deduplicate + inspect + analyze + test)
composer ci
```

## Code Standards

- All PHP files must start with `declare(strict_types=1);`
- All classes and methods must have DocBlocks with types
- Code must pass PHPStan level 5 (`phpstan.neon.dist`). The baseline (`phpstan-baseline.neon`) suppresses a set of known pre-existing errors from CI4 tooling (mostly `model()`/factory and test-mock false positives). **Regenerate the baseline after fixing real errors** — never add suppressions manually.
- Namespace root: `Daycry\Auth`
- `model(ClassName::class)` calls are flagged by the PHPStan CI4 plugin (they go to the baseline). Inside traits, centralize them in a private getter method rather than repeating them per method.
- Use `setting('AuthSecurity.totpIssuer')` / `setting('Auth.views')` etc. (the `setting()` helper) rather than `config('Auth')->...` for settings that can be overridden at runtime.
- Notable `AuthSecurity` properties: `$rememberMePurgeChance` (int, default 0 — expiry is enforced at validation time, so the inline purge is maintenance only; schedule `php spark auth:purge`); `$activeDateThrottle` (int, default 60 — throttles `users.last_active` writes); `$gateFallbackToRbac` (bool, default true — Gate abilities containing a `.` fall back to `User::can()`); `$pwnedPasswordsApiUrl` (string) — configurable HaveIBeenPwned API URL.
- JWT access-token revocation: `users.token_version` (migration `2026-05-08-000001`) is embedded in the access-token payload (`{uid, tv}`, legacy scalar still accepted) and re-checked on every JWT auth; `User::revokeIssuedTokens()` bumps it and is called by `Bannable::ban()` and `PasswordChangeRecorder::record()`.
- Token repositories and RBAC persistence are resolvable services (`service('accessTokenRepository'|'jwtTokenRepository'|'oauthTokenRepository'|'groupPermissionRepository')`); the latter holds the transactional pivot persistence extracted from the `Authorizable` trait. UUID v7 generation goes through `service('uuid')->uuid7()` (`michalsn/codeigniter4-uuid`).
- WebAuthn/Passkeys (`web-auth/webauthn-lib:^5.3`): opt-in passwordless login + passkey 2FA. Global availability flag `AuthSecurity.$webauthnEnabled` (default false → `Auth::routes()` registers no `webauthn` routes and the controller 404s). Credentials live in the dedicated `auth_webauthn_credentials` table (migration `2026-06-03-000001`), stored as the serialized v5 `Webauthn\CredentialRecord` plus denormalized columns. Mirrors the OAuth pattern: `WebAuthnManager` + `ChallengeManager` in `src/Libraries/WebAuthn/` (deptrac Library layer — no new layers), `WebAuthnController` (JSON ceremonies), `WebAuthnCredentialRepository` (row ↔ `CredentialRecord`, implements no lib interface). Services: `webAuthnManager`, `webAuthnCredentialRepository`, `webAuthnSerializer`, `webAuthnAttestationValidator`, `webAuthnAssertionValidator`. The `HasWebAuthn` trait adds `webAuthnCredentials()`/`hasWebAuthnCredentials()`/`revokeWebAuthnCredential()` to `User`. 2FA via the `Webauthn2FA` Action (mutually exclusive with `Totp2FA` as the `login` action). Passwordless verify ends in `auth()->login($user, false)`. Tests use `Tests\Support\WebAuthn\VirtualAuthenticator` (signs real ceremonies the genuine validators accept) + the `SuppressesWebauthnDeprecations` trait (the lib fires an RP-name `E_USER_DEPRECATED` that `CODEIGNITER_SCREAM_DEPRECATIONS=1` turns fatal). See `docs/15-webauthn.md`.

## Architecture

### Source layout

```
src/
├── Auth.php                     # Main service facade (magic __call proxies to active authenticator)
├── Config/
│   ├── Auth.php                 # Core config: authenticators, views, tables, actions, routes
│   ├── AuthSecurity.php         # Security config: passwords, lockout, rate-limit, TOTP, token lifetimes
│   ├── AuthOAuth.php            # OAuth providers ($providers array)
│   └── Registrar.php            # Auto-registers filters, validation rules, toolbar collector
├── Authentication/
│   ├── Authenticators/
│   │   ├── Base.php             # Abstract base: checkLogin(), forceLogin(), recordActiveDate()
│   │   ├── StatelessAuthenticator.php  # Abstract: login/logout/loggedIn/loginById for stateless auth
│   │   ├── Session.php          # Stateful: orchestrates services (see delegation pattern below)
│   │   ├── AccessToken.php      # Stateless: X-API-KEY header or query param
│   │   ├── JWT.php              # Stateless: Authorization: Bearer <token>
│   │   └── Guest.php            # Anonymous fallback
│   ├── Actions/
│   │   ├── AbstractAction.php      # Base: requirePendingUser(), getIdentity(), getIdentityModel()
│   │   ├── Email2FA.php            # Extends AbstractAction
│   │   ├── EmailActivator.php      # Extends AbstractAction
│   │   └── Totp2FA.php             # Extends AbstractAction
│   ├── Services/
│   │   ├── DeviceSessionRecorder.php   # recordSession(), terminateCurrentSession()
│   │   ├── PendingActionCoordinator.php # findPendingAction(), activateAction()
│   │   ├── RememberMe.php              # Remember-me token lifecycle
│   │   └── UserLockoutManager.php      # isLockedOut(), recordFailedAttempt(), resetOnSuccess()
│   ├── JWT/Adapters/            # DaycryJWTAdapter (wraps daycry/jwt)
│   └── Passwords/               # CompositionValidator, NothingPersonalValidator, DictionaryValidator
├── Authorization/Groups.php     # Group utilities
├── Controllers/
│   ├── BaseAuthController.php   # Abstract: uses BaseControllerTrait + Viewable
│   ├── Admin/                   # Bootstrap 5 admin panel (Users, Groups, Permissions, Logs, Dashboard)
│   └── UserSecurityController.php  # User-facing TOTP + device session management
├── Database/Migrations/         # 2023-12-07: core tables; 2026-02-26: indexes + device_sessions;
│                                #   2026-02-28: uuid columns + security columns (lockout, revoked_at)
├── Entities/                    # User (all traits), UserIdentity, AccessToken, DeviceSession, etc.
├── Enums/
│   ├── AuthenticationState.php  # UNKNOWN, PENDING, LOGGED_IN, LOGGED_OUT
│   ├── IdentityType.php         # All identity type strings + oauthProvider() dynamic helper
│   └── TotpState.php            # PENDING = 'totp_pending', CONFIRMED = 'totp'
├── Filters/                     # AbstractAuthFilter + Group/Permission/Auth/Chain/Rates filters
├── Models/
│   ├── UserModel.php               # User CRUD + UUID generation
│   ├── UserIdentityModel.php       # Central identity store (base queries)
│   ├── AccessTokenRepository.php   # Access token CRUD (wraps UserIdentityModel)
│   ├── JwtTokenRepository.php      # JWT refresh token CRUD (wraps UserIdentityModel)
│   ├── OAuthTokenRepository.php    # OAuth identity CRUD (wraps UserIdentityModel)
│   ├── DeviceSessionModel.php      # Device session CRUD + UUID generation
│   └── ...                         # GroupModel, PermissionModel, LoginModel, etc.
├── Libraries/
│   ├── TOTP.php                    # TOTP secret/QR generation + verification
│   └── TokenEmailSender.php        # Token generation + email sending (used by MagicLink, PasswordReset)
├── Services/                    # AttemptHandler, ExceptionHandler, RequestLogger
├── Traits/
│   ├── Authorizable.php         # RBAC: groups/permissions with optional cache
│   ├── HasAccessTokens.php      # Token CRUD (delegates to AccessTokenRepository)
│   ├── HasDeviceSessions.php    # Device session management
│   ├── HasTotp.php              # TOTP enable/disable/verify (secret stored AES-encrypted)
│   ├── Activatable.php          # Email activation flow
│   ├── Bannable.php             # Ban/unban with reason
│   ├── Resettable.php           # Password reset flow
│   └── BaseControllerTrait.php  # Wires AttemptHandler + ExceptionHandler + RequestLogger
└── Commands/                    # auth:setup, auth:discover, auth:user
```

### Authenticator hierarchy

```
Base (abstract)
├── Session (stateful — uses $_SESSION)
└── StatelessAuthenticator (abstract — login/logout/loggedIn shared)
    ├── AccessToken  (getTokenFromRequest → X-API-KEY header/query)
    └── JWT          (getTokenFromRequest → Authorization: Bearer)
```

`AccessToken` and `JWT` both override `loginById()` minimally; all shared stateless logic lives in `StatelessAuthenticator`. `Session` is independent because its lifecycle is fundamentally different.

**Critical**: `loggedIn()` on stateless authenticators re-validates the token on **every call** (no in-memory cache between calls). Calling `auth()->loggedIn()` twice in a request hits the DB/JWT check twice.

### Session delegates to focused services

`Session.php` orchestrates four extracted service classes instead of implementing all logic inline:

| Service | Responsibility |
|---------|---------------|
| `UserLockoutManager` | Per-user lockout: `isLockedOut()`, `recordFailedAttempt()`, `resetOnSuccess()` |
| `DeviceSessionRecorder` | Device session tracking: `recordSession()`, `terminateCurrentSession()` |
| `PendingActionCoordinator` | Post-auth actions: `findPendingAction()`, `activateAction()` |
| `RememberMe` | Remember-me token lifecycle |

All services are injected or instantiated in Session's constructor and read config via `setting()`.

### Identity model is the central identity store

`UserIdentityModel` stores **all** identity types in a single `auth_users_identities` table. Use the `IdentityType` enum for all type strings:

| `IdentityType` case | `secret` column contents |
|---|---|
| `EMAIL_PASSWORD` | email; `secret2` = bcrypt hash |
| `ACCESS_TOKEN` | SHA-256 hash of raw token |
| `JWT_REFRESH` | SHA-256 hash of raw token |
| `TOTP_SECRET` | AES-encrypted + base64 secret (use `service('encrypter')`) |
| `MAGIC_LINK`, `EMAIL_2FA`, `EMAIL_ACTIVATE`, `RESET_PASSWORD`, `EMAIL_CHANGE` | ephemeral codes |

### Token repositories

Token-specific CRUD is encapsulated in focused repository classes that wrap `UserIdentityModel`:

| Repository | Delegates from | Key methods |
|-----------|---------------|-------------|
| `AccessTokenRepository` | `HasAccessTokens` trait, `AccessToken` authenticator | `generateAccessToken()`, `softRevokeAccessToken()`, `getAccessTokenByRawToken()` |
| `JwtTokenRepository` | `JwtController` | `createRefreshToken()`, `getRefreshToken()`, `softRevokeRefreshToken()` |
| `OAuthTokenRepository` | `OauthManager` | `findByUserAndProvider()`, `findByProviderAndSocialId()`, `createOAuthIdentity()`, `updateOAuthIdentity()`, `getProfileData()`, `parseExtra()` |

Both AccessToken and JWT use soft-revocation (`revoked_at` column) by default. The old hard-delete methods on `UserIdentityModel` are `@deprecated`.

### TOTP two-phase enrollment

`HasTotp` separates secret generation from confirmation via `TotpState`:

1. `$user->enableTotp()` — generates a fresh AES-encrypted secret, stores it with `name = TotpState::PENDING`, returns the `otpauth://` URL. Calling it again replaces any existing secret (pending or confirmed).
2. `$user->hasTotpPending()` / `$user->hasTotpEnabled()` — distinguish enrollment state.
3. `$user->confirmTotp()` — updates `name` to `TotpState::CONFIRMED` after the user verifies their first code.
4. `$user->getTotpSecret()` — transparently decrypts and returns the raw base32 secret.

`Totp2FA` (login action) only fires when `hasTotpEnabled()` returns true (i.e. `CONFIRMED`). Users mid-enrollment (`PENDING`) are not challenged.

### Post-auth Action system

After login/register, `Session` checks `Config\Auth::$actions['login']` and `$actions['register']`. If set, the user is redirected to `ActionController` before getting a session. Actions implement `ActionInterface`:
- `Email2FA` — sends a 6-digit code and requires it
- `EmailActivator` — requires email confirmation before allowing login
- `Totp2FA` — validates a TOTP code; user must have confirmed TOTP (`hasTotpEnabled() === true`)

All three action classes extend `AbstractAction`, which provides shared helpers:
- `requirePendingUser()` — returns the pending-login user from the session authenticator
- `getIdentity(User $user)` — returns the identity for this action's type
- `getIdentityModel()` — returns the `UserIdentityModel` instance

Adding a new action: extend `AbstractAction`, implement `ActionInterface`, set `$type` to the `IdentityType` string.

`Session::completeLogin()` always clears `auth_action` / `auth_action_message` from the PHP session before setting `LOGGED_IN` state — this prevents stale pending-action state on subsequent requests.

### Config split

Configuration is split across three classes to keep concerns separate:

| Class | Contains |
|---|---|
| `Config\Auth` | Authenticators, actions, views, table names, routes, session/remember-me settings |
| `Config\AuthSecurity` | Passwords, lockout, rate-limit, TOTP issuer, token lifetimes, permission cache |
| `Config\AuthOAuth` | OAuth provider definitions (`$providers` array) |

### Authorization (RBAC)

The `Authorizable` trait (mixed into `User`) provides groups + permissions. Both are loaded lazily and can be cached:

```php
// Config/AuthSecurity.php
$permissionCacheEnabled = false; // set true in production
$permissionCacheTTL     = 300;   // seconds
```

Cache is auto-invalidated on any `addGroup`/`removeGroup`/`addPermission`/`removePermission` call. Use `$user->clearPermissionCache()` for manual invalidation.

### Route filters

Registered automatically via `Registrar.php`. Applied in CI4's `$routes` or filter config:

| Filter alias | Purpose |
|---|---|
| `auth` | Authenticate (uses `$defaultAuthenticator`) |
| `chain` | Try authenticators in `$authenticationChain` order |
| `group:admin,editor` | Require group membership |
| `permission:users.edit` | Require specific permission |
| `force-reset` | Force password change |
| `rates` | Per-IP/user rate limiting |

`GroupFilter` and `PermissionFilter` both extend `AbstractAuthFilter`. To add a new authorization filter, extend `AbstractAuthFilter` and implement `isAuthorized()` and `redirectToDeniedUrl()`.

### Database schema

- `2023-12-07-000001` — all core tables
- `2026-02-26-000001/000002` — performance indexes + `auth_device_sessions`
- `2026-02-28-000001` — `uuid` column on `users` and `device_sessions` (UUID v7, backfill included)
- `2026-02-28-000002` — security columns: `failed_login_count`, `locked_until` on users; `revoked_at` on identities

All table names are configurable via `$tables` in `Config/Auth.php`.

### OAuth2

`OauthManager` uses League OAuth2 providers. Supported out of the box: `azure` (thenetworg/oauth2-azure), `google`, `facebook`, `github` (league/oauth2-*). Generic OIDC/OAuth via `GenericProvider`. Configured in `Config/AuthOAuth.php::$providers`.

OAuth identity types are dynamic (`oauth_google`, `oauth_github`, etc.) — use `IdentityType::oauthProvider($name)` instead of string concatenation.

`OauthManager` delegates all identity CRUD to `OAuthTokenRepository` (lazy-initialised via `getRepository()`). The centralized `getIdentityModel()` avoids repeated `model()` calls.

**Profile system**: When `$providerConfig['fields']` is configured, `OauthManager` fetches extra profile data via `ProfileResolverFactory`. The factory supports three resolver strategies:
1. `$providerConfig['profileResolver']` — custom class (must implement `ProfileResolverInterface`)
2. Built-in map (`azure` → `AzureProfileResolver`)
3. Fallback → `GenericProfileResolver`

Profile data and metadata are stored in the identity's `extra` JSON column:
```json
{
  "refresh_token": "...",
  "scopes_granted": ["openid", "profile", "email"],
  "profile": {"department": "Engineering"},
  "profile_fetched_at": "2026-03-20 12:00:00"
}
```

**OAuth events** (fired by `handleCallback()`):
- `oauth-login` — after successful login: `Events::trigger('oauth-login', $user, $providerName)`
- `oauth-profile-fetched` — when profile fields were resolved: `Events::trigger('oauth-profile-fetched', $user, $providerName, $profileData)`

### Device Sessions

When `$sessionConfig['trackDeviceSessions'] = true`, every `Session::startLogin()` creates an `auth_device_sessions` record. `Session::logout()` terminates it. Users can manage their sessions via `HasDeviceSessions` trait methods.

### Testing

Tests mirror the `src/` structure under `tests/`. Two base cases:
- `Tests\Support\TestCase` — no DB, resets services, injects array settings handler + a fixed AES-256 encryption key so `service('encrypter')` works
- `Tests\Support\DatabaseTestCase` — extends TestCase + `DatabaseTestTrait` (SQLite in-memory)

**Inject mock config** in tests with:
```php
$this->inkectMockAttributes(['defaultAuthenticator' => 'jwt']);          // Auth config
$this->inkectMockAttributesSecurity(['userMaxAttempts' => 3]);            // AuthSecurity config
$this->inkectMockAttributesOAuth(['providers' => [...]]);                 // AuthOAuth config
```

**Slow-test detection** via Tachycardia (threshold: 0.50s). Coverage reports go to `build/coverage/`. Use `--no-coverage` flag during development.
