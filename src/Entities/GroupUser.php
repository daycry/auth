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

use Daycry\Auth\Traits\BelongsToGroup;
use Daycry\Auth\Traits\BelongsToUser;

/**
 * A user's membership of a group (`groups_users` row).
 */
class GroupUser extends PivotEntity
{
    use BelongsToGroup;
    use BelongsToUser;
}
