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

namespace Daycry\Auth\Models;

use CodeIgniter\I18n\Time;
use Daycry\Auth\Entities\AuditLog;

/**
 * Stores granular security/account events distinct from {@see LogModel}
 * (request-level activity) and {@see LoginModel} (login attempts).
 *
 * Use via the {@see \Daycry\Auth\Services\AuditLogger} service rather than
 * inserting rows directly — the service handles JSON encoding, IP/UA
 * resolution, and timestamp formatting.
 */
class AuditLogModel extends BaseModel
{
    protected $primaryKey     = 'id';
    protected $returnType     = AuditLog::class;
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'user_id',
        'actor_id',
        'event_type',
        'ip_address',
        'user_agent',
        'metadata',
        'created_at',
    ];
    protected $useTimestamps = false; // We set created_at explicitly.

    protected function initialize(): void
    {
        parent::initialize();

        $this->table = $this->tables['audit_logs'] ?? 'auth_audit_logs';
    }

    /**
     * Returns the most recent events for a user (newest first).
     *
     * @return list<AuditLog>
     */
    public function recentForUser(int $userId, int $limit = 50): array
    {
        return $this
            ->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->find();
    }

    /**
     * Returns events filtered by type and time window.
     *
     * @return list<AuditLog>
     */
    public function recentByType(string $eventType, ?Time $since = null, int $limit = 100): array
    {
        $builder = $this
            ->where('event_type', $eventType)
            ->orderBy('created_at', 'DESC')
            ->limit($limit);

        if ($since !== null) {
            $builder = $builder->where('created_at >=', $since->toDateTimeString());
        }

        return $builder->find();
    }
}
