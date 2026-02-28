# 📱 Device Sessions

Device Sessions let you track every device and browser from which a user has logged in. Users can see their active sessions and sign out from any device remotely — the same "Manage active sessions" feature you see in apps like GitHub, Google, or Slack.

## 📋 Table of Contents

- [How It Works](#how-it-works)
- [Configuration](#configuration)
- [Database Migration](#database-migration)
- [Viewing Active Sessions](#viewing-active-sessions)
- [Terminating Sessions](#terminating-sessions)
- [UserSecurityController Integration](#usersecuritycontroller-integration)
- [Building a Sessions Management Page](#building-a-sessions-management-page)
- [Testing Device Sessions](#testing-device-sessions)

---

## How It Works

When a user logs in, a record is created in the `auth_device_sessions` table containing:

| Field | Description |
|-------|-------------|
| `user_id` | The user who logged in |
| `uuid` | A unique identifier for this session |
| `ip_address` | The IP address at login time |
| `user_agent` | The browser/device string |
| `last_active` | Timestamp of the most recent activity |
| `created_at` | When the session was created |

When the user logs out, the session record is updated with a `logged_out_at` timestamp.

---

## Configuration

Enable device session tracking in `app/Config/Auth.php`:

```php
public array $sessionConfig = [
    'field'                => 'user',
    'allowRemembering'     => true,
    'rememberCookieName'   => 'remember',
    'rememberLength'       => 30 * DAY,

    // Enable device session tracking
    'trackDeviceSessions'  => true,
];
```

With `trackDeviceSessions` set to `true`, every successful login call to `Session::startLogin()` automatically creates a device session record.

---

## Database Migration

Device sessions require the `auth_device_sessions` table, which is created by the migration at:

```
src/Database/Migrations/2026-02-26-000002_create_device_sessions.php
```

Run it with:

```bash
php spark migrate --all
```

The table also includes a `uuid` column for safe external exposure (never expose the integer `id` directly in APIs or URLs).

---

## Viewing Active Sessions

The `HasDeviceSessions` trait is mixed into the `User` entity and provides all session management methods.

### Get All Sessions for a User

```php
$user     = auth()->user();
$sessions = $user->getDeviceSessions();

foreach ($sessions as $session) {
    echo $session->ip_address;      // "203.0.113.42"
    echo $session->user_agent;      // "Mozilla/5.0 (Macintosh; ...)"
    echo $session->created_at;      // "2026-02-28 10:30:00"
    echo $session->last_active;     // "2026-02-28 12:15:00"
    echo $session->uuid;            // "0195d8b2-..." (safe to expose)
}
```

### Get Only Active (Not Logged-Out) Sessions

```php
$activeSessions = $user->getActiveDeviceSessions();
```

### Identify the Current Session

Each device session is linked to the current PHP session ID. You can highlight the "current device" in the UI:

```php
$currentSessionId = session()->get('device_session_id');

foreach ($user->getActiveDeviceSessions() as $session) {
    $isCurrent = ($session->id === $currentSessionId);
}
```

---

## Terminating Sessions

### Terminate a Specific Session

```php
// Using the session's UUID (safe for URLs/forms)
$user->terminateDeviceSessionByUuid($uuid);

// Using the internal integer ID (internal use only)
$user->terminateDeviceSession($id);
```

### Terminate All Other Sessions (Keep Current)

Useful for "Sign out everywhere else" functionality:

```php
$currentSessionId = session()->get('device_session_id');
$user->terminateOtherDeviceSessions($currentSessionId);
```

### Terminate All Sessions (Including Current)

```php
$user->terminateAllDeviceSessions();
// Then redirect to login
```

---

## UserSecurityController Integration

Daycry Auth includes a `UserSecurityController` with ready-made actions for session management. Register the routes:

```php
// app/Config/Routes.php
$routes->group('security', ['filter' => 'session', 'namespace' => 'Daycry\Auth\Controllers'], static function ($routes) {
    // Device sessions
    $routes->get('sessions',                     'UserSecurityController::deviceSessionsView', ['as' => 'security-sessions']);
    $routes->delete('sessions/(:segment)',       'UserSecurityController::terminateDeviceSession/$1');
    $routes->delete('sessions/other/all',        'UserSecurityController::terminateOtherDeviceSessions');
});
```

---

## Building a Sessions Management Page

Here is a complete, working example of a device sessions page.

### Controller

```php
<?php

namespace App\Controllers;

use App\Controllers\BaseController;

class SecurityController extends BaseController
{
    public function sessionsView()
    {
        $user             = auth()->user();
        $sessions         = $user->getActiveDeviceSessions();
        $currentSessionId = session()->get('device_session_id');

        return view('security/sessions', [
            'sessions'         => $sessions,
            'currentSessionId' => $currentSessionId,
        ]);
    }

    public function terminateSession(string $uuid)
    {
        $user = auth()->user();
        $user->terminateDeviceSessionByUuid($uuid);

        return redirect()->back()->with('message', 'Session terminated successfully.');
    }

    public function terminateOtherSessions()
    {
        $user             = auth()->user();
        $currentSessionId = session()->get('device_session_id');

        $user->terminateOtherDeviceSessions($currentSessionId);

        return redirect()->back()->with('message', 'All other sessions have been terminated.');
    }
}
```

### View

```html
<!-- app/Views/security/sessions.php -->
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Active Sessions</h2>
        <?= form_open('security/sessions/other/all', ['method' => 'delete']) ?>
            <button type="submit" class="btn btn-warning btn-sm"
                    onclick="return confirm('Sign out all other sessions?')">
                Sign Out Everywhere Else
            </button>
        <?= form_close() ?>
    </div>

    <?php if (session('message')): ?>
        <div class="alert alert-success"><?= session('message') ?></div>
    <?php endif ?>

    <div class="list-group">
        <?php foreach ($sessions as $session): ?>
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <!-- Parse user agent for a friendly name -->
                    <strong>
                        <?php
                            // Simple device type detection
                            $ua = $session->user_agent ?? '';
                            if (str_contains($ua, 'Mobile')) echo '📱 Mobile';
                            elseif (str_contains($ua, 'Tablet')) echo '📟 Tablet';
                            else echo '💻 Desktop';
                        ?>
                    </strong>

                    <?php if ($session->id === $currentSessionId): ?>
                        <span class="badge bg-success ms-2">Current Session</span>
                    <?php endif ?>

                    <div class="text-muted small mt-1">
                        IP: <?= esc($session->ip_address) ?>
                        &nbsp;·&nbsp;
                        Last active: <?= esc($session->last_active) ?>
                        &nbsp;·&nbsp;
                        Signed in: <?= esc($session->created_at) ?>
                    </div>
                </div>

                <?php if ($session->id !== $currentSessionId): ?>
                    <?= form_open('security/sessions/' . esc($session->uuid), ['method' => 'delete']) ?>
                        <button type="submit" class="btn btn-outline-danger btn-sm">
                            Sign Out
                        </button>
                    <?= form_close() ?>
                <?php endif ?>
            </div>
        <?php endforeach ?>
    </div>
</div>
```

---

## New Device Login Notification

You can notify users when a login occurs from an unrecognized device by listening to the `login` event:

```php
<?php

// app/Config/Events.php
use CodeIgniter\Events\Events;

Events::on('login', static function ($user) {
    // Compare current IP/UA against known sessions
    $knownIps = array_column($user->getActiveDeviceSessions(), 'ip_address');
    $currentIp = service('request')->getIPAddress();

    if (! in_array($currentIp, $knownIps, true)) {
        // Send a notification email
        $email = service('email');
        $email->setTo($user->email)
              ->setSubject('New sign-in to your account')
              ->setMessage(
                  "A new sign-in to your account was detected from IP: {$currentIp}.\n" .
                  "If this wasn't you, please change your password immediately."
              )
              ->send();
    }
});
```

---

## Testing Device Sessions

```php
<?php

namespace Tests\Authentication;

use Tests\Support\DatabaseTestCase;

class DeviceSessionTest extends DatabaseTestCase
{
    public function testLoginCreatesDeviceSession(): void
    {
        // Ensure tracking is enabled
        $this->injectMockAttributes(['sessionConfig' => ['trackDeviceSessions' => true]]);

        $user = $this->createUser('user@example.com', 'secret');

        auth('session')->attempt(['email' => 'user@example.com', 'password' => 'secret']);

        $sessions = $user->getActiveDeviceSessions();
        $this->assertCount(1, $sessions);
    }

    public function testTerminateSessionRemovesRecord(): void
    {
        $this->injectMockAttributes(['sessionConfig' => ['trackDeviceSessions' => true]]);

        $user = $this->createUser('user@example.com', 'secret');
        auth('session')->attempt(['email' => 'user@example.com', 'password' => 'secret']);

        $sessions = $user->getActiveDeviceSessions();
        $this->assertCount(1, $sessions);

        $user->terminateDeviceSessionByUuid($sessions[0]->uuid);

        $this->assertCount(0, $user->getActiveDeviceSessions());
    }
}
```

---

## Security Tips

```{admonition} Best Practices
:class: tip

- Always use the `uuid` column when referencing sessions in URLs or API responses — never expose the integer `id`.
- Show the user a "when and from where" summary so they can spot unfamiliar sessions.
- Consider sending an email notification on new device logins for high-security applications.
- Pair device sessions with per-user account lockout for defense in depth.
```

---

🔗 **See also**:
- [TOTP Two-Factor Authentication](10-totp-2fa.md) — Additional login security
- [Password Reset](03-authentication.md#password-reset) — Let users recover access
- [Configuration](02-configuration.md) — Full `sessionConfig` options
