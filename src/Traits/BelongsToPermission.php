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

namespace Daycry\Auth\Traits;

use Daycry\Auth\Entities\Permission;
use Daycry\Auth\Models\PermissionModel;

/**
 * Lazy, memoized access to the permission referenced by a pivot entity's
 * `permission_id` attribute (PermissionUser, PermissionGroup).
 */
trait BelongsToPermission
{
    private ?Permission $permission = null;

    /**
     * Returns the related permission.
     *
     * @return Permission|null
     */
    public function getPermission()
    {
        if ($this->permission instanceof Permission) {
            return $this->permission;
        }

        $this->permission = model(PermissionModel::class)->where('id', $this->attributes['permission_id'])->first();

        return $this->permission;
    }
}
