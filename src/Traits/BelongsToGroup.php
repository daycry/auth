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

use Daycry\Auth\Entities\Group;
use Daycry\Auth\Models\GroupModel;

/**
 * Lazy, memoized access to the group referenced by a pivot entity's
 * `group_id` attribute (GroupUser, PermissionGroup).
 */
trait BelongsToGroup
{
    private ?Group $group = null;

    /**
     * Returns the related group.
     *
     * @return Group|null
     */
    public function getGroup()
    {
        if ($this->group instanceof Group) {
            return $this->group;
        }

        $this->group = model(GroupModel::class)->where('id', $this->attributes['group_id'])->first();

        return $this->group;
    }
}
