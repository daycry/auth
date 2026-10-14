<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

use CodeIgniter\Events\Events;
use Daycry\Auth\Entities\User;

/*
 * Library event listeners, auto-discovered by CodeIgniter's Events::initialize()
 * (module discovery of Config/Events.php). A Registrar cannot register
 * listeners — it only merges config arrays.
 */

// E-mail the user when a successful sign-in looks unusual. Runs only when both
// AuthSecurity::$suspiciousLoginAlerts and $securityNotificationEmails are on
// (SecurityNotifier checks them); the send is swallow-and-log.
Events::on('suspicious-login', static function (User $user, array $flags = [], string $ipAddress = '', string $userAgent = ''): void {
    service('securityNotifier')->suspiciousLogin($user, $flags, $ipAddress, $userAgent);
});
