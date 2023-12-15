<?php

declare(strict_types=1);

namespace Daycry\Auth\Models;

use Daycry\Auth\Entities\Api;

class ApiModel extends BaseModel
{
    protected $primaryKey     = 'id';
    protected $returnType     = Api::class;
    protected $useSoftDeletes = false;

    protected $allowedFields  = [
        'url',
        'checked_at'
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected function initialize(): void
    {
        parent::initialize();

        $this->table = $this->tables['apis'];
    }
}
