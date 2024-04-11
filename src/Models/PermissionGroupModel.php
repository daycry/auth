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
use Daycry\Auth\Entities\Group;
use Daycry\Auth\Entities\PermissionGroup;

class PermissionGroupModel extends BaseModel
{
    protected $primaryKey     = 'id';
    protected $returnType     = PermissionGroup::class;
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'group_id',
        'permission_id',
        'until_at',
    ];
    protected $useTimestamps      = false;
    protected $validationRules    = [];
    protected $validationMessages = [];
    protected $skipValidation     = false;

    protected function initialize(): void
    {
        parent::initialize();

        $this->table = $this->tables['permissions_groups'];
    }

    /**
     * Returns all permissions groups.
     *
     * @return list<UserGroup>
     */
    public function getForGroup(Group $group): ?array
    {
        return $this->where('group_id', $group->id)
            ->where('until_at', null)
            ->orWhere('until_at >', Time::now()->format('Y-m-d H:i:s'))
            ->orderBy($this->primaryKey)->findAll();
    }

    /**
     * @param int|string $groupId
     */
    public function deleteAll($groupId): void
    {
        $return = $this->builder()
            ->where('group_id', $groupId)
            ->delete();

        $this->checkQueryReturn($return);
    }

    /**
     * @param int|string $groupId
     * @param mixed      $cache
     */
    public function deleteNotIn($groupId, $cache): void
    {
        $return = $this->builder()
            ->where('group_id', $groupId)
            ->whereNotIn('permission_id', $cache)
            ->delete();

        $this->checkQueryReturn($return);
    }
}
