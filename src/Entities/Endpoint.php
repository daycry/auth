<?php

namespace Daycry\Auth\Entities;

class Endpoint extends Entity
{
    /**
     * @var string[]
     * @phpstan-var list<string>
     * @psalm-var list<string>
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'checked_at'
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'id'           => '?integer',
        'access_token' => '?int_bool',
        'log'          => '?int_bool',
        'limit'        => '?integer'
    ];
}
