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

/**
 * Shared persistence for the RBAC pivot tables (groups_users,
 * permissions_users, permissions_groups): rows that link an "owner"
 * (user or group) to a related record and may expire via `until_at`.
 */
abstract class PivotModel extends BaseModel
{
    protected $primaryKey     = 'id';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $deletedField   = 'deleted_at';

    /**
     * Key of the pivot table in Auth::$tables.
     */
    protected string $tableKey;

    /**
     * Column referencing the owner (e.g. `user_id`).
     */
    protected string $ownerColumn;

    /**
     * Column referencing the related record (e.g. `group_id`).
     */
    protected string $relatedColumn;

    protected function initialize(): void
    {
        parent::initialize();

        $this->table = $this->tables[$this->tableKey];
    }

    /**
     * Returns the owner's active rows (permanent or not yet expired).
     *
     * @param int|string $ownerId
     *
     * @return list<mixed> Rows hydrated as the subclass's $returnType
     */
    protected function findActiveFor($ownerId): array
    {
        $now = Time::now()->format('Y-m-d H:i:s');

        return $this->where($this->ownerColumn, $ownerId)
            ->where($this->deletedField)
            ->groupStart()
            ->where('until_at')
            ->orWhere('until_at >', $now)
            ->groupEnd()
            ->orderBy($this->primaryKey)
            ->findAll();
    }

    /**
     * Deletes the owner's rows, optionally keeping the given related ids.
     *
     * @param int|string $ownerId
     * @param mixed      $keepRelatedIds Anything whereNotIn() accepts (ids, subquery); null deletes all
     */
    protected function deleteForOwner($ownerId, $keepRelatedIds = null): void
    {
        $builder = $this->builder()->where($this->ownerColumn, $ownerId);

        if ($keepRelatedIds !== null) {
            $builder->whereNotIn($this->relatedColumn, $keepRelatedIds);
        }

        $this->checkQueryReturn($builder->delete());
    }
}
