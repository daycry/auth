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

namespace Daycry\Auth\Interfaces;

/**
 * Application contract for replacing AuditLogger without inheritance.
 */
interface AuditLoggerInterface
{
    /**
     * Records a single audit event.
     *
     * @param string               $eventType One of the EVENT_* constants (or a custom snake_case identifier).
     * @param int|null             $userId    Affected user id.
     * @param array<string, mixed> $metadata  Extra context (will be JSON-encoded).
     * @param int|null             $actorId   User id that triggered the event when different from $userId
     *                                        (admin actions). Defaults to $userId.
     */
    public function record(string $eventType, ?int $userId, array $metadata = [], ?int $actorId = null): void;
}
