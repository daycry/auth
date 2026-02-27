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

namespace Tests\Authentication;

use CodeIgniter\I18n\Time;
use Daycry\Auth\Entities\DeviceSession;
use Daycry\Auth\Models\DeviceSessionModel;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeUser;

/**
 * @internal
 */
final class DeviceSessionTest extends DatabaseTestCase
{
    use FakeUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFakeUser();
    }

    // -----------------------------------------------------------------------
    // DeviceSession entity tests
    // -----------------------------------------------------------------------

    public function testEntityIsActiveWhenLoggedOutAtIsNull(): void
    {
        $session = new DeviceSession([
            'id'            => 1,
            'user_id'       => $this->user->id,
            'session_id'    => 'abc123',
            'ip_address'    => '127.0.0.1',
            'logged_out_at' => null,
        ]);

        $this->assertTrue($session->isActive());
    }

    public function testEntityIsInactiveWhenLoggedOutAtIsSet(): void
    {
        $session = new DeviceSession([
            'id'            => 1,
            'user_id'       => $this->user->id,
            'session_id'    => 'abc123',
            'ip_address'    => '127.0.0.1',
            'logged_out_at' => Time::now()->format('Y-m-d H:i:s'),
        ]);

        $this->assertFalse($session->isActive());
    }

    public function testParseUserAgentChrome(): void
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

        $this->assertSame('Chrome on Windows', DeviceSession::parseUserAgent($ua));
    }

    public function testParseUserAgentFirefoxLinux(): void
    {
        $ua = 'Mozilla/5.0 (X11; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/115.0';

        $this->assertSame('Firefox on Linux', DeviceSession::parseUserAgent($ua));
    }

    public function testParseUserAgentSafariMac(): void
    {
        $ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_2_1) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15';

        $this->assertSame('Safari on macOS', DeviceSession::parseUserAgent($ua));
    }

    public function testParseUserAgentEdge(): void
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0';

        $this->assertSame('Microsoft Edge on Windows', DeviceSession::parseUserAgent($ua));
    }

    public function testParseUserAgentEmpty(): void
    {
        $this->assertSame('Unknown Device', DeviceSession::parseUserAgent(''));
    }

    public function testGetDeviceLabelFallsBackToParsedUserAgent(): void
    {
        $ua      = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36';
        $session = new DeviceSession([
            'user_agent'  => $ua,
            'device_name' => null,
        ]);

        $this->assertSame('Chrome on Windows', $session->getDeviceLabel());
    }

    public function testGetDeviceLabelUsesStoredDeviceName(): void
    {
        $session = new DeviceSession([
            'user_agent'  => 'SomeRawUA',
            'device_name' => 'My Custom Device',
        ]);

        $this->assertSame('My Custom Device', $session->getDeviceLabel());
    }

    // -----------------------------------------------------------------------
    // DeviceSessionModel tests
    // -----------------------------------------------------------------------

    public function testCreateSession(): void
    {
        /** @var DeviceSessionModel $model */
        $model = model(DeviceSessionModel::class);

        $session = $model->createSession(
            $this->user,
            'session_abc',
            '192.168.1.1',
            'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0.0.0',
        );

        $this->assertInstanceOf(DeviceSession::class, $session);
        $this->assertSame($this->user->id, $session->user_id);
        $this->assertSame('session_abc', $session->session_id);
        $this->assertSame('192.168.1.1', $session->ip_address);
        $this->assertTrue($session->isActive());
    }

    public function testFindBySessionId(): void
    {
        /** @var DeviceSessionModel $model */
        $model = model(DeviceSessionModel::class);

        $model->createSession($this->user, 'find_me', '10.0.0.1');

        $found = $model->findBySessionId('find_me');
        $this->assertInstanceOf(DeviceSession::class, $found);
        $this->assertSame('find_me', $found->session_id);

        $notFound = $model->findBySessionId('nonexistent');
        $this->assertNull($notFound);
    }

    public function testGetActiveForUser(): void
    {
        /** @var DeviceSessionModel $model */
        $model = model(DeviceSessionModel::class);

        $model->createSession($this->user, 'active_1', '10.0.0.1');
        $model->createSession($this->user, 'active_2', '10.0.0.2');

        $sessions = $model->getActiveForUser($this->user);
        $this->assertCount(2, $sessions);

        foreach ($sessions as $session) {
            $this->assertTrue($session->isActive());
        }
    }

    public function testTerminateSessionMarksSingleSession(): void
    {
        /** @var DeviceSessionModel $model */
        $model = model(DeviceSessionModel::class);

        $model->createSession($this->user, 'sess_terminate', '127.0.0.1');

        $model->terminateSession('sess_terminate');

        $found = $model->findBySessionId('sess_terminate');
        $this->assertNotNull($found);
        $this->assertFalse($found->isActive());
    }

    public function testGetActiveForUserExcludesTerminatedSessions(): void
    {
        /** @var DeviceSessionModel $model */
        $model = model(DeviceSessionModel::class);

        $model->createSession($this->user, 'keep_active', '10.0.0.1');
        $model->createSession($this->user, 'to_terminate', '10.0.0.2');
        $model->terminateSession('to_terminate');

        $active = $model->getActiveForUser($this->user);
        $this->assertCount(1, $active);
        $this->assertSame('keep_active', $active[0]->session_id);
    }

    public function testTerminateAllForUser(): void
    {
        /** @var DeviceSessionModel $model */
        $model = model(DeviceSessionModel::class);

        $model->createSession($this->user, 'sess_a', '10.0.0.1');
        $model->createSession($this->user, 'sess_b', '10.0.0.2');
        $model->createSession($this->user, 'sess_c', '10.0.0.3');

        $model->terminateAllForUser($this->user);

        $active = $model->getActiveForUser($this->user);
        $this->assertCount(0, $active);
    }

    public function testTerminateAllForUserExceptCurrent(): void
    {
        /** @var DeviceSessionModel $model */
        $model = model(DeviceSessionModel::class);

        $model->createSession($this->user, 'current', '10.0.0.1');
        $model->createSession($this->user, 'other_1', '10.0.0.2');
        $model->createSession($this->user, 'other_2', '10.0.0.3');

        $model->terminateAllForUser($this->user, 'current');

        $active = $model->getActiveForUser($this->user);
        $this->assertCount(1, $active);
        $this->assertSame('current', $active[0]->session_id);
    }

    public function testTouchSessionUpdatesLastActive(): void
    {
        /** @var DeviceSessionModel $model */
        $model = model(DeviceSessionModel::class);

        $model->createSession($this->user, 'touch_me', '127.0.0.1');

        // Simulate time passing
        $past = Time::now()->subMinutes(5)->format('Y-m-d H:i:s');
        $model->where('session_id', 'touch_me')->set('last_active', $past)->update();

        $model->touchSession('touch_me');

        $found = $model->findBySessionId('touch_me');
        $this->assertNotNull($found);
        // last_active should now be more recent than $past
        $this->assertGreaterThanOrEqual($past, $found->last_active->format('Y-m-d H:i:s'));
    }

    public function testPurgeOldSessions(): void
    {
        /** @var DeviceSessionModel $model */
        $model = model(DeviceSessionModel::class);

        $model->createSession($this->user, 'old_sess', '10.0.0.1');

        // Manually set logged_out_at to 60 days ago
        $oldDate = Time::now()->subDays(60)->format('Y-m-d H:i:s');
        $model->where('session_id', 'old_sess')
            ->set('logged_out_at', $oldDate)
            ->update();

        // Also create a recent terminated session (5 days ago)
        $model->createSession($this->user, 'recent_terminated', '10.0.0.2');
        $recentDate = Time::now()->subDays(5)->format('Y-m-d H:i:s');
        $model->where('session_id', 'recent_terminated')
            ->set('logged_out_at', $recentDate)
            ->update();

        $model->purgeOldSessions(30);

        $this->assertNull($model->findBySessionId('old_sess'));
        $this->assertNotNull($model->findBySessionId('recent_terminated'));
    }

    // -----------------------------------------------------------------------
    // HasDeviceSessions trait tests (via User entity)
    // -----------------------------------------------------------------------

    public function testUserGetDeviceSessions(): void
    {
        /** @var DeviceSessionModel $model */
        $model = model(DeviceSessionModel::class);

        $model->createSession($this->user, 'user_sess_1', '10.0.0.1');
        $model->createSession($this->user, 'user_sess_2', '10.0.0.2');

        $sessions = $this->user->getDeviceSessions();
        $this->assertCount(2, $sessions);
    }

    public function testUserTerminateDeviceSession(): void
    {
        /** @var DeviceSessionModel $model */
        $model = model(DeviceSessionModel::class);

        $model->createSession($this->user, 'kill_me', '10.0.0.1');

        $this->user->terminateDeviceSession('kill_me');

        $this->assertCount(0, $this->user->getDeviceSessions());
    }

    public function testUserTerminateAllDeviceSessions(): void
    {
        /** @var DeviceSessionModel $model */
        $model = model(DeviceSessionModel::class);

        $model->createSession($this->user, 'sess_x', '10.0.0.1');
        $model->createSession($this->user, 'sess_y', '10.0.0.2');
        $model->createSession($this->user, 'sess_z', '10.0.0.3');

        $this->user->terminateAllDeviceSessions();

        $this->assertCount(0, $this->user->getDeviceSessions());
    }

    public function testUserTerminateAllDeviceSessionsExceptCurrent(): void
    {
        /** @var DeviceSessionModel $model */
        $model = model(DeviceSessionModel::class);

        $model->createSession($this->user, 'keep', '10.0.0.1');
        $model->createSession($this->user, 'remove_a', '10.0.0.2');
        $model->createSession($this->user, 'remove_b', '10.0.0.3');

        $this->user->terminateAllDeviceSessions('keep');

        $remaining = $this->user->getDeviceSessions();
        $this->assertCount(1, $remaining);
        $this->assertSame('keep', $remaining[0]->session_id);
    }
}
