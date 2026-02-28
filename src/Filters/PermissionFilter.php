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
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Permission Authorization Filter.
 */
class PermissionFilter extends AbstractAuthFilter
{
    /**
     * Ensures the user is logged in and has one or more
     * of the permissions specified in the filter.
     */
    protected function isAuthorized(array $arguments): bool
    {
        return auth()->user()->can(...$arguments);
    }

    /**
     * Redirect to the permission-denied URL (or return 403 JSON) when access is denied.
     */
    protected function redirectToDeniedUrl(RequestInterface $request): RedirectResponse|ResponseInterface
    {
        return $this->buildDeniedResponse($request, config('Auth')->permissionDeniedRedirect());
    }
}
