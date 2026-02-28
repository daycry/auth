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
use Daycry\Auth\Entities\DeviceSession;
use Daycry\Auth\Entities\User;
use Symfony\Component\Uid\Uuid;

class DeviceSessionModel extends BaseModel
{
    protected $primaryKey     = 'id';
    protected $returnType     = DeviceSession::class;
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'uuid',
        'user_id',
        'session_id',
        'device_name',
        'ip_address',
        'user_agent',
        'last_active',
        'logged_out_at',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $beforeInsert  = ['generateUuid'];

    protected function initialize(): void
    {
        parent::initialize();

        $this->table = $this->tables['device_sessions'];
    }

    /**
     * Generates a UUID v7 for new device session records.
     *
     * Model event callback called by `beforeInsert`.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function generateUuid(array $data): array
    {
        if (empty($data['data']['uuid'])) {
            $data['data']['uuid'] = Uuid::v7()->toRfc4122();
        }

        return $data;
    }

    /**
     * Returns all active (not logged out) device sessions for the given user.
     *
     * @return list<DeviceSession>
     */
    public function getActiveForUser(User $user): array
    {
        return $this->where('user_id', $user->id)
            ->where('logged_out_at', null)
            ->orderBy('last_active', 'DESC')
            ->findAll();
    }

    /**
     * Returns all device sessions (active and terminated) for the given user.
     *
     * @return list<DeviceSession>
     */
    public function getAllForUser(User $user): array
    {
        return $this->where('user_id', $user->id)
            ->orderBy('last_active', 'DESC')
            ->findAll();
    }

    /**
     * Finds a device session by its PHP session ID.
     */
    public function findBySessionId(string $sessionId): ?DeviceSession
    {
        return $this->where('session_id', $sessionId)
            ->first();
    }

    /**
     * Creates a new device session record.
     */
    public function createSession(User $user, string $sessionId, string $ipAddress, ?string $userAgent = null): DeviceSession
    {
        $deviceName = DeviceSession::parseUserAgent($userAgent ?? '');

        $data = [
            'user_id'     => $user->id,
            'session_id'  => $sessionId,
            'device_name' => $deviceName,
            'ip_address'  => $ipAddress,
            'user_agent'  => $userAgent,
            'last_active' => Time::now()->format('Y-m-d H:i:s'),
        ];

        $id = $this->insert($data, true);

        /** @var DeviceSession|null $result */
        return $this->find($id);
    }

    /**
     * Updates the last_active timestamp for the given session.
     */
    public function touchSession(string $sessionId): void
    {
        $this->where('session_id', $sessionId)
            ->where('logged_out_at', null)
            ->set('last_active', Time::now()->format('Y-m-d H:i:s'))
            ->update();
    }

    /**
     * Marks the session as terminated by setting logged_out_at.
     */
    public function terminateSession(string $sessionId): void
    {
        $this->where('session_id', $sessionId)
            ->where('logged_out_at', null)
            ->set('logged_out_at', Time::now()->format('Y-m-d H:i:s'))
            ->update();
    }

    /**
     * Terminates all active sessions for a user, optionally keeping one session alive.
     *
     * @param string|null $exceptSessionId Session ID to keep active (e.g. current session)
     */
    public function terminateAllForUser(User $user, ?string $exceptSessionId = null): void
    {
        $builder = $this->where('user_id', $user->id)
            ->where('logged_out_at', null);

        if ($exceptSessionId !== null && $exceptSessionId !== '') {
            $builder = $builder->where('session_id !=', $exceptSessionId);
        }

        $builder->set('logged_out_at', Time::now()->format('Y-m-d H:i:s'))
            ->update();
    }

    /**
     * Removes old terminated sessions older than the given number of days.
     */
    public function purgeOldSessions(int $days = 30): void
    {
        $cutoff = Time::now()->subDays($days)->format('Y-m-d H:i:s');

        $this->where('logged_out_at <', $cutoff)
            ->delete();
    }
}
