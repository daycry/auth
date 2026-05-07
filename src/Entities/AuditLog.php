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

namespace Daycry\Auth\Entities;

use CodeIgniter\Entity\Entity;
use CodeIgniter\I18n\Time;
use JsonException;

/**
 * Represents one row in the auth_audit_logs table.
 *
 * @property int|null    $actor_id   The user that triggered the event (admin or self). Null for system actions.
 * @property Time|null   $created_at
 * @property string      $event_type Short snake_case identifier (e.g. "totp.enabled", "password.changed").
 * @property int|null    $id
 * @property string|null $ip_address
 * @property string|null $metadata   JSON-encoded extra context.
 * @property string|null $user_agent
 * @property int|null    $user_id    The user the event affects.
 */
class AuditLog extends Entity
{
    protected $casts = [
        'id'       => '?integer',
        'user_id'  => '?integer',
        'actor_id' => '?integer',
    ];
    protected $dates = ['created_at'];

    /**
     * Decode metadata from JSON to array.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        $raw = $this->attributes['metadata'] ?? null;

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (JsonException) {
            return [];
        }
    }
}
