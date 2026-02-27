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

namespace Daycry\Auth\Traits;

use Daycry\Auth\Entities\DeviceSession;
use Daycry\Auth\Models\DeviceSessionModel;

/**
 * Trait HasDeviceSessions
 *
 * Provides methods for managing authenticated device sessions.
 * Intended to be used with User entities.
 */
trait HasDeviceSessions
{
    /**
     * Returns the DeviceSessionModel instance (shared service from the CI4 container).
     */
    private function deviceSessionModel(): DeviceSessionModel
    {
        /** @var DeviceSessionModel */
        return model(DeviceSessionModel::class);
    }

    /**
     * Returns all active device sessions for this user.
     *
     * @return list<DeviceSession>
     */
    public function getDeviceSessions(): array
    {
        return $this->deviceSessionModel()->getActiveForUser($this);
    }

    /**
     * Terminates a specific device session by its session ID.
     */
    public function terminateDeviceSession(string $sessionId): void
    {
        $this->deviceSessionModel()->terminateSession($sessionId);
    }

    /**
     * Terminates all active device sessions for this user,
     * optionally keeping one session (e.g. the current one) active.
     *
     * @param string|null $exceptCurrentSession Session ID to keep active
     */
    public function terminateAllDeviceSessions(?string $exceptCurrentSession = null): void
    {
        $this->deviceSessionModel()->terminateAllForUser($this, $exceptCurrentSession);
    }
}
