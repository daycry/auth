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

use Daycry\Auth\Entities\Api;
use Daycry\Auth\Entities\Controller;

class ControllerModel extends BaseModel
{
    protected $primaryKey     = 'id';
    protected $returnType     = Controller::class;
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'api_id',
        'controller',
        'checked_at',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected function initialize(): void
    {
        parent::initialize();

        $this->table = $this->tables['controllers'];
    }

    /**
     * Returns all controllers.
     *
     * @return list<Controller>
     */
    public function getControllers(Api $api): ?array
    {
        return $this->where('api_id', $api->id)->orderBy($this->primaryKey)->findAll();
    }
}
