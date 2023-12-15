<?php

declare(strict_types=1);

namespace Daycry\Auth\Models;

use CodeIgniter\Validation\ValidationInterface;
use CodeIgniter\Database\ConnectionInterface;
use Daycry\Auth\Entities\Controller;
use Daycry\Auth\Entities\Endpoint;

class EndpointModel extends BaseModel
{
    protected $primaryKey     = 'id';
    protected $returnType     = Endpoint::class;
    protected $useSoftDeletes = false;

    protected $allowedFields  = [
        'controller_id',
        'method',
        'checked_at',
        'auth',
        'access_token',
        'log',
        'limit',
        'time',
        'scope'
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected function initialize(): void
    {
        parent::initialize();

        $this->table = $this->tables['endpoints'];
    }

    /**
     * Returns all Endpoints.
     *
     * @return Endpoint[]
     */
    public function getEndpoints(Controller $controller): ?array
    {
        return $this->where('controller_id', $controller->id)->orderBy($this->primaryKey)->findAll();
    }
}
