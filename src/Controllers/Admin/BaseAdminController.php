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

use Daycry\Auth\Controllers\BaseAuthController;

/**
 * BaseAdminController
 *
 * All admin sub-controllers extend this class.
 * Consumers should protect the admin route group with at least
 * the `session` filter, plus a `group:admin` or `permission:admin.access` filter.
 *
 * Example route group:
 *   $routes->group('admin', ['filter' => 'session:group:admin'], static function ($routes): void {
 *       $routes->get('/',                   'Daycry\Auth\Controllers\Admin\DashboardController::index',      ['as' => 'admin-dashboard']);
 *       $routes->get('users',               'Daycry\Auth\Controllers\Admin\UsersController::index',          ['as' => 'admin-users']);
 *       $routes->get('users/(:num)',         'Daycry\Auth\Controllers\Admin\UsersController::show/$1',        ['as' => 'admin-user-show']);
 *       $routes->get('users/(:num)/edit',    'Daycry\Auth\Controllers\Admin\UsersController::edit/$1',        ['as' => 'admin-user-edit']);
 *       $routes->post('users/(:num)/update', 'Daycry\Auth\Controllers\Admin\UsersController::update/$1',      ['as' => 'admin-user-update']);
 *       $routes->post('users/(:num)/ban',    'Daycry\Auth\Controllers\Admin\UsersController::ban/$1',         ['as' => 'admin-user-ban']);
 *       $routes->post('users/(:num)/unban',  'Daycry\Auth\Controllers\Admin\UsersController::unban/$1',       ['as' => 'admin-user-unban']);
 *       $routes->post('users/(:num)/activate','Daycry\Auth\Controllers\Admin\UsersController::activate/$1',  ['as' => 'admin-user-activate']);
 *       $routes->post('users/(:num)/delete', 'Daycry\Auth\Controllers\Admin\UsersController::delete/$1',     ['as' => 'admin-user-delete']);
 *       $routes->get('groups',               'Daycry\Auth\Controllers\Admin\GroupsController::index',         ['as' => 'admin-groups']);
 *       $routes->get('groups/create',        'Daycry\Auth\Controllers\Admin\GroupsController::create',        ['as' => 'admin-group-create']);
 *       $routes->post('groups/store',        'Daycry\Auth\Controllers\Admin\GroupsController::store',         ['as' => 'admin-group-store']);
 *       $routes->get('groups/(:num)/edit',   'Daycry\Auth\Controllers\Admin\GroupsController::edit/$1',       ['as' => 'admin-group-edit']);
 *       $routes->post('groups/(:num)/update','Daycry\Auth\Controllers\Admin\GroupsController::update/$1',     ['as' => 'admin-group-update']);
 *       $routes->post('groups/(:num)/delete','Daycry\Auth\Controllers\Admin\GroupsController::delete/$1',     ['as' => 'admin-group-delete']);
 *       $routes->get('permissions',               'Daycry\Auth\Controllers\Admin\PermissionsController::index',         ['as' => 'admin-permissions']);
 *       $routes->get('permissions/create',        'Daycry\Auth\Controllers\Admin\PermissionsController::create',        ['as' => 'admin-permission-create']);
 *       $routes->post('permissions/store',        'Daycry\Auth\Controllers\Admin\PermissionsController::store',         ['as' => 'admin-permission-store']);
 *       $routes->get('permissions/(:num)/edit',   'Daycry\Auth\Controllers\Admin\PermissionsController::edit/$1',       ['as' => 'admin-permission-edit']);
 *       $routes->post('permissions/(:num)/update','Daycry\Auth\Controllers\Admin\PermissionsController::update/$1',     ['as' => 'admin-permission-update']);
 *       $routes->post('permissions/(:num)/delete','Daycry\Auth\Controllers\Admin\PermissionsController::delete/$1',     ['as' => 'admin-permission-delete']);
 *       $routes->get('logs',                 'Daycry\Auth\Controllers\Admin\LogsController::index',           ['as' => 'admin-logs']);
 *       $routes->post('logs/purge',          'Daycry\Auth\Controllers\Admin\LogsController::purge',           ['as' => 'admin-logs-purge']);
 *   });
 */
abstract class BaseAdminController extends BaseAuthController
{
    protected function getValidationRules(): array
    {
        return [];
    }
}
