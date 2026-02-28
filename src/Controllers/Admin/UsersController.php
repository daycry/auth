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
use Daycry\Auth\Entities\User;
use Daycry\Auth\Exceptions\AuthorizationException;
use Daycry\Auth\Models\DeviceSessionModel;
use Daycry\Auth\Models\GroupModel;
use Daycry\Auth\Models\PermissionModel;
use Daycry\Auth\Models\UserModel;

/**
 * Admin Users Controller — full CRUD for user accounts.
 */
class UsersController extends BaseAdminController
{
    /**
     * Paginated list of users with optional keyword search.
     */
    public function index(): string
    {
        /** @var UserModel $userModel */
        $userModel = model(UserModel::class);

        $search = (string) $this->request->getGet('q');

        if ($search !== '') {
            $userModel->groupStart()
                ->like('username', $search)
                ->orLike('status', $search)
                ->groupEnd();
        }

        $users = $userModel
            ->orderBy('id', 'DESC')
            ->paginate(20, 'default');

        return $this->view('Daycry\\Auth\\Views\\admin\\users\\index', [
            'users'  => $users,
            'pager'  => $userModel->pager,
            'search' => $search,
        ]);
    }

    /**
     * Show a single user's detail: info, groups, permissions, device sessions.
     *
     * @param int|string $id
     */
    public function show($id): RedirectResponse|string
    {
        $user = $this->findUserOr404((int) $id);

        if ($user instanceof RedirectResponse) {
            return $user;
        }

        /** @var DeviceSessionModel $deviceModel */
        $deviceModel = model(DeviceSessionModel::class);

        return $this->view('Daycry\\Auth\\Views\\admin\\users\\show', [
            'user'     => $user,
            'sessions' => $deviceModel->getAllForUser($user),
        ]);
    }

    /**
     * Show the edit form for a user.
     *
     * @param int|string $id
     */
    public function edit($id): RedirectResponse|string
    {
        $user = $this->findUserOr404((int) $id);

        if ($user instanceof RedirectResponse) {
            return $user;
        }

        /** @var GroupModel $groupModel */
        $groupModel = model(GroupModel::class);

        /** @var PermissionModel $permissionModel */
        $permissionModel = model(PermissionModel::class);

        return $this->view('Daycry\\Auth\\Views\\admin\\users\\edit', [
            'user'      => $user,
            'allGroups' => $groupModel->findAll(),
            'allPerms'  => $permissionModel->findAll(),
        ]);
    }

    /**
     * Process the edit form.
     *
     * @param int|string $id
     */
    public function update($id): RedirectResponse
    {
        $user = $this->findUserOr404((int) $id);

        if ($user instanceof RedirectResponse) {
            return $user;
        }

        /** @var UserModel $userModel */
        $userModel = model(UserModel::class);

        $username = trim((string) $this->request->getPost('username'));
        $email    = trim((string) $this->request->getPost('email'));
        $active   = (bool) $this->request->getPost('active');

        if ($username !== '') {
            $user->username = $username;
        }

        if ($email !== '') {
            $user->email = $email;
        }

        $user->active = $active;

        $userModel->save($user);

        try {
            // Sync groups
            $newGroups = array_filter((array) $this->request->getPost('groups'));
            $user->syncGroups(...$newGroups);

            // Sync permissions
            $newPerms = array_filter((array) $this->request->getPost('permissions'));
            $user->syncPermissions(...$newPerms);
        } catch (AuthorizationException $e) {
            return redirect()->route('admin-user-edit', [$id])
                ->with('error', $e->getMessage());
        }

        return redirect()->route('admin-user-show', [$id])
            ->with('message', 'User updated successfully.');
    }

    /**
     * Ban a user.
     *
     * @param int|string $id
     */
    public function ban($id): RedirectResponse
    {
        $user = $this->findUserOr404((int) $id);

        if ($user instanceof RedirectResponse) {
            return $user;
        }

        $user->ban((string) $this->request->getPost('reason'));

        return redirect()->route('admin-user-show', [$id])
            ->with('message', 'User has been banned.');
    }

    /**
     * Remove ban from a user.
     *
     * @param int|string $id
     */
    public function unban($id): RedirectResponse
    {
        $user = $this->findUserOr404((int) $id);

        if ($user instanceof RedirectResponse) {
            return $user;
        }

        $user->unBan();

        return redirect()->route('admin-user-show', [$id])
            ->with('message', 'User has been unbanned.');
    }

    /**
     * Manually activate a user.
     *
     * @param int|string $id
     */
    public function activate($id): RedirectResponse
    {
        $user = $this->findUserOr404((int) $id);

        if ($user instanceof RedirectResponse) {
            return $user;
        }

        /** @var UserModel $userModel */
        $userModel = model(UserModel::class);
        $userModel->activate($user);

        return redirect()->route('admin-user-show', [$id])
            ->with('message', 'User has been activated.');
    }

    /**
     * Delete a user (hard delete).
     *
     * @param int|string $id
     */
    public function delete($id): RedirectResponse
    {
        $user = $this->findUserOr404((int) $id);

        if ($user instanceof RedirectResponse) {
            return $user;
        }

        /** @var UserModel $userModel */
        $userModel = model(UserModel::class);
        $userModel->delete($user->id, true);

        return redirect()->route('admin-users')
            ->with('message', 'User deleted permanently.');
    }

    // ── Private helpers ───────────────────────────────────────────

    /**
     * Find a user by ID or redirect with error.
     */
    private function findUserOr404(int $id): RedirectResponse|User
    {
        /** @var UserModel $userModel */
        $userModel = model(UserModel::class);
        $user      = $userModel->withIdentities()->findById($id);

        if ($user === null) {
            return redirect()->route('admin-users')->with('error', 'User not found.');
        }

        return $user;
    }
}
