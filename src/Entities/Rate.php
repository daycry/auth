<?php

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
        'count'           => 'integer'
    ];
}
