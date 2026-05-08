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

namespace Tests\Authentication\Services;

use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Authentication\Services\SuspiciousLoginDetector;
use Daycry\Auth\Models\DeviceSessionModel;
use Daycry\Auth\Models\LoginModel;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class SuspiciousLoginDetectorTest extends DatabaseTestCase
{
    private SuspiciousLoginDetector $detector;
    private LoginModel $logins;
    private DeviceSessionModel $devices;

    protected function setUp(): void
    {
        parent::setUp();

        $this->detector = new SuspiciousLoginDetector();
        $this->logins   = model(LoginModel::class);
        $this->devices  = model(DeviceSessionModel::class);
    }

    public function testFreshUserHasNoHistorySoEverythingIsNew(): void
    {
        $user = fake(UserModel::class);

        $flags = $this->detector->analyse($user, '203.0.113.1', 'Mozilla/5.0');

        $this->assertContains(SuspiciousLoginDetector::FLAG_NEW_IP, $flags);
        $this->assertContains(SuspiciousLoginDetector::FLAG_NEW_DEVICE, $flags);
    }

    public function testNoFlagsWhenIpAndDeviceMatchHistory(): void
    {
        $user = fake(UserModel::class);
        $ip   = '198.51.100.7';
        $ua   = 'Mozilla/5.0 (X11; Linux x86_64)';

        // Seed a successful login from this IP and a device session with
        // the matching UA.
        $this->logins->recordLoginAttempt(
            Session::ID_TYPE_EMAIL_PASSWORD,
            $user->getEmail() ?? 'user@example.com',
            true,
            $ip,
            $ua,
            (int) $user->id,
        );

        $this->devices->createSession($user, 'session-id-A', $ip, $ua);

        $flags = $this->detector->analyse($user, $ip, $ua);

        $this->assertSame([], $flags);
    }

    public function testNewIpFlagWhenDeviceMatchesButIpDoesNot(): void
    {
        $user = fake(UserModel::class);
        $ua   = 'Mozilla/5.0 (Macintosh)';

        $this->devices->createSession($user, 'session-id-B', '198.51.100.10', $ua);
        // No login row from the new IP yet.

        $flags = $this->detector->analyse($user, '203.0.113.42', $ua);

        $this->assertContains(SuspiciousLoginDetector::FLAG_NEW_IP, $flags);
        $this->assertNotContains(SuspiciousLoginDetector::FLAG_NEW_DEVICE, $flags);
    }

    public function testEmptyIpAndUaResultInNoFlags(): void
    {
        $user = fake(UserModel::class);

        $flags = $this->detector->analyse($user, '', null);

        $this->assertSame([], $flags);
    }

    public function testIsNewIpReturnsTrueForUnseenIp(): void
    {
        $user = fake(UserModel::class);

        $this->assertTrue($this->detector->isNewIp($user, '192.0.2.1'));
    }

    public function testIsNewIpReturnsFalseAfterSuccessfulLogin(): void
    {
        $user = fake(UserModel::class);
        $ip   = '192.0.2.50';

        $this->logins->recordLoginAttempt(
            Session::ID_TYPE_EMAIL_PASSWORD,
            $user->getEmail() ?? 'user@example.com',
            true,
            $ip,
            'browser',
            (int) $user->id,
        );

        $this->assertFalse($this->detector->isNewIp($user, $ip));
    }

    public function testIsNewIpIgnoresFailedLogins(): void
    {
        $user = fake(UserModel::class);
        $ip   = '192.0.2.99';

        // Failed login from this IP — should NOT count as familiar.
        $this->logins->recordLoginAttempt(
            Session::ID_TYPE_EMAIL_PASSWORD,
            'wrong@example.com',
            false,
            $ip,
            'attacker-ua',
            (int) $user->id,
        );

        $this->assertTrue($this->detector->isNewIp($user, $ip));
    }

    public function testIsNewDeviceReturnsTrueForUnseenUserAgent(): void
    {
        $user = fake(UserModel::class);

        $this->assertTrue($this->detector->isNewDevice($user, 'curl/8.0'));
    }

    public function testIsNewDeviceReturnsFalseWhenSessionExists(): void
    {
        $user = fake(UserModel::class);
        $ua   = 'Mobile-App/1.0';

        $this->devices->createSession($user, 'session-D', '203.0.113.1', $ua);

        $this->assertFalse($this->detector->isNewDevice($user, $ua));
    }
}
