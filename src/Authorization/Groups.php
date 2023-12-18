<?php

declare(strict_types=1);

namespace Daycry\Auth\Authorization;

use Daycry\Auth\Entities\Group;
use Daycry\Auth\Models\GroupModel;
use Daycry\Exceptions\Exceptions\RuntimeException;

/**
 * Provides utility feature for working with
 * groups, adding permissions, etc.
 */
class Groups
{
    /**
     * Grabs a group info from settings.
     */
    public function info(string $group): ?Group
    {
        return (new GroupModel())->find(['name' => $group])->first();
    }

    /**
     * Saves or creates the group.
     */
    public function save(Group $group): bool
    {
        return (new GroupModel())->save($group);
    }
}