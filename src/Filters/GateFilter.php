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
use Daycry\Auth\Authorization\Gate;

/**
 * Authorization filter for closure-based abilities and class-based
 * policies registered with {@see Gate}.
 *
 * Apply on routes that map cleanly to a single ability **without** a
 * resource argument — for example, abilities that depend solely on the
 * authenticated user's groups / claims (`gate:dashboard.view`,
 * `gate:billing.access`).
 *
 * For abilities that need a resource instance (a Post, an Order, etc.)
 * use the Gate API directly inside the controller method:
 *
 *     Gate::authorize('post.update', $post);
 *
 * Filter usage:
 *
 *     $routes->get('admin', 'Admin::index', ['filter' => 'gate:admin.access']);
 */
class GateFilter extends AbstractAuthFilter
{
    protected function isAuthorized(array $arguments): bool
    {
        if ($arguments === []) {
            return false;
        }

        /** @var Gate $gate */
        $gate = service('gate');

        foreach ($arguments as $ability) {
            $ability = trim((string) $ability);

            if ($ability === '') {
                continue;
            }

            if ($gate->denies($ability)) {
                return false;
            }
        }

        return true;
    }

    protected function redirectToDeniedUrl(RequestInterface $request): RedirectResponse|ResponseInterface
    {
        return $this->buildDeniedResponse($request, config('Auth')->permissionDeniedRedirect());
    }
}
