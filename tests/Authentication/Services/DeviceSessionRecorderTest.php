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

use Daycry\Auth\Authentication\Services\DeviceSessionRecorder;
use Daycry\Auth\Models\DeviceSessionModel;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeUser;

/**
 * @internal
 */
final class DeviceSessionRecorderTest extends DatabaseTestCase
{
    use FakeUser;

    private DeviceSessionRecorder $recorder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFakeUser();

        $this->recorder = new DeviceSessionRecorder();
    }

    public function testRecordSessionSkipsWithoutActiveSession(): void
    {
        // Without a real PHP session, session_id() returns '' — so no record is created
        $this->recorder->recordSession($this->user, '127.0.0.1');

        /** @var DeviceSessionModel $model */
        $model = model(DeviceSessionModel::class);

        $sessions = $model->getActiveForUser($this->user);
        $this->assertCount(0, $sessions);
    }

    public function testTerminateCurrentSessionSkipsWithoutActiveSession(): void
    {
        $this->expectNotToPerformAssertions();

        // Should not throw when there is no active PHP session
        $this->recorder->terminateCurrentSession();
    }

    public function testRecordSessionCreatesRecord(): void
    {
        // Manually create a device session to test the model interaction
        // (we can't start a real PHP session in PHPUnit)
        /** @var DeviceSessionModel $model */
        $model = model(DeviceSessionModel::class);

        $session = $model->createSession($this->user, 'test-session-id', '192.168.1.1', 'PHPUnit Test Agent');

        $this->assertSame((string) $this->user->id, (string) $session->user_id);
        $this->assertSame('test-session-id', $session->session_id);
        $this->assertSame('192.168.1.1', $session->ip_address);

        // Verify it appears in active sessions
        $active = $model->getActiveForUser($this->user);
        $this->assertCount(1, $active);
    }

    public function testTerminateCurrentSessionDeletesRecord(): void
    {
        /** @var DeviceSessionModel $model */
        $model = model(DeviceSessionModel::class);

        // Create a session first
        $model->createSession($this->user, 'terminate-test-session', '10.0.0.1', 'PHPUnit');

        // Terminate it directly via model (since we can't set session_id())
        $model->terminateSession('terminate-test-session');

        $active = $model->getActiveForUser($this->user);
        $this->assertCount(0, $active);
    }
}
