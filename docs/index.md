# Daycry Auth Documentation

Welcome to the complete documentation for **Daycry Auth**, a comprehensive authentication and authorization library for CodeIgniter 4.

```{toctree}
:maxdepth: 2
:caption: Getting Started

01-quick-start
02-configuration
```

```{toctree}
:maxdepth: 2
:caption: Authentication

03-authentication
09-oauth
10-totp-2fa
11-device-sessions
```

```{toctree}
:maxdepth: 2
:caption: Controllers & Filters

04-filters
05-controllers
```

```{toctree}
:maxdepth: 2
:caption: Authorization & Logging

06-authorization
07-logging
```

```{toctree}
:maxdepth: 2
:caption: Compliance & Operations

13-audit-and-compliance
14-cli-commands
```

```{toctree}
:maxdepth: 2
:caption: Testing & Reference

08-testing
12-migration
```

## Main Features

### Authentication
- **Multiple Authenticators**: Session, Access Token (with scope enforcement), JWT (with refresh tokens), Magic Link
- **JWT Access-Token Revocation**: `users.token_version` lets you invalidate every outstanding access token at once — `User::revokeIssuedTokens()` ("log out everywhere"), fired automatically on ban and password change ([JWT](03-authentication.md))
- **TOTP Two-Factor Authentication** with **backup codes**, optional **"Trust this device"** bypass, **brute-force lockout**, and **single-use (anti-replay) codes** ([TOTP](10-totp-2fa.md))
- **Device Session Tracking** with optional **concurrent-session limit** and **real, enforced revocation** — a revoked session is forced to re-authenticate on the next request ([Device Sessions](11-device-sessions.md))
- **Password Reset** + **Force Password Reset** + optional **rotation policy** + **history (no reuse)**, with **hashed-at-rest** magic-link & reset tokens
- **OAuth 2.0 / Social Login**: Google, GitHub, Facebook, Microsoft Azure, custom profile fields, OAuth events, explicit **account linking** (`oauth/link/(:segment)`) and **verified-email merge safety** ([OAuth](09-oauth.md))

### Authorization
- **Groups & Permissions (RBAC)** with optional persistent cache, uniform wildcard matching (`*`, `posts.*`) across user- and group-level permissions, and a **Gate → RBAC bridge** (`gateFallbackToRbac`)
- **API token scope enforcement** (`token-scope:` filter)
- **Per-Route Rate Limiting** — `rates:<limit>,<period>` overrides the global limit for a single route (`<period>` in seconds or `SECOND`/`MINUTE`/`HOUR`/`DAY`/`WEEK`) ([Filters](04-filters.md))
- **Sudo Mode (Password Confirmation)** — `password-confirm:<seconds>` enforces a fresh confirmation on the most sensitive routes, overriding the global window ([Filters](04-filters.md))
- **Flexible Filters**: Auth, chain, group, permission, gate, token-scope, password-age, rate limiting (`rates`), force-reset, password-confirm

### Security
- **Per-User Account Lockout** (atomic) — independent of IP-based blocking, now also applied to **TOTP / backup-code verification**
- **Compromised-Password Recheck on Login** (HIBP integration, opt-in)
- **Suspicious Login Detection** with `suspicious-login` event for email alerts, plus **remember-me theft detection** (a mismatched validator purges all of the user's tokens and fires `remember-me-theft`)
- **Secret-safe login log** — Access Token / JWT credentials are stored in `auth_logins` as a non-reversible SHA-256 fingerprint, never the raw bearer token
- **Timing-safe OAuth state** validation

### Compliance & Operations
- **Granular audit log** (`auth_audit_logs`) — 22 canonical event types, filterable CLI
- **GDPR helpers** — JSON data export + account anonymization
- **Admin CLI**: `auth:tokens revoke`, `auth:sessions terminate`, `auth:totp reset`, `auth:audit`, `auth:gdpr export|anonymize`
- **Scheduled Maintenance**: `auth:purge [--days <n>]` — purges expired remember-me tokens and old terminated device sessions; run on a schedule instead of the probabilistic on-login purge ([CLI Commands](14-cli-commands.md))
- **Complete Logging**: CI4 Events + database login attempts + audit log
- **Highly Customizable**: Extend or replace any component

## Quick Start

```bash
composer require daycry/auth
php spark migrate --all
php spark auth:setup
```

```php
// Login
$result = auth()->attempt(['email' => 'user@example.com', 'password' => 'secret']);

if ($result->isOK()) {
    return redirect()->to('/dashboard');
}
```

## Documentation Sections

### [Quick Start Guide](01-quick-start.md)
Install and configure Daycry Auth in minutes.

### [Configuration](02-configuration.md)
Every configuration option explained with examples.

### [Authentication](03-authentication.md)
Session, Access Token, JWT (with refresh), Magic Link, Password Reset, and more.

### [OAuth 2.0 & Social Login](09-oauth.md)
Google, GitHub, Facebook, Microsoft Azure — and any OIDC provider. Profile fields, custom resolvers, OAuth events, scopes tracking, explicit account linking, and `allowUnverifiedEmailLink` merge safety.

### [TOTP Two-Factor Authentication](10-totp-2fa.md)
Time-based OTP with authenticator apps, brute-force lockout, and single-use anti-replay codes.

### [Device Sessions](11-device-sessions.md)
Track and manage active logins across devices, with enforced revocation that forces re-authentication.

### [Security Filters](04-filters.md)
Protect routes with authentication and authorization filters, including per-route rate limits (`rates:<limit>,<period>`) and sudo mode (`password-confirm:<seconds>`).

### [Controllers](05-controllers.md)
All included controllers: Login, Register, Password Reset, Force Reset, JWT, UserSecurity.

### [Authorization](06-authorization.md)
Groups, permissions, permission cache, wildcard matching, the Gate → RBAC bridge, and RBAC patterns.

### [Logging & Monitoring](07-logging.md)
CI4 Events, database logs, per-user lockout, and rate limiting.

### [Testing](08-testing.md)
Unit and integration testing with authentication mocking.

## Additional Resources

- **GitHub**: [daycry/auth](https://github.com/daycry/auth)
- **CodeIgniter 4 Docs**: [codeigniter4.github.io](https://codeigniter4.github.io/)
- **Packagist**: [packagist.org/packages/daycry/auth](https://packagist.org/packages/daycry/auth)
- **Issues**: [github.com/daycry/auth/issues](https://github.com/daycry/auth/issues)
