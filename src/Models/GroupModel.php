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

use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Validation\ValidationInterface;
use Daycry\Auth\Entities\Group;

class GroupModel extends BaseModel
{
    protected $table;
    protected $primaryKey     = 'id';
    protected $returnType     = Group::class;
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'name',
        'scopes',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    public function __construct(?ConnectionInterface &$db = null, ?ValidationInterface $validation = null)
    {
        parent::__construct($db, $validation);

        $this->table = $this->tables['groups'];
    }

    /**
     * @param int[]|string[] $groupIds
     *
     * @return Group[]
     */
    public function getGroupsByIds(array $groupIds): array
    {
        return $this->whereIn('id', $groupIds)->orderBy($this->primaryKey)->findAll();
    }
}
