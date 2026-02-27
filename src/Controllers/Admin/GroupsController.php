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

namespace Daycry\Auth\Controllers\Admin;

use CodeIgniter\HTTP\RedirectResponse;
use Daycry\Auth\Entities\Group;
use Daycry\Auth\Models\GroupModel;
use Daycry\Auth\Models\GroupUserModel;
use Daycry\Auth\Models\PermissionGroupModel;
use Daycry\Auth\Models\PermissionModel;

/**
 * Admin Groups Controller — CRUD for groups (roles).
 */
class GroupsController extends BaseAdminController
{
    /**
     * List all groups with member counts.
     */
    public function index(): string
    {
        /** @var GroupModel $groupModel */
        $groupModel = model(GroupModel::class);

        /** @var GroupUserModel $groupUserModel */
        $groupUserModel = model(GroupUserModel::class);

        $groups = $groupModel->orderBy('name')->findAll();

        // Attach member counts using group_id
        $counts = [];

        foreach ($groups as $group) {
            $counts[$group->id] = $groupUserModel->where('group_id', $group->id)->countAllResults();
        }

        return $this->view('Daycry\\Auth\\Views\\admin\\groups\\index', [
            'groups' => $groups,
            'counts' => $counts,
        ]);
    }

    /**
     * Show the create-group form.
     */
    public function create(): string
    {
        /** @var PermissionModel $permissionModel */
        $permissionModel = model(PermissionModel::class);

        return $this->view('Daycry\\Auth\\Views\\admin\\groups\\form', [
            'group'    => null,
            'allPerms' => $permissionModel->findAll(),
            'assigned' => [],
        ]);
    }

    /**
     * Persist a new group.
     */
    public function store(): RedirectResponse
    {
        /** @var GroupModel $groupModel */
        $groupModel = model(GroupModel::class);

        $name        = trim((string) $this->request->getPost('name'));
        $description = trim((string) $this->request->getPost('description'));

        if ($name === '') {
            return redirect()->route('admin-group-create')->with('error', 'Group name is required.');
        }

        $group              = new Group();
        $group->name        = $name;
        $group->description = $description !== '' ? $description : null;

        if (! $groupModel->save($group)) {
            return redirect()->route('admin-group-create')
                ->with('errors', $groupModel->errors());
        }

        return redirect()->route('admin-groups')
            ->with('message', "Group \"{$name}\" created.");
    }

    /**
     * Show the edit form for an existing group.
     *
     * @param int|string $id
     */
    public function edit($id): RedirectResponse|string
    {
        $group = $this->findGroupOr404((int) $id);

        if ($group instanceof RedirectResponse) {
            return $group;
        }

        /** @var PermissionModel $permissionModel */
        $permissionModel = model(PermissionModel::class);

        /** @var PermissionGroupModel $pgModel */
        $pgModel = model(PermissionGroupModel::class);

        // Collect IDs of permissions already assigned to this group
        $assigned = array_column(
            $pgModel->where('group_id', $group->id)->findAll(),
            'permission_id',
        );

        return $this->view('Daycry\\Auth\\Views\\admin\\groups\\form', [
            'group'    => $group,
            'allPerms' => $permissionModel->findAll(),
            'assigned' => $assigned,
        ]);
    }

    /**
     * Persist edits to an existing group.
     *
     * @param int|string $id
     */
    public function update($id): RedirectResponse
    {
        $group = $this->findGroupOr404((int) $id);

        if ($group instanceof RedirectResponse) {
            return $group;
        }

        /** @var GroupModel $groupModel */
        $groupModel = model(GroupModel::class);

        $description = trim((string) $this->request->getPost('description'));

        $group->description = $description !== '' ? $description : null;
        $groupModel->save($group);

        // Sync permissions for the group via PermissionGroupModel
        /** @var PermissionGroupModel $pgModel */
        $pgModel = model(PermissionGroupModel::class);

        // The form sends permission IDs (from the checkbox values)
        $newPermIds = array_filter(array_map('intval', (array) $this->request->getPost('permissions')));

        $pgModel->deleteAll($group->id);

        if ($newPermIds !== []) {
            $inserts = [];

            foreach ($newPermIds as $permId) {
                $inserts[] = ['group_id' => $group->id, 'permission_id' => $permId];
            }

            $pgModel->insertBatch($inserts);
        }

        return redirect()->route('admin-groups')
            ->with('message', "Group \"{$group->name}\" updated.");
    }

    /**
     * Delete a group.
     *
     * @param int|string $id
     */
    public function delete($id): RedirectResponse
    {
        $group = $this->findGroupOr404((int) $id);

        if ($group instanceof RedirectResponse) {
            return $group;
        }

        /** @var GroupModel $groupModel */
        $groupModel = model(GroupModel::class);
        $groupModel->delete($group->id, true);

        return redirect()->route('admin-groups')
            ->with('message', "Group \"{$group->name}\" deleted.");
    }

    // ── Private helpers ───────────────────────────────────────────

    private function findGroupOr404(int $id): Group|RedirectResponse
    {
        /** @var GroupModel $groupModel */
        $groupModel = model(GroupModel::class);
        $group      = $groupModel->find($id);

        if ($group === null) {
            return redirect()->route('admin-groups')->with('error', 'Group not found.');
        }

        return $group;
    }
}
