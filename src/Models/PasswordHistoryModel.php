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
use Daycry\Auth\Entities\User;

/**
 * Stores hashes of recently used passwords to prevent reuse.
 *
 * Only the last N hashes (configured via `AuthSecurity::$passwordHistorySize`)
 * are retained per user — older entries are pruned on each insert.
 */
class PasswordHistoryModel extends BaseModel
{
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'user_id',
        'password_hash',
        'created_at',
    ];
    protected $useTimestamps = false;

    protected function initialize(): void
    {
        parent::initialize();

        $this->table = $this->tables['password_history'] ?? 'auth_password_history';
    }

    /**
     * Records a previously-used password hash for this user. Trims older
     * entries beyond the configured retention window.
     */
    public function recordHash(User $user, string $passwordHash, int $retain): void
    {
        if ($retain <= 0 || $passwordHash === '') {
            return;
        }

        $this->insert([
            'user_id'       => (int) $user->id,
            'password_hash' => $passwordHash,
            'created_at'    => Time::now()->toDateTimeString(),
        ]);

        $this->pruneToRetention($user, $retain);
    }

    /**
     * Returns true when the supplied plaintext password matches one of the
     * user's recent password hashes.
     */
    public function matchesRecent(User $user, string $plainPassword, int $retain): bool
    {
        if ($retain <= 0 || $plainPassword === '') {
            return false;
        }

        $rows = $this->where('user_id', $user->id)
            ->orderBy('id', 'DESC')
            ->limit($retain)
            ->find();

        foreach ($rows as $row) {
            $hash = (string) ($row['password_hash'] ?? '');

            if ($hash !== '' && password_verify($plainPassword, $hash)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Removes all history for a user (e.g. on user deletion / anonymization).
     */
    public function purgeForUser(User $user): void
    {
        $this->where('user_id', $user->id)->delete();
    }

    /**
     * Keeps only the most recent $retain entries for the user.
     */
    private function pruneToRetention(User $user, int $retain): void
    {
        $keep = $this->where('user_id', $user->id)
            ->orderBy('id', 'DESC')
            ->limit($retain)
            ->select('id')
            ->find();

        if ($keep === []) {
            return;
        }

        $keepIds = [];

        foreach ($keep as $row) {
            $keepIds[] = (int) ($row['id'] ?? 0);
        }

        $this->where('user_id', $user->id)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }
}
