# Daycry Auth — Documentation

Complete documentation for **Daycry Auth**, a comprehensive authentication and authorization library for CodeIgniter 4 (PHP 8.2+).

## Documentation Index

### [Quick Start Guide](01-quick-start.md)
- Installation via Composer
- Running migrations
- Basic configuration
- First protected route

### [Configuration Reference](02-configuration.md)
- All configuration options with descriptions
- Database, authenticators, session, password
- Password reset, JWT refresh, per-user lockout
- Permission cache, views, routes, redirects
- Trusted devices, concurrent session limit
- Hot-path write throttles (`activeDateThrottle`, `tokenLastUsedThrottle`)
- Gate → RBAC fallback (`gateFallbackToRbac`)
- Compliance & observability presets

### [Authentication](03-authentication.md)
- Session authenticator (web apps)
- Per-user account lockout (atomic)
- Compromised-password recheck on login (HIBP)
- Access Token authenticator + scope enforcement
- JWT authenticator + refresh token rotation + token-version revocation
- Magic Link (passwordless) — email link or 6-digit code modes
- Password Reset flow
- Force Password Reset
- Pre-authentication events

### [Security Filters](04-filters.md)
- `session`, `tokens`, `jwt`, `chain` authentication filters
- `group`, `permission`, `token-scope`, `gate` authorization filters
- `rates` rate limiting (per-route `rates:<limit>,<period>` override)
- `password-confirm` sudo mode (per-route `password-confirm:<seconds>`)
- `force-reset`, `password-age` enforcement filters

### [Controllers](05-controllers.md)
- `LoginController`, `RegisterController`
- `PasswordResetController`, `ForcePasswordResetController`
- `JwtController` (stateless login/refresh/logout)
- `UserSecurityController` (change password, email, OAuth unlink, login activity feed)
- Creating custom controllers

### [Authorization](06-authorization.md)
- Groups (roles) and permissions
- Permission inheritance and wildcards (`posts.*`, user- and group-level)
- Permission cache (for production)
- Route filter usage (`gate` ↔ `permission` via `gateFallbackToRbac`)
- RBAC patterns and best practices

### [Logging & Monitoring](07-logging.md)
- CodeIgniter Events (pre-login, login, logout, register, passwordReset, suspicious-login, remember-me-theft)
- Database login attempt logs
- IP-based failed attempt blocking
- Per-user account lockout
- Rate limiting
- Audit log overview

### [Testing](08-testing.md)
- Running the test suite
- DatabaseTestCase setup
- Authentication mocking (`injectMockAttributes*`)
- Testing filters, controllers, models

### [OAuth 2.0 & Social Login](09-oauth.md)
- Google, GitHub, Facebook, Microsoft Azure
- Generic OIDC provider
- Profile fields & custom resolvers
- OAuth events (`oauth-login`, `oauth-profile-fetched`)
- Scopes granted tracking
- OAuthTokenRepository
- Refresh token handling
- Explicit account linking (`oauth/link/(:segment)`)
- Verified-email merge guard (`allowUnverifiedEmailLink`)
- Unlinking providers

### [TOTP Two-Factor Authentication](10-totp-2fa.md)
- Setup and enrollment flow
- Login flow with TOTP
- **Backup codes** for recovery
- **"Trust this device"** 2FA bypass
- Admin TOTP reset
- Managing TOTP in controllers
- Testing TOTP

### [WebAuthn / Passkeys](15-webauthn.md)
- Availability vs. enforcement (global flag, opt-in per user)
- **Passwordless login** (usernameless / discoverable)
- **Passkey as a second factor** (`Webauthn2FA` action)
- Enrollment, routes & JSON endpoints
- `HasWebAuthn` trait + dedicated `auth_webauthn_credentials` table
- Security invariants + the in-test `VirtualAuthenticator`

### [Device Sessions](11-device-sessions.md)
- Tracking logins per device
- Viewing and terminating sessions
- "Sign out everywhere" feature
- **Concurrent session limit**
- **Trusted devices** (linked to 2FA bypass)
- **Login activity feed** (user-facing)
- Admin CLI termination

### [Audit Log & Compliance](13-audit-and-compliance.md)
- Granular audit log (`auth_audit_logs`)
- Suspicious login detection + email alerts
- Compromised-password recheck on login
- Password history (no reuse, NIST SP 800-63B)
- Password rotation policy
- GDPR data export & account anonymization

### [CLI Commands](14-cli-commands.md)
- `auth:setup`, `auth:discover`
- `auth:user` — user CRUD
- `auth:tokens revoke`, `auth:sessions terminate`, `auth:totp reset`
- `auth:audit` — query the audit log
- `auth:gdpr export|anonymize`
- `auth:purge [--days <n>]` — scheduled cleanup of expired remember-me tokens and old terminated device sessions

## Feature Matrix

### Authentication
| Feature | Status |
|---------|--------|
| Session authentication | ✅ |
| Access Token (API keys) | ✅ |
| Access Token scope enforcement (`token-scope:` filter) | ✅ |
| JWT + Refresh Tokens (one-time-use rotation) | ✅ |
| JWT **token-version revocation** ("log out everywhere" via `User::revokeIssuedTokens()`) | ✅ |
| Magic Link (passwordless) — link & code modes | ✅ |
| Email Two-Factor Auth | ✅ |
| TOTP Two-Factor Auth | ✅ |
| TOTP **backup codes** | ✅ |
| TOTP **"Trust this device"** | ✅ |
| TOTP **rate-limited + anti-replay** verification | ✅ |
| OAuth 2.0 Social Login (Google/GitHub/Facebook/Azure/Generic) | ✅ |
| OAuth **explicit account linking** (`oauth/link/(:segment)`) | ✅ |

### Authorization
| Feature | Status |
|---------|--------|
| Groups & Permissions (RBAC) | ✅ |
| Permission Cache (persistent) | ✅ |
| API token scopes / abilities | ✅ |
| **Gate → RBAC fallback** (`gateFallbackToRbac`) | ✅ |

### Account safety
| Feature | Status |
|---------|--------|
| Password Reset | ✅ |
| Force Password Reset | ✅ |
| **Password rotation policy** (`password-age` filter) | ✅ |
| **Password history** (no reuse) | ✅ |
| Per-User Account Lockout (atomic, covers password **and** TOTP) | ✅ |
| IP-Based Attempt Blocking | ✅ |
| Rate Limiting (per-route `rates:<limit>,<period>` override) | ✅ |
| **Compromised-password recheck on login** (HIBP) | ✅ |
| **Suspicious login detection** + alerts | ✅ |
| **Remember-me theft detection** (`remember-me-theft` event) | ✅ |
| **Sudo mode** (per-route `password-confirm:<seconds>`) | ✅ |
| **Hashed-at-rest tokens** (access/JWT/magic-link/password-reset) | ✅ |
| **Concurrent session limit** | ✅ |

### Operations & compliance
| Feature | Status |
|---------|--------|
| Device Session Tracking (**live revocation** invalidates the session) | ✅ |
| **Granular audit log** (`auth_audit_logs`) | ✅ |
| **Login activity feed** (user-facing) | ✅ |
| **GDPR export / anonymize** | ✅ |
| **Admin CLI** (tokens / sessions / totp / audit / gdpr / purge) | ✅ |
| **Scheduled maintenance** (`auth:purge`) | ✅ |
| Bootstrap 5 Admin Panel | ✅ |

### Misc
| Feature | Status |
|---------|--------|
| UUID Dual-Key Pattern | ✅ |
| Pre-Auth Events | ✅ |
| Email Change Confirmation | ✅ |
| OAuth Provider Unlinking | ✅ |
| OAuth Profile Fields & Resolvers | ✅ |
| OAuth Events (`oauth-login`, `oauth-profile-fetched`) | ✅ |
| Self-Service Password Change | ✅ |

## Quick Links

- **GitHub**: [daycry/auth](https://github.com/daycry/auth)
- **CodeIgniter 4**: [Official Documentation](https://codeigniter4.github.io/)
- **Packagist**: [daycry/auth](https://packagist.org/packages/daycry/auth)

---

> Start with the [Quick Start Guide](01-quick-start.md) if it is your first time using Daycry Auth.
