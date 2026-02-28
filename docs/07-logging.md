# 📊 Logging, Events & Monitoring

Daycry Auth provides two independent logging systems that complement each other:

1. **CodeIgniter Events** — lightweight hooks fired at authentication milestones; listened to in your app code
2. **Database Logs** — persistent records of login attempts, access logs, and rate limiting stored in dedicated tables

---

## 📋 Table of Contents

- [CodeIgniter Events](#codeigniter-events)
- [Available Events](#available-events)
- [Listening to Events](#listening-to-events)
- [Pre-Authentication Events](#pre-authentication-events)
- [Database Logging](#database-logging)
- [Login Attempt Logging](#login-attempt-logging)
- [Failed Attempt Blocking](#failed-attempt-blocking)
- [Per-User Account Lockout](#per-user-account-lockout)
- [Rate Limiting](#rate-limiting)
- [Monitoring & Querying Logs](#monitoring--querying-logs)

---

## CodeIgniter Events

Daycry Auth fires CI4 events at key authentication moments. Your application code listens to these events in `app/Config/Events.php` — no modifications to the library are needed.

### Register Event Listeners

```php
<?php
// app/Config/Events.php
use CodeIgniter\Events\Events;

// Runs after a successful login
Events::on('login', static function (object $user): void {
    log_message('info', "User {$user->email} logged in.");
});

// Runs after a failed login attempt
Events::on('failedLogin', static function (array $credentials): void {
    log_message('warning', "Failed login attempt for: " . ($credentials['email'] ?? 'unknown'));
});

// Runs after logout
Events::on('logout', static function (object $user): void {
    log_message('info', "User {$user->email} logged out.");
});

// Runs after successful registration
Events::on('registered', static function (object $user): void {
    // Send a welcome email, assign a default group, etc.
    service('email')
        ->setTo($user->email)
        ->setSubject('Welcome!')
        ->setMessage("Thanks for signing up!")
        ->send();
});
```

---

## Available Events

| Event | When Fired | Argument |
|-------|-----------|----------|
| `pre-login` | Before credentials are checked | `array $credentials` |
| `login` | After successful login | `User $user` |
| `failedLogin` | After a failed login attempt | `array $credentials` |
| `logout` | After logout | `User $user` |
| `pre-register` | Before registration is processed | `array $postData` |
| `registered` | After successful registration | `User $user` |
| `passwordReset` | After password successfully reset | `User $user` |
| `magicLogin` | After magic link login | `User $user` |

---

## Listening to Events

### Security Alert on Multiple Failed Logins

```php
Events::on('failedLogin', static function (array $credentials): void {
    $email = $credentials['email'] ?? null;
    if ($email === null) {
        return;
    }

    // Count recent failures from the auth_logins table
    $recentFailures = model(\Daycry\Auth\Models\LoginModel::class)
        ->where('identifier', $email)
        ->where('success', 0)
        ->where('created_at >', date('Y-m-d H:i:s', strtotime('-15 minutes')))
        ->countAllResults();

    if ($recentFailures >= 5) {
        // Alert the security team
        log_message('critical', "Brute-force suspected for account: {$email}");
    }
});
```

### Welcome Email on Registration

```php
Events::on('registered', static function (object $user): void {
    $emailService = service('email');
    $emailService->setTo($user->email)
                 ->setSubject('Welcome to ' . config('App')->appName)
                 ->setMessage(view('emails/welcome', ['user' => $user]))
                 ->send();
});
```

### Audit Trail for Password Resets

```php
Events::on('passwordReset', static function (object $user): void {
    log_message('notice', "Password reset completed for user ID {$user->id} ({$user->email}).");
    // Write to an audit log table
    db_connect()->table('audit_log')->insert([
        'user_id'    => $user->id,
        'action'     => 'password_reset',
        'created_at' => date('Y-m-d H:i:s'),
    ]);
});
```

---

## Pre-Authentication Events

`pre-login` and `pre-register` fire **before** any database check. Listeners can inspect or enrich the data. They cannot cancel the operation directly, but can redirect via `redirect()`.

### Log All Login Attempts

```php
Events::on('pre-login', static function (array $credentials): void {
    $ip = service('request')->getIPAddress();
    log_message('debug', "Login attempt for '{$credentials['email']}' from IP {$ip}");
});
```

### Block Specific Domains from Registering

```php
Events::on('pre-register', static function (array $data): void {
    $email  = $data['email'] ?? '';
    $domain = substr(strrchr($email, '@'), 1);

    $blockedDomains = ['tempmail.com', 'throwaway.email'];

    if (in_array($domain, $blockedDomains, true)) {
        // Redirect before processing — effectively cancels registration
        redirect()->to('/register')->with('error', 'Disposable email addresses are not allowed.')->send();
        exit;
    }
});
```

---

## Database Logging

### Enable Activity Logs

```php
// app/Config/Auth.php
public bool $enableLogs = true;
```

When enabled, authentication events are written to the `auth_logs` table. The log includes the user ID, action type, IP address, and timestamp.

### Query Logs

```php
// Get recent login events for a user
$logs = model(\Daycry\Auth\Models\LogModel::class)
    ->where('user_id', $userId)
    ->orderBy('created_at', 'DESC')
    ->limit(20)
    ->findAll();

foreach ($logs as $entry) {
    echo "[{$entry->created_at}] {$entry->action} from {$entry->ip_address}" . PHP_EOL;
}
```

---

## Login Attempt Logging

Track all login attempts (success and failure) in the `auth_logins` table.

### Configuration

```php
// app/Config/Auth.php

// Options:
// Auth::RECORD_LOGIN_ATTEMPT_NONE    (0) - Don't record anything
// Auth::RECORD_LOGIN_ATTEMPT_FAILURE (1) - Only record failures
// Auth::RECORD_LOGIN_ATTEMPT_ALL     (2) - Record everything (default)
public int $recordLoginAttempt = \Daycry\Auth\Config\Auth::RECORD_LOGIN_ATTEMPT_ALL;
```

### What Gets Stored

| Column | Description |
|--------|-------------|
| `ip_address` | IP address of the request |
| `identifier` | The email (or username) used |
| `credential_type` | Type of credential (e.g., `email_password`) |
| `success` | `1` for success, `0` for failure |
| `user_agent` | Browser/client string |
| `created_at` | Timestamp |

### Query Login Attempts

```php
use Daycry\Auth\Models\LoginModel;

$loginModel = model(LoginModel::class);

// All failures in the last hour from a specific IP
$suspiciousAttempts = $loginModel
    ->where('ip_address', '203.0.113.42')
    ->where('success', 0)
    ->where('created_at >', date('Y-m-d H:i:s', strtotime('-1 hour')))
    ->countAllResults();
```

---

## Failed Attempt Blocking

Daycry Auth can automatically block an IP address that fails login too many times in a row.

### Configuration

```php
// app/Config/Auth.php

// Enable IP-based failed attempt blocking
public bool $enableInvalidAttempts = true;

// Maximum failed attempts before blocking
public int $maxAttempts = 10;

// How long to block the IP (seconds)
public int $timeBlocked = 3600; // 1 hour
```

When an IP address exceeds `$maxAttempts`, all subsequent requests from that IP receive a "too many attempts" error until `$timeBlocked` seconds have passed.

> **Note**: This is IP-level blocking. For per-user account lockout, see the section below.

---

## Per-User Account Lockout

Independent of IP blocking, you can lock individual user accounts after too many failed password attempts. This is stored on the `users` table (`failed_login_count`, `locked_until`).

### Configuration

```php
// app/Config/Auth.php

// Maximum failed password attempts before locking the account
// Set to 0 to disable per-user lockout
public int $userMaxAttempts = 5;

// How long to lock the account (seconds)
public int $userLockoutTime = 3600; // 1 hour
```

### How It Works

1. A user fails to log in → `failed_login_count` increments
2. When `failed_login_count >= userMaxAttempts` → `locked_until` is set
3. Any login attempt before `locked_until` returns an error with minutes remaining
4. After lockout expires → counter resets automatically on next attempt
5. After a **successful** login → counter resets to 0

### Unlocking a User Manually

```php
// In an admin controller or command
$user = model(\Daycry\Auth\Models\UserModel::class)->find($userId);

model(\Daycry\Auth\Models\UserModel::class)->update($user->id, [
    'failed_login_count' => 0,
    'locked_until'       => null,
]);
```

---

## Rate Limiting

Rate limiting controls how many requests a client can make in a time window, independent of login failures.

### Configuration

```php
// app/Config/Auth.php

// How to identify the rate limit subject
// Options: 'IP_ADDRESS', 'USER', 'METHOD_NAME', 'ROUTED_URL'
public string $limitMethod = 'IP_ADDRESS';

// Maximum requests per window
public int $requestLimit = 60;

// Time window (seconds)
public int $timeLimit = MINUTE;
```

### Apply Rate Limiting to Routes

```php
// app/Config/Routes.php

// Limit all auth routes
$routes->group('auth', ['filter' => 'auth-rates'], static function ($routes) {
    $routes->post('login',    'LoginController::loginAction');
    $routes->post('register', 'RegisterController::registerAction');
});
```

```php
// app/Config/Filters.php
public array $aliases = [
    'auth-rates' => \Daycry\Auth\Filters\AuthRatesFilter::class,
];
```

---

## Monitoring & Querying Logs

### Dashboard Statistics

```php
use Daycry\Auth\Models\LoginModel;

$loginModel = model(LoginModel::class);

$stats = [
    'total_logins_today' => $loginModel
        ->where('success', 1)
        ->where('created_at >', date('Y-m-d 00:00:00'))
        ->countAllResults(),

    'failed_today' => $loginModel
        ->where('success', 0)
        ->where('created_at >', date('Y-m-d 00:00:00'))
        ->countAllResults(),

    'unique_ips_today' => $loginModel
        ->select('ip_address')
        ->where('created_at >', date('Y-m-d 00:00:00'))
        ->distinct()
        ->countAllResults(),
];
```

### Find Locked Accounts

```php
$lockedUsers = model(\Daycry\Auth\Models\UserModel::class)
    ->where('locked_until >', date('Y-m-d H:i:s'))
    ->findAll();

foreach ($lockedUsers as $user) {
    echo "{$user->email} — locked until {$user->locked_until}" . PHP_EOL;
}
```

### CI4 Log Files

Beyond the database, standard CI4 log files in `writable/logs/` capture log messages written with `log_message()`:

```php
Events::on('failedLogin', static function (array $credentials): void {
    log_message('warning', 'Failed login: ' . json_encode($credentials));
});
```

Set the log threshold in `app/Config/Logger.php`:

```php
public int $threshold = 4; // 0=disabled, 1=emergency, 4=warning+, 7=info+, 8=debug
```

---

🔗 **See also**:
- [Configuration](02-configuration.md) — All logging configuration options
- [Per-User Lockout & Password Reset](03-authentication.md) — Security features
- [Filters](04-filters.md) — `auth-rates` filter
