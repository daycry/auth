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

use Daycry\Auth\Traits\BelongsToPermission;
use Daycry\Auth\Traits\BelongsToUser;

/**
 * A permission granted directly to a user (`permissions_users` row).
 */
class PermissionUser extends PivotEntity
{
    use BelongsToPermission;
    use BelongsToUser;
}
