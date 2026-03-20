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

use CodeIgniter\I18n\Time;
use Daycry\Auth\Authentication\Services\UserLockoutManager;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeUser;

/**
 * @internal
 */
final class UserLockoutManagerTest extends DatabaseTestCase
{
    use FakeUser;

    private UserLockoutManager $lockoutManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFakeUser();

        /** @var UserModel $provider */
        $provider             = model(UserModel::class);
        $this->lockoutManager = new UserLockoutManager($provider);
    }

    public function testIsLockedOutReturnsNullWhenDisabled(): void
    {
        $this->inkectMockAttributesSecurity(['userMaxAttempts' => 0]);

        $result = $this->lockoutManager->isLockedOut($this->user);

        $this->assertNull($result);
    }

    public function testIsLockedOutReturnsNullWhenNotLocked(): void
    {
        $this->inkectMockAttributesSecurity(['userMaxAttempts' => 5]);

        $result = $this->lockoutManager->isLockedOut($this->user);

        $this->assertNull($result);
    }

    public function testIsLockedOutReturnsResultWhenLocked(): void
    {
        $this->inkectMockAttributesSecurity(['userMaxAttempts' => 5]);

        // Lock the user until 1 hour from now
        $lockedUntil = Time::now()->addHours(1)->format('Y-m-d H:i:s');
        model(UserModel::class)->update($this->user->id, [
            'failed_login_count' => 5,
            'locked_until'       => $lockedUntil,
        ]);

        // Refresh user entity
        $this->user = model(UserModel::class)->find($this->user->id);

        $result = $this->lockoutManager->isLockedOut($this->user);

        $this->assertNotNull($result);
        $this->assertFalse($result->isOK());
    }

    public function testIsLockedOutResetsExpiredLockout(): void
    {
        $this->inkectMockAttributesSecurity(['userMaxAttempts' => 5]);

        // Lock the user until 1 hour ago (expired)
        $lockedUntil = Time::now()->subHours(1)->format('Y-m-d H:i:s');
        model(UserModel::class)->update($this->user->id, [
            'failed_login_count' => 5,
            'locked_until'       => $lockedUntil,
        ]);

        // Refresh user entity
        $this->user = model(UserModel::class)->find($this->user->id);

        $result = $this->lockoutManager->isLockedOut($this->user);

        $this->assertNull($result);

        // Verify counter was reset in DB
        $freshUser = model(UserModel::class)->find($this->user->id);
        $this->assertSame(0, (int) $freshUser->failed_login_count);
        $this->assertNull($freshUser->locked_until);
    }

    public function testRecordFailedAttemptIncrementsCounter(): void
    {
        $this->inkectMockAttributesSecurity(['userMaxAttempts' => 5]);

        $this->lockoutManager->recordFailedAttempt($this->user);

        $freshUser = model(UserModel::class)->find($this->user->id);
        $this->assertSame(1, (int) $freshUser->failed_login_count);
        $this->assertNull($freshUser->locked_until);
    }

    public function testRecordFailedAttemptLocksAtThreshold(): void
    {
        $this->inkectMockAttributesSecurity([
            'userMaxAttempts' => 3,
            'userLockoutTime' => 600,
        ]);

        // Set counter just below threshold
        model(UserModel::class)->update($this->user->id, [
            'failed_login_count' => 2,
        ]);
        $this->user = model(UserModel::class)->find($this->user->id);

        $this->lockoutManager->recordFailedAttempt($this->user);

        $freshUser = model(UserModel::class)->find($this->user->id);
        $this->assertSame(3, (int) $freshUser->failed_login_count);
        $this->assertNotNull($freshUser->locked_until);
    }

    public function testRecordFailedAttemptNoOpWhenDisabled(): void
    {
        $this->inkectMockAttributesSecurity(['userMaxAttempts' => 0]);

        $this->lockoutManager->recordFailedAttempt($this->user);

        $freshUser = model(UserModel::class)->find($this->user->id);
        $this->assertSame(0, (int) $freshUser->failed_login_count);
    }

    public function testResetOnSuccessClearsCounter(): void
    {
        $this->inkectMockAttributesSecurity(['userMaxAttempts' => 5]);

        // Set a non-zero counter
        model(UserModel::class)->update($this->user->id, [
            'failed_login_count' => 3,
        ]);
        $this->user = model(UserModel::class)->find($this->user->id);

        $this->lockoutManager->resetOnSuccess($this->user);

        $freshUser = model(UserModel::class)->find($this->user->id);
        $this->assertSame(0, (int) $freshUser->failed_login_count);
        $this->assertNull($freshUser->locked_until);
    }

    public function testResetOnSuccessNoOpWhenAlreadyZero(): void
    {
        $this->inkectMockAttributesSecurity(['userMaxAttempts' => 5]);

        // User already has 0 failed attempts — resetOnSuccess should not call update
        $this->lockoutManager->resetOnSuccess($this->user);

        $freshUser = model(UserModel::class)->find($this->user->id);
        $this->assertSame(0, (int) $freshUser->failed_login_count);
    }
}
