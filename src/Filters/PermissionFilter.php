<?php

declare(strict_types=1);

namespace Daycry\Auth\Filters;

use CodeIgniter\HTTP\RedirectResponse;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Config\Auth;

/**
 * Group Authorization Filter.
 */
class PermissionFilter extends AbstractAuthFilter
{
    /**
     * Ensures the user is logged in and a member of one or
     * more groups as specified in the filter.
     */
    protected function isAuthorized(array $arguments): bool
    {
        foreach ($arguments as $permission) {
            if (auth()->user()->can($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * If the user does not belong to the group, redirect to the configured URL with an error message.
     */
    protected function redirectToDeniedUrl(): RedirectResponse
    {
        /** @var Auth $config */
        $config = config('Auth');

        if(auth()->getAuthenticator() instanceof Session)
        {
            return redirect()->to($config->permissionDeniedRedirect())
                ->with('error', lang('Auth.notEnoughPrivilege'));
        }else{
            return service('response')->setStatusCode(
                401,
                lang('Auth.notEnoughPrivilege') // message
            );
        }
    }
}
