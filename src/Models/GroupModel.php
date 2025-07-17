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

use Daycry\Auth\Entities\Group;

class GroupModel extends BaseModel
{
    protected $table;
    protected $primaryKey     = 'id';
    protected $returnType     = Group::class;
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'name',
        'description',
    ];
    protected $useTimestamps   = true;
    protected $createdField    = 'created_at';
    protected $updatedField    = 'updated_at';
    protected $deletedField    = 'deleted_at';
    protected $validationRules = [
        'name' => 'required|max_length[30]',
    ];
    protected $validationMessages = [];
    protected $skipValidation     = false;

    protected function initialize(): void
    {
        parent::initialize();

        $this->table = $this->tables['groups'];
    }

    /**
     * @param list<int>|list<string> $groupIds
     *
     * @return list<Group>
     */
    public function getByIds(array $groupIds): array
    {
        return $this->whereIn('id', $groupIds)->orderBy($this->primaryKey)->findAll();
    }
}
