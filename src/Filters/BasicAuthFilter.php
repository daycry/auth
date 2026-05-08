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
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Daycry\Auth\Authentication\Passwords;
use Daycry\Auth\Models\UserModel;

/**
 * HTTP Basic authentication filter (RFC 7617).
 *
 * Reads the `Authorization: Basic base64(user:pass)` request header and
 * authenticates the user against the configured user provider. Useful for
 * machine-to-machine endpoints (cron, health checks, internal tooling)
 * where managing tokens or sessions is unnecessary overhead.
 *
 * Behaviour:
 *   - On success: logs the user in via the `session` authenticator so
 *     `auth()->user()` works for the rest of the request lifecycle.
 *   - On failure (missing / malformed header, unknown user, wrong
 *     password): returns 401 with `WWW-Authenticate: Basic realm="..."`
 *     so browsers prompt for credentials and clients see the expected
 *     challenge.
 *
 * Use the `once` argument (`basic-auth:once`) when you do NOT want to
 * persist the auth into the session — useful for stateless API endpoints
 * that should re-verify credentials on every request.
 *
 * The realm string can be overridden in `app/Config/Auth.php`:
 *
 *     public string $basicAuthRealm = 'My App API';
 */
class BasicAuthFilter implements FilterInterface
{
    /**
     * @param array|null $arguments ['once'] disables session login.
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $header = $request->getHeaderLine('Authorization');

        $credentials = $this->extractCredentials($header);

        if ($credentials === null) {
            return $this->challenge();
        }

        [$identifier, $password] = $credentials;

        $user = $this->resolveUser($identifier);

        if ($user === null) {
            return $this->challenge();
        }

        /** @var Passwords $passwords */
        $passwords = service('passwords');

        if (! $passwords->verify($password, $user->getPasswordHash())) {
            return $this->challenge();
        }

        // Persist into the session unless the caller asked for stateless auth.
        $stateless = $arguments !== null && in_array('once', $arguments, true);

        if (! $stateless) {
            auth('session')->login($user);
        } else {
            // Stateless: still expose $user via the session authenticator's
            // in-memory state so $this->request->user() / auth()->user()
            // work for downstream code in this request.
            auth('session')->loginById($user->id);
        }

        return null;
    }

    /**
     * Extracts and decodes the `user:password` pair from a `Basic` header.
     * Returns null when the header is missing, the scheme is wrong, or the
     * payload is not valid base64 / does not contain a colon.
     *
     * @return array{0: string, 1: string}|null
     */
    private function extractCredentials(string $header): ?array
    {
        if ($header === '' || ! str_starts_with(strtolower($header), strtolower('Basic '))) {
            return null;
        }

        $payload = trim(substr($header, 6));
        $decoded = base64_decode($payload, true);

        if ($decoded === false || ! str_contains($decoded, ':')) {
            return null;
        }

        [$user, $pass] = explode(':', $decoded, 2);

        if ($user === '' || $pass === '') {
            return null;
        }

        return [$user, $pass];
    }

    /**
     * Resolves a user by email when the identifier looks like an email,
     * otherwise by username. Returns null when no match is found.
     */
    private function resolveUser(string $identifier)
    {
        /** @var UserModel $userModel */
        $userModel = model(UserModel::class);

        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false
            ? 'email'
            : 'username';

        return $userModel->findByCredentials([$field => $identifier]);
    }

    /**
     * Returns a 401 response with the standard `WWW-Authenticate` challenge.
     */
    private function challenge(): ResponseInterface
    {
        $realm = (string) (config('Auth')->basicAuthRealm ?? 'Restricted');

        // RFC 7617 §2 — realm must be quoted; escape any embedded quotes.
        $realm = str_replace('"', '\\"', $realm);

        return service('response')
            ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED)
            ->setHeader('WWW-Authenticate', 'Basic realm="' . $realm . '", charset="UTF-8"')
            ->setJSON(['message' => lang('Auth.badAttempt')]);
    }

    /**
     * @param array|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
        // Nothing required.
    }
}
