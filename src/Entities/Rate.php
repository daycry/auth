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

use CodeIgniter\Entity\Entity;

class Rate extends Entity
{
    protected $dates = [
        'hour_started_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];
    protected $casts = [
        'count' => 'integer',
    ];
}
