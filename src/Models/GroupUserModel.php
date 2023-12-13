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
use CodeIgniter\I18n\Time;
use CodeIgniter\Validation\ValidationInterface;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Entities\UserGroup;
use Daycry\Auth\Entities\UserIdentity;

class GroupUserModel extends BaseModel
{
    protected $table;
    protected $primaryKey     = 'id';
    protected $returnType     = UserGroup::class;
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'user_id',
        'group_id',
        'until_at'
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    public function __construct(?ConnectionInterface &$db = null, ?ValidationInterface $validation = null)
    {
        parent::__construct($db, $validation);

        $this->table = $this->tables['groups_users'];
    }

    /**
     * Returns all user groups.
     *
     * @return UserGroup[]
     */
    public function getGroups(User $user): ?array
    {
        return $this->where('user_id', $user->id)
            ->where('until_at', null)
            ->orWhere('until_at >', Time::now()->format('Y-m-d H:i:s'))
            ->orderBy($this->primaryKey)->findAll();
    }
}
