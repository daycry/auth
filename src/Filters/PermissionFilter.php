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
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\Response;
use Daycry\Auth\Config\Auth;

/**
 * Group Authorization Filter.
 */
class PermissionFilter extends AbstractAuthFilter
{
    /**
     * Ensures the user is logged in and has one or more
     * of the specified permissions.
     */
    protected function isAuthorized(array $arguments): bool
    {
        // Use the native can() method which accepts multiple permissions via variadic params
        return auth()->user()->can(...$arguments);
    }

    /**
     * If the user does not belong to the group, redirect to the configured URL with an error message.
     */
    protected function redirectToDeniedUrl(RequestInterface $request): RedirectResponse|Response
    {
        /** @var Auth $config */
        $config = config('Auth');

        if ($this->expectsJson($request)) {
            return service('response')->setStatusCode(
                401,
                lang('Auth.notEnoughPrivilege'), // message
            );
        }

        return redirect()->to($config->permissionDeniedRedirect())
            ->with('error', lang('Auth.notEnoughPrivilege'));
    }
}
