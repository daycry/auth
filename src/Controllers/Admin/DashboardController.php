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

use Daycry\Auth\Models\GroupModel;
use Daycry\Auth\Models\LoginModel;
use Daycry\Auth\Models\PermissionModel;
use Daycry\Auth\Models\UserModel;

/**
 * Admin Dashboard — high-level overview of the auth system.
 */
class DashboardController extends BaseAdminController
{
    /**
     * Show the admin dashboard with aggregate statistics.
     */
    public function index(): string
    {
        /** @var UserModel $userModel */
        $userModel = model(UserModel::class);

        /** @var GroupModel $groupModel */
        $groupModel = model(GroupModel::class);

        /** @var PermissionModel $permissionModel */
        $permissionModel = model(PermissionModel::class);

        /** @var LoginModel $loginModel */
        $loginModel = model(LoginModel::class);

        $totalUsers       = $userModel->countAllResults(false);
        $activeUsers      = $userModel->where('active', 1)->countAllResults();
        $totalGroups      = $groupModel->countAllResults(false);
        $totalPermissions = $permissionModel->countAllResults(false);

        $recentLogins = $loginModel
            ->orderBy('id', 'DESC')
            ->limit(15)
            ->findAll();

        return $this->view('Daycry\\Auth\\Views\\admin\\dashboard', [
            'totalUsers'       => $totalUsers,
            'activeUsers'      => $activeUsers,
            'totalGroups'      => $totalGroups,
            'totalPermissions' => $totalPermissions,
            'recentLogins'     => $recentLogins,
        ]);
    }
}
