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
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Daycry\Auth\Interfaces\MfaPolicyInterface;

/**
 * Enforces configured MFA enrollment and a factor proof on this session.
 */
class MfaRequiredFilter implements FilterInterface
{
    /**
     * @param array|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        if (! $request instanceof IncomingRequest || ! auth('session')->loggedIn()) {
            return null;
        }

        $user = auth('session')->user();
        /** @var MfaPolicyInterface $policy */
        $policy = service('mfaPolicy');
        if ($user === null || ! $policy->requires($user) || $this->isEnrollmentRequest($request, ! $policy->hasFactor($user))) {
            return null;
        }

        if ($policy->enrollmentRequired($user)) {
            return $this->deny($request, 'security', 'Auth.mfaEnrollmentRequired', 'mfa_enrollment_required');
        }

        if ($policy->hasFactor($user) && ! $policy->verified($user)) {
            session()->setTempdata('passwordConfirmIntendedUrl', (string) $request->getUri(), 300);

            return $this->deny($request, 'password-confirm-show', 'Auth.mfaVerificationRequired', 'mfa_verification_required');
        }

        return null;
    }

    /**
     * Only the exact registered routes needed to enroll, verify or sign out.
     */
    protected function isEnrollmentRequest(IncomingRequest $request, bool $mayEnroll): bool
    {
        $path  = trim($request->getUri()->getPath(), '/');
        $names = ['password-confirm-show' => 'GET', 'password-confirm' => 'POST', 'password-confirm-options' => 'POST', 'logout' => 'GET'];
        if ($mayEnroll) {
            $names += ['security' => 'GET', 'totp-setup' => 'GET', 'totp-setup-confirm' => 'POST'];
        }

        foreach ($names as $name => $method) {
            $route = route_to($name);
            if ($route !== false && strtoupper($request->getMethod()) === $method
                && $path === trim((string) parse_url(site_url($route), PHP_URL_PATH), '/')) {
                return true;
            }
        }

        // These ceremony endpoints belong to the same signed-in user and
        // retain their normal session, ownership and CSRF checks.
        foreach ($mayEnroll ? ['webauthn/register/options', 'webauthn/register/verify'] : [] as $route) {
            if (strtoupper($request->getMethod()) === 'POST' && $path === trim((string) parse_url(site_url($route), PHP_URL_PATH), '/')) {
                return true;
            }
        }

        return false;
    }

    protected function deny(IncomingRequest $request, string $route, string $message, string $code): ResponseInterface
    {
        if ($request->is('json') || $request->isAJAX() || str_contains(strtolower($request->getHeaderLine('Accept')), 'application/json')) {
            return service('response')->setStatusCode(403)->setJSON([
                'message'  => lang($message),
                'code'     => $code,
                'redirect' => url_to($route),
            ]);
        }

        return redirect()->route($route)->with('error', lang($message));
    }

    /**
     * @param array|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        return null;
    }
}
