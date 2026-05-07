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
 * Stores one-time backup codes that authenticate a user when their
 * TOTP authenticator is unavailable.
 *
 * Codes are SHA-256 hashed at rest; raw codes are shown to the user
 * exactly once during enrollment.
 */
class TotpBackupCodeModel extends BaseModel
{
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'user_id',
        'code_hash',
        'used_at',
        'created_at',
    ];
    protected $useTimestamps = false;

    protected function initialize(): void
    {
        parent::initialize();

        $this->table = $this->tables['totp_backup_codes'] ?? 'auth_totp_backup_codes';
    }

    /**
     * Replaces this user's backup codes with a fresh set and returns the
     * plain-text codes (only shown to the user this once).
     *
     * @return list<string>
     */
    public function regenerateForUser(User $user, int $count = 10, int $bytes = 5): array
    {
        $this->where('user_id', $user->id)->delete();

        $now   = Time::now()->toDateTimeString();
        $codes = [];
        $rows  = [];

        while (count($codes) < $count) {
            $candidate = strtolower(bin2hex(random_bytes($bytes)));

            if (in_array($candidate, $codes, true)) {
                continue; // collision — extremely unlikely but cheap to handle
            }

            $codes[] = $candidate;
            $rows[]  = [
                'user_id'    => (int) $user->id,
                'code_hash'  => hash('sha256', $candidate),
                'used_at'    => null,
                'created_at' => $now,
            ];
        }

        $this->insertBatch($rows);

        return $codes;
    }

    /**
     * Returns true and marks the row as used when the supplied plain-text
     * code matches an unused backup code for the user.
     */
    public function consume(User $user, string $rawCode): bool
    {
        $hash = hash('sha256', strtolower(trim($rawCode)));

        // Atomic: only succeeds when there is an unused matching row, and
        // marks it as used in the same statement (no SELECT-then-UPDATE race).
        $this->where('user_id', $user->id)
            ->where('code_hash', $hash)
            ->where('used_at', null)
            ->set('used_at', Time::now()->toDateTimeString())
            ->update();

        return $this->db->affectedRows() > 0;
    }

    /**
     * Counts the user's remaining (unused) backup codes.
     */
    public function remainingCount(User $user): int
    {
        return $this->where('user_id', $user->id)
            ->where('used_at', null)
            ->countAllResults();
    }

    /**
     * Removes all backup codes for the user (e.g. when TOTP is disabled).
     */
    public function purgeForUser(User $user): void
    {
        $this->where('user_id', $user->id)->delete();
    }
}
