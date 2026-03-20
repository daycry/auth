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

namespace Daycry\Auth\Authentication\Services;

use CodeIgniter\HTTP\IncomingRequest;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Models\DeviceSessionModel;

/**
 * Handles creation and termination of device session records.
 *
 * Extracted from Session authenticator to isolate device-tracking
 * concerns from the core authentication flow.
 */
class DeviceSessionRecorder
{
    /**
     * Creates a device session record for the given user.
     *
     * Silently returns if there is no active PHP session (e.g. in testing).
     */
    public function recordSession(User $user, string $ipAddress): void
    {
        $sessionId = session_id();

        // No active session (e.g. testing environment without a real session)
        if ($sessionId === '' || $sessionId === false) {
            return;
        }

        /** @var DeviceSessionModel $deviceSessionModel */
        $deviceSessionModel = model(DeviceSessionModel::class);

        /** @var IncomingRequest $incomingRequest */
        $incomingRequest = service('request');
        $userAgent       = (string) $incomingRequest->getUserAgent();

        $deviceSessionModel->createSession($user, $sessionId, $ipAddress, $userAgent !== '' ? $userAgent : null);
    }

    /**
     * Terminates the current device session.
     */
    public function terminateCurrentSession(): void
    {
        $sessionId = session_id();

        if ($sessionId === '' || $sessionId === false) {
            return;
        }

        /** @var DeviceSessionModel $deviceSessionModel */
        $deviceSessionModel = model(DeviceSessionModel::class);
        $deviceSessionModel->terminateSession($sessionId);
    }
}
