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

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Daycry\Auth\Entities\Endpoint;

/**
 * Auth Rate-Limiting Filter.
 *
 * Provides rated limiting intended for routes.
 */
class RatesFilter implements FilterInterface
{
    /**
     * Intened for use on auth form pages to restrict the number
     * of attempts that can be generated. Restricts it to 10 attempts
     * per minute, which is what auth0 uses.
     *
     * @see https://auth0.com/docs/troubleshoot/customer-support/operational-policies/rate-limit-policy/database-connections-rate-limits
     *
     * @param array|null $arguments
     *
     * @return RedirectResponse|void
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! $request instanceof IncomingRequest) {
            return;
        }

        helper('checkEndpoint');

        $throttler = service('throttler');
        $router    = Services::router();

        $endpoint = checkEndpoint();

        $limit = service('settings')->get('Auth.requestLimit') ?? 10;
        $time  = service('settings')->get('Auth.timeLimit') ?? 60;

        if ($endpoint instanceof Endpoint) {
            $limit = $endpoint->limit ?: $limit;
            $time  = $endpoint->time ?: $time;
        }

        $limitMethod = service('settings')->get('Auth.limitMethod') ?? 'ROUTED_URL';
        $limited_uri = $this->buildLimitedUri($request, $router, $limitMethod);

        $ignoreLimits = false;
        if ($userId = auth()->id()) {
            $ignoreLimits = auth()->user()->ignore_rates ?? false;
        }

        // Restrict requests based on the configured method and limits
        if (! $ignoreLimits && $throttler->check(md5($limited_uri), $limit, $time, 1) === false) {
            return service('response')->setStatusCode(
                429,
                lang('Auth.throttled', [$throttler->getTokenTime()]), // message
            );
        }
    }

    /**
     * Build the URI used for rate limiting based on the configured method
     *
     * @param mixed $router
     */
    private function buildLimitedUri(RequestInterface $request, $router, string $limitMethod): string
    {
        switch ($limitMethod) {
            case 'IP_ADDRESS':
                return 'ip-address:' . $request->getIPAddress();

            case 'USER':
                $username = auth()->user()->username ?? 'anonymous';

                return 'user:' . $username;

            case 'METHOD_NAME':
                return 'method-name:' . $router->controllerName() . '::' . $router->methodName();

            case 'ROUTED_URL':
            default:
                return 'uri:' . $request->getUri()->getPath() . ':' . $request->getMethod();
        }
    }

    /**
     * We don't have anything to do here.
     *
     * @param array|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
        // Nothing required
    }
}
