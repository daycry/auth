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

use Daycry\Auth\Entities\Rate;

class RateModel extends BaseModel
{
    protected $primaryKey     = 'id';
    protected $returnType     = Rate::class;
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'user_id',
        'uri',
        'count',
        'hour_started_at',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected function initialize(): void
    {
        parent::initialize();

        $this->table = $this->tables['rates'];
    }
}
