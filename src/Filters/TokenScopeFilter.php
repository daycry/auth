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
use Daycry\Auth\Entities\AccessToken;
use Daycry\Auth\Entities\User;

/**
 * API Token Scope Authorization Filter.
 *
 * Validates that the access token used to authenticate the current request
 * grants every scope declared in the filter arguments. Tokens with the
 * `*` wildcard scope satisfy any check.
 *
 * Usage in routes:
 *     $routes->group('api', ['filter' => 'access_token,token-scope:read'], ...);
 *     $routes->post('/posts', 'Posts::create', ['filter' => 'access_token,token-scope:posts.write']);
 *
 * Multiple scopes are AND-ed:
 *     ['filter' => 'token-scope:read,write']  // requires BOTH read AND write
 *
 * Requires the request to have already been authenticated by the
 * `access_token` (or compatible) filter — the user's `currentAccessToken()`
 * must return a non-null `AccessToken` entity.
 */
class TokenScopeFilter extends AbstractAuthFilter
{
    /**
     * Ensures the current access token grants every scope listed in $arguments.
     */
    protected function isAuthorized(array $arguments): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        $token = $this->resolveCurrentAccessToken($user);

        if (! $token instanceof AccessToken) {
            return false;
        }

        foreach ($arguments as $scope) {
            $scope = trim((string) $scope);

            if ($scope === '') {
                continue;
            }

            if ($token->cant($scope)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns the current access token in use for the authenticated request,
     * or null when no compatible authenticator is active.
     */
    private function resolveCurrentAccessToken(User $user): ?AccessToken
    {
        $token = $user->currentAccessToken();

        return $token instanceof AccessToken ? $token : null;
    }

    /**
     * Builds the denial response (403 JSON for APIs, redirect otherwise).
     */
    protected function redirectToDeniedUrl(RequestInterface $request): RedirectResponse|ResponseInterface
    {
        return $this->buildDeniedResponse($request, config('Auth')->permissionDeniedRedirect());
    }
}
