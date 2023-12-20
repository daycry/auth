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

namespace Daycry\Auth\Filters;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\Response;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Config\Auth;

/**
 * Group Authorization Filter.
 */
class GroupFilter extends AbstractAuthFilter
{
    /**
     * Ensures the user is logged in and a member of one or
     * more groups as specified in the filter.
     */
    protected function isAuthorized(array $arguments): bool
    {
        return auth()->user()->inGroup(...$arguments);
    }

    /**
     * If the user does not belong to the group, redirect to the configured URL with an error message.
     */
    protected function redirectToDeniedUrl(): Response|RedirectResponse
    {
        /** @var Auth $config */
        $config = config('Auth');

        if (auth()->getAuthenticator() instanceof Session) {
            return redirect()->to($config->groupDeniedRedirect())
                ->with('error', lang('Auth.notEnoughPrivilege'));
        }

        return service('response')->setStatusCode(
            401,
            lang('Auth.notEnoughPrivilege') // message
        );
    }
}
