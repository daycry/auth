# Daycry Auth — Documentation

Complete documentation for **Daycry Auth**, a comprehensive authentication and authorization library for CodeIgniter 4 (PHP 8.1+).

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

### [Authentication](03-authentication.md)
- Session authenticator (web apps)
- Access Token authenticator (APIs)
- JWT authenticator + refresh token rotation
- Magic Link (passwordless)
- Password Reset flow
- Force Password Reset
- Pre-authentication events

### [Security Filters](04-filters.md)
- `session`, `tokens`, `jwt`, `chain` authentication filters
- `group`, `permission` authorization filters
- `auth-rates` rate limiting
- `force-reset` forced password change

### [Controllers](05-controllers.md)
- `LoginController`, `RegisterController`
- `PasswordResetController`, `ForcePasswordResetController`
- `JwtController` (stateless login/refresh/logout)
- `UserSecurityController` (change password, email, OAuth unlink)
- Creating custom controllers

### [Authorization](06-authorization.md)
- Groups (roles) and permissions
- Permission inheritance and wildcards
- Permission cache (for production)
- Route filter usage
- RBAC patterns and best practices

### [Logging & Monitoring](07-logging.md)
- CodeIgniter Events (pre-login, login, logout, register, passwordReset)
- Database login attempt logs
- IP-based failed attempt blocking
- Per-user account lockout
- Rate limiting

### [Testing](08-testing.md)
- Running the test suite
- DatabaseTestCase setup
- Authentication mocking
- Testing filters, controllers, models

### [OAuth 2.0 & Social Login](09-oauth.md)
- Google, GitHub, Facebook, Microsoft Azure
- Generic OIDC provider
- Refresh token handling
- Unlinking providers

### [TOTP Two-Factor Authentication](10-totp-2fa.md)
- Setup and enrollment flow
- Login flow with TOTP
- Managing TOTP in controllers
- Testing TOTP

### [Device Sessions](11-device-sessions.md)
- Tracking logins per device
- Viewing and terminating sessions
- "Sign out everywhere" feature
- New device login notifications

## Feature Matrix

| Feature | Status |
|---------|--------|
| Session authentication | ✅ Complete |
| Access Token (API keys) | ✅ Complete |
| JWT + Refresh Tokens | ✅ Complete |
| Magic Link (passwordless) | ✅ Complete |
| Password Reset | ✅ Complete |
| Force Password Reset | ✅ Complete |
| TOTP Two-Factor Auth | ✅ Complete |
| Email Two-Factor Auth | ✅ Complete |
| Device Session Tracking | ✅ Complete |
| OAuth 2.0 Social Login | ✅ Complete |
| Groups & Permissions (RBAC) | ✅ Complete |
| Permission Cache | ✅ Complete |
| Per-User Account Lockout | ✅ Complete |
| IP-Based Attempt Blocking | ✅ Complete |
| Rate Limiting | ✅ Complete |
| UUID Dual-Key Pattern | ✅ Complete |
| Bootstrap 5 Admin Panel | ✅ Complete |
| Email Change Confirmation | ✅ Complete |
| OAuth Provider Unlinking | ✅ Complete |
| Pre-Auth Events | ✅ Complete |
| Self-Service Password Change | ✅ Complete |

## Quick Links

- **GitHub**: [daycry/auth](https://github.com/daycry/auth)
- **CodeIgniter 4**: [Official Documentation](https://codeigniter4.github.io/)
- **Packagist**: [daycry/auth](https://packagist.org/packages/daycry/auth)

---

> Start with the [Quick Start Guide](01-quick-start.md) if it is your first time using Daycry Auth.
