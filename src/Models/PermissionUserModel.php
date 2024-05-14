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

use CodeIgniter\I18n\Time;
use Daycry\Auth\Entities\PermissionUser;
use Daycry\Auth\Entities\User;

class PermissionUserModel extends BaseModel
{
    protected $primaryKey     = 'id';
    protected $returnType     = PermissionUser::class;
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'user_id',
        'permission_id',
        'until_at',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';
    
    protected $validationRules    = [];
    protected $validationMessages = [];
    protected $skipValidation     = false;

    protected function initialize(): void
    {
        parent::initialize();

        $this->table = $this->tables['permissions_users'];
    }

    /**
     * Returns all user groups.
     *
     * @return list<UserGroup>
     */
    public function getForUser(User $user): ?array
    {
        return $this->where('user_id', $user->id)
            ->where('until_at', null)
            ->orWhere('until_at >', Time::now()->format('Y-m-d H:i:s'))
            ->orderBy($this->primaryKey)->findAll();
    }

    /**
     * @param int|string $userId
     */
    public function deleteAll($userId): void
    {
        $return = $this->builder()
            ->where('user_id', $userId)
            ->delete();

        $this->checkQueryReturn($return);
    }

    /**
     * @param int|string $userId
     * @param mixed      $cache
     */
    public function deleteNotIn($userId, $cache): void
    {
        $return = $this->builder()
            ->where('user_id', $userId)
            ->whereNotIn('permission_id', $cache)
            ->delete();

        $this->checkQueryReturn($return);
    }
}
