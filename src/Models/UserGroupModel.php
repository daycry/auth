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
use Daycry\Auth\Entities\User;
use Daycry\Auth\Entities\UserGroup;
use Daycry\Auth\Entities\UserIdentity;

class UserGroupModel extends BaseModel
{
    protected $table;
    protected $primaryKey     = 'id';
    protected $returnType     = UserGroup::class;
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'user_id',
        'group_id',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    public function __construct(?ConnectionInterface &$db = null, ?ValidationInterface $validation = null)
    {
        parent::__construct($db, $validation);

        $this->table = $this->tables['users_groups'];
    }

    /**
     * Returns all user identities.
     *
     * @return UserIdentity[]
     */
    public function getGroups(User $user): ?array
    {
        return $this->where('user_id', $user->id)->orderBy($this->primaryKey)->findAll();
    }
}
