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
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

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
        $request   = Services::request();
        $router    = Services::router();

        $endpoint = checkEndpoint();

        if ($endpoint) {
            $limit = ($endpoint->limit) ?: service('settings')->get('Auth.requestLimit');
            $time  = ($endpoint->time) ?: service('settings')->get('Auth.timeLimit');
        }

        switch (service('settings')->get('RestFul.limitMethod')) {
            case 'IP_ADDRESS':
                $api_key     = $request->getIPAddress();
                $limited_uri = 'ip-address:' . $request->getIPAddress();
                break;

            case 'USER':
                $limited_uri = 'user:' . auth()->user()->username;
                break;

            case 'METHOD_NAME':
                $limited_uri = 'method-name:' . $router->controllerName() . '::' . $router->methodName();
                break;

            case 'ROUTED_URL':
            default:
                $limited_uri = 'uri:' . $request->getPath() . ':' . $request->getMethod(); // It's good to differentiate GET from PUT
                break;
        }

        $ignoreRates = false;

        if ($userId = auth()->id()) {
            $ignoreLimits = auth()->user()->ignore_rates;
        }

        if (! $ignoreLimits) {
            // Restrict an IP address to no more than 10 requests
            // per minute on any auth-form pages (login, register, forgot, etc).
            if ($throttler->check(md5($limited_uri), $limit, $time, 1) === false) {
                return service('response')->setStatusCode(
                    429,
                    lang('Auth.throttled', [$throttler->getTokenTime()]) // message
                );
            }
        }
    }

    /**
     * We don't have anything to do here.
     *
     * @param Response|ResponseInterface $response
     * @param array|null                 $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
        // Nothing required
    }
}
