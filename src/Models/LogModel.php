<?php

declare(strict_types=1);

namespace Daycry\Auth\Models;

use Daycry\Auth\Entities\Log;

class LogModel extends BaseModel
{
    protected $primaryKey     = 'id';
    protected $returnType     = Log::class;
    protected $useSoftDeletes = false;

    protected $allowedFields  = [
        'user_id',
        'uri',
        'method',
        'params',
        'ip_address',
        'duration',
        'response_code',
        'authorized'
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected function initialize(): void
    {
        parent::initialize();

        $this->table = $this->tables['logs'];
    }
}
