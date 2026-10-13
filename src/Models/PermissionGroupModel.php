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

use Daycry\Auth\Entities\Group;
use Daycry\Auth\Entities\PermissionGroup;

class PermissionGroupModel extends PivotModel
{
    protected $returnType    = PermissionGroup::class;
    protected $allowedFields = [
        'group_id',
        'permission_id',
        'until_at',
    ];
    protected string $tableKey      = 'permissions_groups';
    protected string $ownerColumn   = 'group_id';
    protected string $relatedColumn = 'permission_id';

    /**
     * Returns all active group permissions (permanent or not yet expired).
     *
     * @return list<PermissionGroup>|null
     */
    public function getForGroup(Group $group): ?array
    {
        return $this->findActiveFor($group->id);
    }

    /**
     * @param int|string $groupId
     */
    public function deleteAll($groupId): void
    {
        $this->deleteForOwner($groupId);
    }

    /**
     * @param int|string $groupId
     * @param mixed      $cache
     */
    public function deleteNotIn($groupId, $cache): void
    {
        $this->deleteForOwner($groupId, $cache);
    }
}
