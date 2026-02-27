# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

`daycry/auth` is a comprehensive Authentication/Authorization library for CodeIgniter 4 (PHP 8.1+). It provides multiple authentication methods (Session, Access Token, JWT, OAuth) and an RBAC authorization system. Full documentation lives in `docs/`.

## Commands

```bash
# Run full test suite (PHPUnit + code style)
composer test

# Run PHPUnit only
vendor/bin/phpunit

# Run a single test file
vendor/bin/phpunit tests/Authentication/Authenticators/SessionTest.php

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

# Full analysis (PHPStan + Rector dry-run)
composer analyze

# Architecture compliance check
composer inspect

# Full CI pipeline (cs + deduplicate + inspect + analyze + test)
composer ci
```

## Code Standards

**CRITICAL**: All code modifications require:
1. **Analysis** – understand the current implementation and impact of changes
2. **Validation** – verify the approach
3. **Implementation** – code following the standards below

- All PHP files must start with `declare(strict_types=1);`
- All classes and methods must have DocBlocks with types
- Code must pass PHPStan level 5 (see `phpstan.neon.dist`) and Psalm (`psalm.xml`)
- Namespace root: `Daycry\Auth`
- Use `Daycry\Auth\Entities\*` entities instead of raw arrays
- Access CI4 core services via `CodeIgniter\Config\Services`

## Architecture

### Source layout

```
src/
├── Auth.php                     # Main service facade
├── Config/Auth.php              # Central configuration (800+ lines)
├── Authentication/
│   ├── Authenticators/          # Session, AccessToken, JWT, Guest
│   ├── Actions/                 # Post-auth actions (Email2FA, EmailActivator)
│   ├── JWT/                     # JWT adapter
│   └── Passwords/               # Password validators (Composition, NothingPersonal, Dictionary)
├── Authorization/Groups.php     # Group utilities
├── Controllers/                 # Pre-built auth UI controllers
├── Database/Migrations/         # Single migration creates all tables
├── Entities/                    # User, UserIdentity, AccessToken, Group, Permission, Log, Rate, etc.
├── Filters/                     # Route security filters
├── Models/                      # Database models (UserModel, UserIdentityModel, etc.)
├── Services/                    # AttemptHandler, ExceptionHandler, RequestLogger
├── Traits/                      # Authorizable, HasAccessTokens, Activatable, Bannable, Resettable
└── Commands/                    # CLI: auth:setup, auth:discover, auth:user
```

### Authentication flow

Each authenticator (`Session`, `AccessToken`, `JWT`, `Guest`) implements `AuthenticatorInterface`. The `auth()` helper returns the authenticator instance:

- `auth('session')` – session-based login; persists via `$_SESSION`
- `auth('access_token')` – reads `X-API-KEY` header; token stored in `auth_users_identities`
- `auth('jwt')` – reads `Authorization: Bearer <token>`; stateless
- `auth('guest')` – anonymous access fallback

The `chain` filter tries authenticators in the order defined by `$authenticationChain` in config.

### Authorization (RBAC)

Groups (roles) and permissions are stored in their own tables. The `Authorizable` trait (mixed into the `User` entity) provides:
- `$user->inGroup('admin')` / `$user->addToGroup('editor')`
- `$user->can('resource.action')` / `$user->addPermission('users.edit')`

### Route filters

Registered in CI4's `Filters` config and applied to routes:

| Filter | Purpose |
|--------|---------|
| `session`, `tokens`, `jwt`, `chain` | Authenticate the request |
| `group:admin,editor` | Require group membership |
| `permission:users.edit` | Require specific permission |
| `forcePasswordReset` | Force password change flow |
| `rates` | Per-IP/user rate limiting |

### Database schema

All tables are created by `src/Database/Migrations/2023-12-07-000001_create_core_tables.php`. Key tables: `users`, `auth_users_identities`, `auth_groups`, `auth_permissions`, `auth_groups_users`, `auth_permissions_users`, `auth_permissions_groups`, `auth_logins`, `auth_remember_tokens`, `auth_logs`, `auth_attempts`, `auth_rates`, `auth_apis`, `auth_controllers`, `auth_endpoints`.

Table names are fully configurable via the `$tables` array in `src/Config/Auth.php`.

### Discovery feature

When `$enableDiscovery = true`, the library scans registered namespaces and stores discovered controllers/endpoints in `auth_controllers` / `auth_endpoints`. The `checkEndpoint` helper short-circuits to avoid DB calls when discovery is disabled.

### Testing

Tests mirror the `src/` structure under `tests/`. The bootstrap uses CI4's test bootstrap. Run slow-test detection is enabled via the Tachycardia extension (warn threshold: 0.50s). Coverage reports go to `build/coverage/`.
