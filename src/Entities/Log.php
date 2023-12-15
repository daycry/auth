<?php

namespace Daycry\Auth\Entities;

class Log extends Entity
{
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'authorized' => 'int_bool'
    ];
}
