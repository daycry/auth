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
use Daycry\Auth\Entities\Permission;
use Daycry\Auth\Models\PermissionModel;

/**
 * Admin Permissions Controller — CRUD for individual permissions.
 */
class PermissionsController extends BaseAdminController
{
    /**
     * List all permissions.
     */
    public function index(): string
    {
        /** @var PermissionModel $permissionModel */
        $permissionModel = model(PermissionModel::class);

        $permissions = $permissionModel->orderBy('name')->findAll();

        return $this->view('Daycry\\Auth\\Views\\admin\\permissions\\index', [
            'permissions' => $permissions,
        ]);
    }

    /**
     * Show the create form.
     */
    public function create(): string
    {
        return $this->view('Daycry\\Auth\\Views\\admin\\permissions\\form', [
            'permission' => null,
        ]);
    }

    /**
     * Persist a new permission.
     */
    public function store(): RedirectResponse
    {
        /** @var PermissionModel $permissionModel */
        $permissionModel = model(PermissionModel::class);

        $name = trim((string) $this->request->getPost('name'));

        if ($name === '') {
            return redirect()->route('admin-permission-create')->with('error', 'Permission name is required.');
        }

        $perm       = new Permission();
        $perm->name = $name;

        if (! $permissionModel->save($perm)) {
            return redirect()->route('admin-permission-create')
                ->with('errors', $permissionModel->errors());
        }

        return redirect()->route('admin-permissions')
            ->with('message', "Permission \"{$name}\" created.");
    }

    /**
     * Show the edit form.
     *
     * @param int|string $id
     */
    public function edit($id): RedirectResponse|string
    {
        $perm = $this->findPermOr404((int) $id);

        if ($perm instanceof RedirectResponse) {
            return $perm;
        }

        return $this->view('Daycry\\Auth\\Views\\admin\\permissions\\form', [
            'permission' => $perm,
        ]);
    }

    /**
     * Persist edits.
     *
     * @param int|string $id
     */
    public function update($id): RedirectResponse
    {
        $perm = $this->findPermOr404((int) $id);

        if ($perm instanceof RedirectResponse) {
            return $perm;
        }

        /** @var PermissionModel $permissionModel */
        $permissionModel = model(PermissionModel::class);

        $name = trim((string) $this->request->getPost('name'));

        if ($name !== '') {
            $perm->name = $name;
        }

        $permissionModel->save($perm);

        return redirect()->route('admin-permissions')
            ->with('message', 'Permission updated.');
    }

    /**
     * Delete a permission.
     *
     * @param int|string $id
     */
    public function delete($id): RedirectResponse
    {
        $perm = $this->findPermOr404((int) $id);

        if ($perm instanceof RedirectResponse) {
            return $perm;
        }

        /** @var PermissionModel $permissionModel */
        $permissionModel = model(PermissionModel::class);
        $permissionModel->delete($perm->id, true);

        return redirect()->route('admin-permissions')
            ->with('message', "Permission \"{$perm->name}\" deleted.");
    }

    // ── Private helpers ───────────────────────────────────────────

    private function findPermOr404(int $id): Permission|RedirectResponse
    {
        /** @var PermissionModel $permissionModel */
        $permissionModel = model(PermissionModel::class);
        $perm            = $permissionModel->find($id);

        if ($perm === null) {
            return redirect()->route('admin-permissions')->with('error', 'Permission not found.');
        }

        return $perm;
    }
}
