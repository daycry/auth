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

use Daycry\Auth\Entities\Permission;

class PermissionModel extends BaseModel
{
    protected $primaryKey     = 'id';
    protected $returnType     = Permission::class;
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'name',
        'created_at',
    ];
    protected $useTimestamps      = true;
    protected $createdField       = 'created_at';
    protected $updatedField       = 'updated_at';
    protected $deletedField       = 'deleted_at';
    protected $validationRules    = [];
    protected $validationMessages = [];
    protected $skipValidation     = false;

    protected function initialize(): void
    {
        parent::initialize();

        $this->table = $this->tables['permissions'];
    }

    /**
     * @param list<int>|list<string> $permissionIds
     *
     * @return list<Permission>
     */
    public function getByIds(array $permissionIds): array
    {
        return $this->whereIn('id', $permissionIds)->orderBy($this->primaryKey)->findAll();
    }
}
