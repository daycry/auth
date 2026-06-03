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

        $limit = (int) (service('settings')->get('AuthSecurity.requestLimit') ?? 10);
        $time  = (int) (service('settings')->get('AuthSecurity.timeLimit') ?? 60);

        // Per-route arguments (e.g. `rates:50,MINUTE`) override the global
        // defaults for that route.
        [$limit, $time] = $this->applyArguments($arguments, $limit, $time);

        // A configured endpoint row (runtime/admin override) wins over both.
        if ($endpoint instanceof Endpoint) {
            $limit = $endpoint->limit ?: $limit;
            $time  = $endpoint->time ?: $time;
        }

        $limitMethod = service('settings')->get('AuthSecurity.limitMethod') ?? 'ROUTED_URL';
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
     * Applies per-route filter arguments over the resolved limit/time.
     *
     * Accepts `rates:<limit>` and `rates:<limit>,<period>` where `<period>` is
     * either a number of seconds or a named unit (SECOND, MINUTE, HOUR, DAY,
     * WEEK). Unknown/empty arguments leave the corresponding value unchanged.
     *
     * @param array<int, string>|null $arguments
     *
     * @return array{0: int, 1: int} [limit, time]
     */
    private function applyArguments($arguments, int $limit, int $time): array
    {
        if (! is_array($arguments) || $arguments === []) {
            return [$limit, $time];
        }

        if (isset($arguments[0]) && is_numeric($arguments[0])) {
            $limit = (int) $arguments[0];
        }

        if (isset($arguments[1])) {
            $time = $this->parsePeriod($arguments[1], $time);
        }

        return [$limit, $time];
    }

    /**
     * Converts a period argument to seconds. Accepts a numeric value (seconds)
     * or a named unit; falls back to $default for anything unrecognised.
     */
    private function parsePeriod(string $period, int $default): int
    {
        if (is_numeric($period)) {
            return (int) $period;
        }

        return match (strtoupper($period)) {
            'SECOND', 'SECONDS', 'SEC' => 1,
            'MINUTE', 'MINUTES', 'MIN' => 60,
            'HOUR', 'HOURS'            => 3600,
            'DAY', 'DAYS'              => 86400,
            'WEEK', 'WEEKS'            => 604800,
            default                    => $default,
        };
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
