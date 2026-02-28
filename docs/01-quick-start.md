# Quick Start Guide

Get Daycry Auth running in your CodeIgniter 4 application in a few minutes.

## Requirements

- PHP 8.1 or higher
- CodeIgniter 4.4 or higher
- Composer

---

## Installation

### 1. Install via Composer

```bash
composer require daycry/auth
```

### 2. Run Migrations

Creates all the required database tables:

```bash
php spark migrate --all
```

### 3. Publish Configuration

Copies configuration files and basic route setup to your application:

```bash
php spark auth:setup
```

---

## Basic Configuration

Open `app/Config/Auth.php` (created by `auth:setup`):

```php
<?php

namespace Config;

use Daycry\Auth\Config\Auth as BaseAuth;

class Auth extends BaseAuth
{
    // Allow new user registration
    public bool $allowRegistration = true;

    // Default group assigned to every new user
    public string $defaultGroup = 'user';

    // Login with email (add 'username' to also allow username login)
    public array $validFields = ['email'];

    // Where to redirect after login/logout
    public array $redirects = [
        'register' => '/',
        'login'    => '/dashboard',
        'logout'   => 'login',
    ];
}
```

---

## Register Filters

In `app/Config/Filters.php`, add the auth filter aliases:

```php
public array $aliases = [
    // Authentication
    'session'     => \Daycry\Auth\Filters\AuthSessionFilter::class,
    'tokens'      => \Daycry\Auth\Filters\AuthAccessTokenFilter::class,
    'jwt'         => \Daycry\Auth\Filters\AuthJWTFilter::class,
    'chain'       => \Daycry\Auth\Filters\ChainFilter::class,

    // Authorization
    'group'       => \Daycry\Auth\Filters\GroupFilter::class,
    'permission'  => \Daycry\Auth\Filters\PermissionFilter::class,

    // Security
    'auth-rates'  => \Daycry\Auth\Filters\AuthRatesFilter::class,
    'force-reset' => \Daycry\Auth\Filters\ForcePasswordResetFilter::class,
];
```

---

## Set Up Routes

In `app/Config/Routes.php`:

```php
// Public auth routes
$routes->group('auth', ['namespace' => 'Daycry\Auth\Controllers'], static function ($routes) {
    $routes->get('login',    'LoginController::loginView',         ['as' => 'login']);
    $routes->post('login',   'LoginController::loginAction');
    $routes->get('register', 'RegisterController::registerView',   ['as' => 'register']);
    $routes->post('register','RegisterController::registerAction');
    $routes->get('logout',   'LoginController::logoutAction',      ['as' => 'logout']);

    // Password reset
    $routes->get('password-reset',         'PasswordResetController::requestView',  ['as' => 'password-reset']);
    $routes->post('password-reset',        'PasswordResetController::requestAction');
    $routes->get('password-reset/message', 'PasswordResetController::messageView',  ['as' => 'password-reset-message']);
    $routes->get('password-reset/(:any)',  'PasswordResetController::resetView');
    $routes->post('password-reset/(:any)', 'PasswordResetController::resetAction');
});

// Protected routes — must be logged in
$routes->group('dashboard', ['filter' => 'session'], static function ($routes) {
    $routes->get('/', 'Dashboard::index');
    $routes->get('profile', 'Dashboard::profile');
});

// Admin routes — must be logged in AND in the 'admin' group
$routes->group('admin', ['filter' => 'session,group:admin'], static function ($routes) {
    $routes->get('/', 'Admin::index');
    $routes->get('users', 'Admin::users');
});
```

---

## Your First Protected Controller

```php
<?php

namespace App\Controllers;

use Daycry\Auth\Controllers\BaseAuthController;
use CodeIgniter\HTTP\ResponseInterface;

class Dashboard extends BaseAuthController
{
    // Required by BaseAuthController
    protected function getValidationRules(): array
    {
        return [];
    }

    public function index(): ResponseInterface
    {
        $user = auth()->user();

        $content = $this->view('dashboard/index', [
            'user'  => $user,
            'title' => 'Dashboard',
        ]);

        return $this->response->setBody($content);
    }
}
```

---

## Authentication Helpers

```php
// Is the user logged in?
if (auth()->loggedIn()) { ... }

// Get the current user
$user = auth()->user();
echo $user->email;

// Check a permission
if ($user->can('posts.create')) { ... }

// Check group membership
if ($user->inGroup('admin')) { ... }

// Manual login attempt
$result = auth()->attempt([
    'email'    => 'user@example.com',
    'password' => 'secret',
]);

if ($result->isOK()) {
    return redirect()->to('/dashboard');
}

// Logout
auth()->logout();
return redirect()->route('login');
```

---

## What's Working Now

After following these steps, your application has:

| Page | URL | Who can access |
|------|-----|---------------|
| Login | `/auth/login` | Everyone |
| Register | `/auth/register` | Everyone |
| Forgot password | `/auth/password-reset` | Everyone |
| Dashboard | `/dashboard` | Authenticated users only |
| Admin | `/admin` | `admin` group only |

---

## Next Steps

1. **[Configuration](02-configuration.md)** — Customize passwords, 2FA, JWT, lockout settings
2. **[Filters](04-filters.md)** — Add rate limiting, force-reset, permission-based access
3. **[Authorization](06-authorization.md)** — Create groups and permissions
4. **[TOTP Two-Factor Auth](10-totp-2fa.md)** — Add authenticator app 2FA
5. **[OAuth / Social Login](09-oauth.md)** — Google, GitHub, Microsoft login
6. **[Device Sessions](11-device-sessions.md)** — Track and manage logins per device

## Troubleshooting

- **Migrations failed**: Check your database configuration in `app/Config/Database.php`
- **Routes 404**: Verify the namespace and controller names
- **Filters not working**: Confirm aliases are registered in `app/Config/Filters.php`
- **Check migration status**: `php spark migrate:status`
- **Check logs**: `writable/logs/`
