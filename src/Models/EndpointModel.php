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
        'scope',
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
     * @return list<Endpoint>
     */
    public function getEndpoints(Controller $controller): ?array
    {
        return $this->where('controller_id', $controller->id)->orderBy($this->primaryKey)->findAll();
    }
}
