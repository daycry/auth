[![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.com/donate?business=SYC5XDT23UZ5G&no_recurring=0&item_name=Thank+you%21&currency_code=EUR)

# Daycry Auth

[![Tests](https://github.com/daycry/auth/actions/workflows/phpunit.yml/badge.svg?branch=main)](https://github.com/daycry/auth/actions/workflows/phpunit.yml)
[![Static Analysis](https://github.com/daycry/auth/actions/workflows/static-analysis.yml/badge.svg?branch=main)](https://github.com/daycry/auth/actions/workflows/static-analysis.yml)
[![Coverage Status](https://coveralls.io/repos/github/daycry/auth/badge.svg?branch=main)](https://coveralls.io/github/daycry/auth?branch=main)
[![Documentation Status](https://readthedocs.org/projects/authentication-for-codeigniter-4/badge/?version=latest)](https://authentication-for-codeigniter-4.readthedocs.io/en/latest/?badge=latest)
[![Downloads](https://poser.pugx.org/daycry/auth/downloads)](https://packagist.org/packages/daycry/auth)
[![GitHub release (latest by date)](https://img.shields.io/github/v/release/daycry/auth)](https://packagist.org/packages/daycry/auth)
[![GitHub stars](https://img.shields.io/github/stars/daycry/auth)](https://packagist.org/packages/daycry/auth)
[![GitHub license](https://img.shields.io/github/license/daycry/auth)](https://github.com/daycry/auth/blob/main/LICENSE)

A comprehensive authentication and authorization library for **CodeIgniter 4**, designed to be flexible, secure, and easy to extend.

```bash
composer require daycry/auth
```

---

## Features

### Authentication Methods

| Method | Description |
|--------|-------------|
| **Session** | Email/password with secure remember-me cookies |
| **Access Token** | Long-lived API keys sent via `X-API-KEY` header |
| **JWT** | Stateless Bearer tokens with refresh token rotation |
| **Magic Link** | Passwordless login via one-time email link |
| **OAuth 2.0** | Social login: Google, GitHub, Facebook, Microsoft Azure |

### Security Features

| Feature | Description |
|---------|-------------|
| **TOTP Two-Factor Auth** | Time-based OTP (Google Authenticator, Authy, 1Password) |
| **Email Two-Factor Auth** | 6-digit code sent to user's email after login |
| **Email Activation** | Require email confirmation before first login |
| **Per-User Account Lockout** | Lock account after N failed attempts (independent of IP) |
| **IP-Based Blocking** | Block IPs that exceed failed attempt limits |
| **Rate Limiting** | Per-IP, per-user, or per-endpoint request throttling |
| **Force Password Reset** | Flag accounts for mandatory password change |
| **Password Reset Flow** | Secure token-based reset with email delivery |
| **Self-Service Email Change** | Change email with confirmation link to new address |
| **Access Token Revocation** | Soft-revoke tokens without deleting them |
| **Device Session Tracking** | See and terminate active logins per device/browser |
| **UUID Dual-Key Pattern** | Internal `id` (INT) + external `uuid` (UUID v7) on users |

### Authorization

| Feature | Description |
|---------|-------------|
| **Groups** | Named roles (e.g., `admin`, `editor`, `user`) |
| **Permissions** | Granular actions (e.g., `posts.create`, `users.delete`) |
| **Permission Inheritance** | Users inherit all permissions from their groups |
| **Wildcard Permissions** | `posts.*` grants all post-related permissions |
| **Permission Cache** | Configurable TTL cache to avoid repeated DB queries |
| **Route Filters** | `group:admin`, `permission:posts.edit` directly on routes |

### Developer Experience

| Feature | Description |
|---------|-------------|
| **BaseAuthController** | Abstract base with validation, redirect, and error helpers |
| **Bootstrap 5 Admin Panel** | Manage users, groups, permissions, and logs via UI |
| **OAuth Provider Unlinking** | Let users disconnect social accounts |
| **Pre-Auth Events** | `pre-login` and `pre-register` CodeIgniter Events |
| **CI4 Events System** | Hook into `login`, `logout`, `registered`, `passwordReset`, etc. |
| **Chain Authenticator** | Try session → access_token → JWT automatically |
| **Custom Authenticators** | Extend `Base` with full Dependency Injection support |

---

## Quick Start

### Requirements

- PHP **8.1** or higher
- CodeIgniter **4.4** or higher
- Composer

### Installation

```bash
# 1. Install the package
composer require daycry/auth

# 2. Run migrations (creates all auth tables)
php spark migrate --all

# 3. Publish config files and basic routes
php spark auth:setup
```

### Basic Usage

```php
// Login
$result = auth()->attempt([
    'email'    => 'user@example.com',
    'password' => 'secret',
]);

if ($result->isOK()) {
    return redirect()->to('/dashboard');
}

// Check authentication
if (auth()->loggedIn()) {
    $user = auth()->user();
    echo $user->email;
}

// Check authorization
if ($user->can('posts.create')) { ... }
if ($user->inGroup('admin')) { ... }

// Logout
auth()->logout();
```

### Protect Routes

```php
// app/Config/Routes.php

// Require login
$routes->group('dashboard', ['filter' => 'session'], static function ($routes) {
    $routes->get('/', 'Dashboard::index');
});

// Require login + admin group
$routes->group('admin', ['filter' => 'session,group:admin'], static function ($routes) {
    $routes->get('/', 'Admin::index');
});

// Require a specific permission
$routes->post('posts/delete/(:num)', 'PostController::delete/$1', [
    'filter' => 'session,permission:posts.delete',
]);

// API with JWT
$routes->group('api', ['filter' => 'jwt'], static function ($routes) {
    $routes->get('profile', 'API\ProfileController::show');
});
```

### JWT with Refresh Tokens (API)

```bash
# Login → get access + refresh token
POST /auth/jwt/login
email=user@example.com&password=secret

# Use access token
GET /api/profile
Authorization: Bearer eyJ0eXAi...

# Refresh when expired
POST /auth/jwt/refresh
user_id=42&refresh_token=a3f8c2d1...

# Logout (revoke refresh token)
POST /auth/jwt/logout
user_id=42&refresh_token=a3f8c2d1...
```

---

## Documentation

Full documentation is available at:

**[https://authentication-for-codeigniter-4.readthedocs.io/](https://authentication-for-codeigniter-4.readthedocs.io/)**

| Section | Description |
|---------|-------------|
| [Quick Start](https://authentication-for-codeigniter-4.readthedocs.io/en/latest/01-quick-start.html) | Install and set up in minutes |
| [Configuration](https://authentication-for-codeigniter-4.readthedocs.io/en/latest/02-configuration.html) | Every config option explained |
| [Authentication](https://authentication-for-codeigniter-4.readthedocs.io/en/latest/03-authentication.html) | All auth methods + JWT refresh + password reset |
| [Filters](https://authentication-for-codeigniter-4.readthedocs.io/en/latest/04-filters.html) | Route protection filters |
| [Controllers](https://authentication-for-codeigniter-4.readthedocs.io/en/latest/05-controllers.html) | All included controllers |
| [Authorization](https://authentication-for-codeigniter-4.readthedocs.io/en/latest/06-authorization.html) | Groups, permissions, RBAC |
| [Logging & Events](https://authentication-for-codeigniter-4.readthedocs.io/en/latest/07-logging.html) | CI4 Events, DB logs, lockout |
| [Testing](https://authentication-for-codeigniter-4.readthedocs.io/en/latest/08-testing.html) | Testing auth in your app |
| [OAuth 2.0](https://authentication-for-codeigniter-4.readthedocs.io/en/latest/09-oauth.html) | Google, GitHub, Facebook, Azure |
| [TOTP 2FA](https://authentication-for-codeigniter-4.readthedocs.io/en/latest/10-totp-2fa.html) | Authenticator app integration |
| [Device Sessions](https://authentication-for-codeigniter-4.readthedocs.io/en/latest/11-device-sessions.html) | Active session management |

---

## Contributing

Contributions of all kinds are welcome — code, documentation, bug reports, or feedback. See [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

This project is licensed under the MIT License — see the [LICENSE](LICENSE) file for details.

## Acknowledgements

<a href="https://github.com/daycry/auth/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=daycry/auth" />
</a>

Made with [contrib.rocks](https://contrib.rocks).

Security design informed by:

- [NIST Digital Identity Guidelines (SP 800-63B)](https://pages.nist.gov/800-63-3/sp800-63b.html)
- [Google Cloud: Best practices for user account, authentication, and password management](https://cloud.google.com/blog/products/identity-security/account-authentication-and-password-management-best-practices)
- [OWASP Password Storage Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html)
- [Secure "Remember Me" Cookies (paragonie.com)](https://paragonie.com/blog/2015/04/secure-authentication-php-with-long-term-persistence)
