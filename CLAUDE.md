# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

`daycry/auth` is a comprehensive Authentication/Authorization library for CodeIgniter 4 (PHP 8.1+). It provides multiple authentication methods (Session, Access Token, JWT, OAuth) and an RBAC authorization system. Full documentation lives in `docs/`.

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
- Code must pass PHPStan level 5 (`phpstan.neon.dist`). The baseline (`phpstan-baseline.neon`) suppresses ~592 known pre-existing errors from CI4 tooling. **Regenerate the baseline after fixing real errors** — never add suppressions manually.
- Namespace root: `Daycry\Auth`
- `model(ClassName::class)` calls are flagged by the PHPStan CI4 plugin (they go to the baseline). Inside traits, centralize them in a private getter method rather than repeating them per method.

## Architecture

### Source layout

```
src/
├── Auth.php                     # Main service facade (magic __call proxies to active authenticator)
├── Config/Auth.php              # Central configuration (880+ lines, all features)
├── Config/Registrar.php         # Auto-registers filters, validation rules, toolbar collector
├── Authentication/
│   ├── Authenticators/
│   │   ├── Base.php             # Abstract base: checkLogin(), forceLogin(), recordActiveDate()
│   │   ├── StatelessAuthenticator.php  # Abstract: login/logout/loggedIn/loginById for stateless auth
│   │   ├── Session.php          # Stateful: $_SESSION + remember-me + device sessions
│   │   ├── AccessToken.php      # Stateless: X-API-KEY header or query param
│   │   ├── JWT.php              # Stateless: Authorization: Bearer <token>
│   │   └── Guest.php            # Anonymous fallback
│   ├── Actions/                 # Post-auth hooks: Email2FA, EmailActivator, Totp2FA
│   ├── JWT/Adapters/            # DaycryJWTAdapter (wraps daycry/jwt)
│   └── Passwords/               # CompositionValidator, NothingPersonalValidator, DictionaryValidator
├── Authorization/Groups.php     # Group utilities
├── Controllers/
│   ├── BaseAuthController.php   # Abstract: uses BaseControllerTrait + Viewable
│   ├── Admin/                   # Bootstrap 5 admin panel (Users, Groups, Permissions, Logs, Dashboard)
│   └── UserSecurityController.php  # User-facing TOTP + device session management
├── Database/Migrations/         # 2023-12-07: core tables; 2026-02-26: indexes + device_sessions
├── Entities/                    # User (all traits), UserIdentity, AccessToken, DeviceSession, etc.
├── Filters/                     # AbstractAuthFilter + Group/Permission/Auth/Chain/Rates filters
├── Models/                      # UserModel, UserIdentityModel (central identity store), DeviceSessionModel, etc.
├── Services/                    # AttemptHandler, ExceptionHandler, RequestLogger
├── Traits/
│   ├── Authorizable.php         # RBAC: groups/permissions with optional cache
│   ├── HasAccessTokens.php      # Token CRUD (delegates to UserIdentityModel)
│   ├── HasDeviceSessions.php    # Device session management
│   ├── HasTotp.php              # TOTP enable/disable/verify
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

### Identity model is the central identity store

`UserIdentityModel` stores **all** identity types in a single `auth_users_identities` table:
- `email_password` — hashed password
- `access_token` — hashed Bearer tokens
- `magic-link` — one-time login links
- `email_2fa` — 6-digit email codes (ephemeral)
- `email_activate` — activation codes
- `totp_secret` — permanent TOTP secret (RFC 6238)
- `totp` — ephemeral marker during TOTP login flow

### Post-auth Action system

After login/register, `Session` checks `Config\Auth::$actions['login']` and `$actions['register']`. If set, the user is redirected to `ActionController` before getting a session. Actions implement `ActionInterface`:
- `Email2FA` — sends a 6-digit code and requires it
- `EmailActivator` — requires email confirmation before allowing login
- `Totp2FA` — validates a TOTP code; user must have `totp_secret` identity pre-configured via `$user->enableTotp()`

### Authorization (RBAC)

The `Authorizable` trait (mixed into `User`) provides groups + permissions. Groups and their assigned permissions are stored in separate tables; both are loaded lazily and can be cached:

```php
// Config/Auth.php
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

Two migrations: `2023-12-07-000001_create_core_tables.php` (all core tables) and `2026-02-26-000001/000002` (performance indexes + `auth_device_sessions`). All table names are configurable via `$tables` in `Config/Auth.php`.

### OAuth2

`OauthManager` uses League OAuth2 providers. Supported out of the box: `azure` (thenetworg/oauth2-azure), `google`, `facebook`, `github` (league/oauth2-*). Generic OIDC/OAuth via `GenericProvider`. Configured in `Config/Auth.php::$providers`.

### Device Sessions

When `$sessionConfig['trackDeviceSessions'] = true`, every `Session::startLogin()` creates an `auth_device_sessions` record. `Session::logout()` terminates it. Users can manage their sessions via `HasDeviceSessions` trait methods.

### Testing

Tests mirror the `src/` structure under `tests/`. Two base cases:
- `Tests\Support\TestCase` — no DB, resets services, injects array settings handler
- `Tests\Support\DatabaseTestCase` — extends TestCase + `DatabaseTestTrait` (SQLite in-memory)

**Inject mock config** in tests with:
```php
$this->inkectMockAttributes(['defaultAuthenticator' => 'jwt']);
```

**Slow-test detection** via Tachycardia (threshold: 0.50s). Coverage reports go to `build/coverage/`. Use `--no-coverage` flag during development.
