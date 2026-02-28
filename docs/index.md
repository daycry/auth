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
:caption: Testing & Reference

08-testing
```

## Main Features

- **Multiple Authenticators**: Session, Access Token, JWT (with refresh tokens), Magic Link
- **TOTP Two-Factor Authentication**: Time-based OTP with Google Authenticator, Authy, etc.
- **Device Session Tracking**: See and revoke active logins per device
- **Password Reset Flow**: Secure token-based reset with email delivery
- **Force Password Reset**: Flag accounts for mandatory password change
- **Permission System**: Groups and granular permissions with optional cache
- **Flexible Filters**: Auth, chain, group, permission, rate limiting, force-reset
- **OAuth 2.0 / Social Login**: Google, GitHub, Facebook, Microsoft Azure
- **Per-User Account Lockout**: Independent of IP-based blocking
- **Complete Logging**: CI4 Events + database login attempt logs
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
Google, GitHub, Facebook, Microsoft Azure — and any OIDC provider.

### [TOTP Two-Factor Authentication](10-totp-2fa.md)
Time-based OTP with authenticator apps.

### [Device Sessions](11-device-sessions.md)
Track and manage active logins across devices.

### [Security Filters](04-filters.md)
Protect routes with authentication and authorization filters.

### [Controllers](05-controllers.md)
All included controllers: Login, Register, Password Reset, Force Reset, JWT, UserSecurity.

### [Authorization](06-authorization.md)
Groups, permissions, permission cache, and RBAC patterns.

### [Logging & Monitoring](07-logging.md)
CI4 Events, database logs, per-user lockout, and rate limiting.

### [Testing](08-testing.md)
Unit and integration testing with authentication mocking.

## Additional Resources

- **GitHub**: [daycry/auth](https://github.com/daycry/auth)
- **CodeIgniter 4 Docs**: [codeigniter4.github.io](https://codeigniter4.github.io/)
- **Packagist**: [packagist.org/packages/daycry/auth](https://packagist.org/packages/daycry/auth)
- **Issues**: [github.com/daycry/auth/issues](https://github.com/daycry/auth/issues)
