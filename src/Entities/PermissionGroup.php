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

use Daycry\Auth\Models\GroupModel;
use Daycry\Auth\Models\PermissionModel;

class PermissionGroup extends Entity
{
    /**
     * User $user
     */
    private ?Group $group = null;

    /**
     * Group $group
     */
    private ?Permission $permission = null;

    /**
     * @var string[]
     * @phpstan-var list<string>
     * @psalm-var list<string>
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'until_at',
    ];

    /**
     * Get User
     */
    public function getGroup()
    {
        if ($this->group) {
            return $this->group;
        }

        $groupModel  = model(GroupModel::class);
        $this->group = $groupModel->where('id', $this->attributes['group_id'])->first();

        return $this->group;
    }

    /**
     * Get Permission
     */
    public function getPermission()
    {
        if ($this->permission) {
            return $this->permission;
        }

        $groupModel       = model(PermissionModel::class);
        $this->permission = $groupModel->where('id', $this->attributes['permission_id'])->first();

        return $this->permission;
    }
}
