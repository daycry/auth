# Quick Start Guide

This guide will help you set up Daycry Auth in your CodeIgniter 4 project in just a few minutes.

## Requirements

- PHP 8.1 or higher
- CodeIgniter 4.4 or higher
- Composer

## 🔧 Installation

### 1. Install via Composer

```bash
composer require daycry/auth
```

### 2. Run Migrations

```bash
php spark migrate --all
```

### 3. Publish Configuration

```bash
php spark auth:setup
```

This command:
- Copies configuration files
- Creates basic routes
- Sets up necessary filters

## ⚙️ Basic Configuration

### 1. Configure Database

In `app/Config/Database.php`:

```php
public array $default = [
    'hostname' => 'localhost',
    'username' => 'your_username',
    'password' => 'your_password',
    'database' => 'your_database',
    // ... other options
];
```

### 2. Configure Daycry Auth

The `app/Config/Auth.php` file is created automatically. Basic configurations:

```php
<?php

namespace Config;

use Daycry\Auth\Config\Auth as BaseAuth;

class Auth extends BaseAuth
{
    // Allow registration of new users
    public bool $allowRegistration = true;
    
    // Default group for new users
    public string $defaultGroup = 'user';
    
    // Valid fields for login
    public array $validFields = ['email'];
    
    // Redirect URLs
    public array $redirects = [
        'register' => '/',
        'login'    => '/dashboard',
        'logout'   => '/login',
    ];
}
```

## 🛡️ Configure Basic Filters

### 1. Register Filters in `app/Config/Filters.php`

```php
<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Filters extends BaseConfig
{
    public array $aliases = [
        // ... other filters
        
        // Authentication Filters
        'session'      => \Daycry\Auth\Filters\AuthSessionFilter::class,
        'tokens'       => \Daycry\Auth\Filters\AuthAccessTokenFilter::class,
        'jwt'          => \Daycry\Auth\Filters\AuthJWTFilter::class,
        'chain'        => \Daycry\Auth\Filters\ChainFilter::class,
        
        // Authorization Filters
        'group'        => \Daycry\Auth\Filters\GroupFilter::class,
        'permission'   => \Daycry\Auth\Filters\PermissionFilter::class,
        
        // Additional Filters
        'auth-rates'   => \Daycry\Auth\Filters\AuthRatesFilter::class,
        'force-reset'  => \Daycry\Auth\Filters\ForcePasswordResetFilter::class,
    ];

    public array $globals = [
        'before' => [
            // 'auth-rates', // Optional: global rate limiting
        ],
        'after' => [
            // filters after response
        ],
    ];
}
```

### 2. Configure Routes with Filters

In `app/Config/Routes.php`:

```php
<?php

// Public routes (no authentication)
$routes->get('/', 'Home::index');
$routes->group('auth', ['namespace' => 'Daycry\Auth\Controllers'], function($routes) {
    $routes->get('login', 'LoginController::loginView');
    $routes->post('login', 'LoginController::loginAction');
    $routes->get('register', 'RegisterController::registerView');
    $routes->post('register', 'RegisterController::registerAction');
    $routes->get('logout', 'LoginController::logoutAction');
});

// Protected routes (require authentication)
$routes->group('dashboard', ['filter' => 'session'], function($routes) {
    $routes->get('/', 'Dashboard::index');
    $routes->get('profile', 'Dashboard::profile');
});

// Admin routes (require 'admin' group)
$routes->group('admin', ['filter' => 'session,group:admin'], function($routes) {
    $routes->get('/', 'Admin::index');
    $routes->get('users', 'Admin::users');
});
```

## 🎮 First Controller with Authentication

### 1. Create a Basic Controller

```php
<?php

namespace App\Controllers;

use Daycry\Auth\Controllers\BaseAuthController;

class Dashboard extends BaseAuthController
{
    public function index()
    {
        // User is automatically authenticated by the filter
        $user = auth()->user();
        
        return $this->view('dashboard/index', [
            'user' => $user,
            'title' => 'Dashboard'
        ]);
    }
    
    public function profile()
    {
        $user = auth()->user();
        
        return $this->view('dashboard/profile', [
            'user' => $user,
            'title' => 'My Profile'
        ]);
    }
    
    // Method required by BaseAuthController
    protected function getValidationRules(): array
    {
        return []; // We don't need special validation in this controller
    }
}
```

### 2. Create Views

**`app/Views/dashboard/index.php`**:
```php
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="container">
    <h1>Welcome, <?= esc($user->username ?? $user->email) ?>!</h1>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Your Profile</h5>
                    <p class="card-text">Manage your personal information.</p>
                    <a href="<?= site_url('dashboard/profile') ?>" class="btn btn-primary">View Profile</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
```

## 🔐 Authentication Helpers

Daycry Auth provides useful helpers:

```php
// Check if user is logged in
if (auth()->loggedIn()) {
    echo "User authenticated";
}

// Get current user
$user = auth()->user();
echo "Hello " . $user->email;

// Check permissions
if (auth()->user()->can('admin.users.edit')) {
    echo "Can edit users";
}

// Check groups
if (auth()->user()->inGroup('admin')) {
    echo "Is administrator";
}

// Manual login
$credentials = [
    'email' => 'user@example.com',
    'password' => 'password123'
];

if (auth()->attempt($credentials)) {
    echo "Login successful";
}

// Logout
auth()->logout();
```

## 🎯 Next Steps

Congratulations! You now have Daycry Auth working. Now you can:

1. **[Explore Detailed Configuration](02-configuration.md)** - Customize all options
2. **[Learn about Filters](04-filters.md)** - Set up advanced security filters  
3. **[Master Controllers](05-controllers.md)** - Create robust controllers
4. **[Configure Authorization](06-authorization.md)** - Implement granular permissions

## 🆘 Problems?

If you encounter any issues:

1. Check the [FAQ](10-faq.md) for common solutions
2. Verify migrations ran correctly: `php spark migrate:status`
3. Check logs in `writable/logs/`
4. Make sure database configuration is correct

## 📝 Complete Working Example

After following this guide, you should be able to:

1. Visit `/auth/register` to create an account
2. Visit `/auth/login` to log in
3. Access `/dashboard` (protected by authentication)
4. View `/admin` only if you have the 'admin' group

Your application now has a complete and functional authentication system!
