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

use Daycry\Auth\Entities\GroupUser;
use Daycry\Auth\Entities\User;

class GroupUserModel extends PivotModel
{
    protected $returnType    = GroupUser::class;
    protected $allowedFields = [
        'user_id',
        'group_id',
        'until_at',
    ];
    protected string $tableKey      = 'groups_users';
    protected string $ownerColumn   = 'user_id';
    protected string $relatedColumn = 'group_id';

    /**
     * Returns all active user groups (permanent or not yet expired).
     *
     * @return list<GroupUser>
     */
    public function getForUser(User $user): array
    {
        return $this->findActiveFor($user->id);
    }

    /**
     * @param int|string $userId
     */
    public function deleteAll($userId): void
    {
        $this->deleteForOwner($userId);
    }

    /**
     * @param int|string $userId
     * @param mixed      $cache
     */
    public function deleteNotIn($userId, $cache): void
    {
        $this->deleteForOwner($userId, $cache);
    }
}
