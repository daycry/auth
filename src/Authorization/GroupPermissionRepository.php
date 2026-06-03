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

namespace Daycry\Auth\Authorization;

use CodeIgniter\I18n\Time;
use Config\Database;
use Daycry\Auth\Models\GroupUserModel;
use Daycry\Auth\Models\PermissionUserModel;

/**
 * Owns the transactional persistence of a user's group/permission pivot rows.
 *
 * Extracted from the {@see \Daycry\Auth\Traits\Authorizable} trait so the User
 * entity no longer opens database transactions itself — that Entity → DB
 * layering inversion is what previously required a deptrac whitelist.
 */
class GroupPermissionRepository
{
    /**
     * Syncs a user's pivot rows (groups or permissions) so the persisted set
     * matches $cache: rows not in $cache are removed, and rows in $cache but
     * not yet persisted ($existing) are inserted — all in one transaction.
     *
     * @param         int|string                         $userId
     * @param         string                             $type     'group_id' | 'permission_id'
     * @param         GroupUserModel|PermissionUserModel $model
     * @param         list<int|string>                   $cache    Desired set of ids
     * @param         list<int|string>                   $existing Currently-persisted ids
     * @phpstan-param 'group_id'|'permission_id'         $type
     */
    public function saveUserPivot($userId, string $type, $model, array $cache, array $existing): void
    {
        $db  = Database::connect();
        $new = array_diff($cache, $existing);

        $db->transStart();

        // Delete any not in the cache.
        if ($cache !== []) {
            $model->deleteNotIn($userId, $cache);
        }
        // Nothing in the cache? Then delete all rows for this user.
        else {
            $model->deleteAll($userId);
        }

        // Insert new ones.
        if ($new !== []) {
            $inserts = [];

            foreach ($new as $item) {
                $inserts[] = [
                    'user_id'    => $userId,
                    $type        => $item,
                    'created_at' => Time::now()->format('Y-m-d H:i:s'),
                ];
            }

            $model->insertBatch($inserts);
        }

        $db->transComplete();
    }
}
