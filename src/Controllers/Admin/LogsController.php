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
use CodeIgniter\I18n\Time;
use Daycry\Auth\Models\LoginModel;

/**
 * Admin Logs Controller — view and purge login attempt logs.
 */
class LogsController extends BaseAdminController
{
    /**
     * Paginated login log with optional filters.
     */
    public function index(): string
    {
        /** @var LoginModel $loginModel */
        $loginModel = model(LoginModel::class);

        $q       = (string) $this->request->getGet('q');       // identifier / email
        $success = $this->request->getGet('success');           // '1' | '0' | ''
        $from    = (string) $this->request->getGet('from');     // Y-m-d
        $to      = (string) $this->request->getGet('to');       // Y-m-d

        if ($q !== '') {
            $loginModel->like('identifier', $q);
        }

        if ($success === '1' || $success === '0') {
            $loginModel->where('success', (int) $success);
        }

        if ($from !== '') {
            $loginModel->where('date >=', $from . ' 00:00:00');
        }

        if ($to !== '') {
            $loginModel->where('date <=', $to . ' 23:59:59');
        }

        $logs = $loginModel
            ->orderBy('id', 'DESC')
            ->paginate(30, 'default');

        return $this->view('Daycry\\Auth\\Views\\admin\\logs\\index', [
            'logs'    => $logs,
            'pager'   => $loginModel->pager,
            'q'       => $q,
            'success' => $success,
            'from'    => $from,
            'to'      => $to,
        ]);
    }

    /**
     * Delete login records older than N days.
     */
    public function purge(): RedirectResponse
    {
        $days = max(1, (int) $this->request->getPost('days'));

        /** @var LoginModel $loginModel */
        $loginModel = model(LoginModel::class);

        $cutoff = Time::now()->subDays($days)->format('Y-m-d H:i:s');
        $loginModel->where('date <', $cutoff)->delete();

        return redirect()->route('admin-logs')
            ->with('message', "Logs older than {$days} day(s) have been purged.");
    }
}
