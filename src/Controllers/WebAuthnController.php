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

namespace Daycry\Auth\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Exceptions\WebAuthnDuplicateCredentialException;
use Daycry\Auth\Exceptions\WebAuthnException;
use Daycry\Auth\Interfaces\WebAuthnManagerInterface;

/**
 * JSON endpoints for the WebAuthn ceremonies. All methods 404 when the feature
 * is globally disabled (defense in depth on top of Auth::routes() gating).
 */
class WebAuthnController extends BaseAuthController
{
    /**
     * This controller exposes only JSON ceremony endpoints; it does not use
     * the form-validation flow that BaseAuthController otherwise requires.
     */
    protected function getValidationRules(): array
    {
        return [];
    }

    /**
     * Whether passkeys are enabled (`AuthSecurity::$webauthnEnabled`).
     */
    protected function enabled(): bool
    {
        return (bool) (setting('AuthSecurity.webauthnEnabled') ?? false);
    }

    /**
     * The ceremony manager (`service('webAuthnManager')`).
     */
    protected function manager(): WebAuthnManagerInterface
    {
        return service('webAuthnManager');
    }

    /**
     * A JSON error response carrying a fresh CSRF token.
     */
    protected function error(string $message, int $status, string $code = 'error'): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON([
            'status'  => 'error',
            'error'   => $code,
            'message' => $message,
            'token'   => csrf_hash(),
        ]);
    }

    /**
     * Adds the freshly-rotated CSRF token to a JSON payload so the reference
     * ceremony JS can carry it into the follow-up request (CI4 regenerates the
     * token per request when csrf.regenerate is enabled). Harmless for the
     * WebAuthn option objects: the browser ignores unknown top-level keys.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    protected function withToken(array $payload): array
    {
        $payload['token'] = csrf_hash();

        return $payload;
    }

    /**
     * Required MFA cannot be replaced or enrolled using only a remembered session.
     */
    protected function registrationGuard(): ?ResponseInterface
    {
        $user   = auth()->user();
        $policy = service('mfaPolicy');
        if (! $policy->requires($user)) {
            return null;
        }
        if ($policy->hasFactor($user)) {
            return $policy->verified($user) ? null : $this->error(lang('Auth.mfaVerificationRequired'), 403, 'mfa_verification_required');
        }
        $interactive = (int) session(Session::AUTHENTICATED_AT_KEY);
        $confirmed   = (string) session('auth_step_up_user') === (string) $user->id ? (int) session('password_confirmed_at') : 0;
        $latest      = max($interactive, $confirmed);
        $lifetime    = (int) (setting('AuthSecurity.passwordConfirmationLifetime') ?? 0);
        if ($latest <= 0 || $latest > time() || $lifetime <= 0 || time() - $latest >= $lifetime) {
            return $this->error(lang('Auth.passwordConfirmRequired'), 403, 'confirmation_required');
        }

        return null;
    }

    public function registerOptions(): ResponseInterface
    {
        if (! $this->enabled()) {
            return $this->error(lang('Auth.webauthnDisabled'), 404, 'disabled');
        }
        if (! auth()->loggedIn()) {
            return $this->error(lang('Auth.notLoggedIn'), 403, 'forbidden');
        }
        if (($denied = $this->registrationGuard()) !== null) {
            return $denied;
        }

        $body = $this->request->is('json') ? $this->request->getJSON(true) : $this->request->getPost();
        if ((bool) ($body['conditional'] ?? false)) {
            $authenticatedAt = (int) session(Session::AUTHENTICATED_AT_KEY);
            $lifetime        = (int) (setting('AuthSecurity.passwordConfirmationLifetime') ?? 0);
            if (! (bool) setting('AuthSecurity.webauthnAutoUpgrade') || $authenticatedAt <= 0 || $authenticatedAt > time() || $lifetime <= 0 || time() - $authenticatedAt >= $lifetime) {
                return $this->error(lang('Auth.passwordConfirmRequired'), 403, 'confirmation_required');
            }
        }

        try {
            $name    = $body['name'] ?? null;
            $options = $this->manager()->startRegistration(auth()->user(), is_string($name) && $name !== '' ? $name : null);
        } catch (WebAuthnException $e) {
            return $this->error($e->getMessage(), 409, 'conflict');
        }

        return $this->response->setJSON($this->withToken($options));
    }

    public function registerVerify(): ResponseInterface
    {
        if (! $this->enabled()) {
            return $this->error(lang('Auth.webauthnDisabled'), 404, 'disabled');
        }
        if (! auth()->loggedIn()) {
            return $this->error(lang('Auth.notLoggedIn'), 403, 'forbidden');
        }
        if (($denied = $this->registrationGuard()) !== null) {
            return $denied;
        }

        $json = $this->credentialJson();
        if ($json === null) {
            return $this->error(lang('Auth.webauthnVerificationFailed'), 400, 'bad_request');
        }

        try {
            $entity = $this->manager()->finishRegistration(auth()->user(), $json);
        } catch (WebAuthnDuplicateCredentialException $e) {
            return $this->error($e->getMessage(), 409, 'conflict');
        } catch (WebAuthnException $e) {
            return $this->error($e->getMessage(), 422, 'unprocessable');
        }

        return $this->response->setStatusCode(201)->setJSON($this->withToken([
            'status'     => 'ok',
            'credential' => ['uuid' => $entity->uuid, 'name' => $entity->name],
        ]));
    }

    public function loginOptions(): ResponseInterface
    {
        if (! $this->enabled()) {
            return $this->error(lang('Auth.webauthnDisabled'), 404, 'disabled');
        }

        $body    = $this->request->is('json') ? $this->request->getJSON(true) : $this->request->getPost();
        $email   = $body['email'] ?? null;
        $options = $this->manager()->startLogin(is_string($email) && $email !== '' ? $email : null);

        return $this->response->setJSON($this->withToken($options));
    }

    public function loginVerify(): ResponseInterface
    {
        if (! $this->enabled()) {
            return $this->error(lang('Auth.webauthnDisabled'), 404, 'disabled');
        }

        $json = $this->credentialJson();
        if ($json === null) {
            return $this->error(lang('Auth.webauthnVerificationFailed'), 400, 'bad_request');
        }

        try {
            $user = $this->manager()->finishLogin($json);
        } catch (WebAuthnException $e) {
            return $this->error($e->getMessage(), 422, 'unprocessable');
        }

        // Password login refuses banned users in checkLogin(); login() does not.
        if ($user->isBanned()) {
            return $this->error($user->getBanMessage() ?? lang('Auth.bannedUser'), 403, 'banned');
        }

        // A verified passkey (with user verification) is multi-factor: complete
        // the session directly without re-running the 'login' Action pipeline.
        $assertion    = json_decode($json, true);
        $encoded      = $assertion['response']['authenticatorData'] ?? '';
        $bytes        = is_string($encoded) ? base64_decode(strtr($encoded, '-_', '+/'), true) : false;
        $userVerified = is_string($bytes) && strlen($bytes) > 32 && (ord($bytes[32]) & 0x04) !== 0;
        if (service('mfaPolicy')->requires($user) && ! $userVerified) {
            return $this->error(lang('Auth.mfaVerificationRequired'), 403, 'mfa_verification_required');
        }
        auth()->login($user, false);
        if ($userVerified) {
            service('mfaPolicy')->markVerified($user);
        }

        return $this->response->setJSON($this->withToken([
            'status'   => 'ok',
            'redirect' => config('Auth')->loginRedirect(),
        ]));
    }

    public function twoFactorOptions(): ResponseInterface
    {
        if (! $this->enabled()) {
            return $this->error(lang('Auth.webauthnDisabled'), 404, 'disabled');
        }

        $pending = auth()->getPendingUser();
        if ($pending === null) {
            return $this->error(lang('Auth.webauthnVerificationFailed'), 422, 'unprocessable');
        }

        return $this->response->setJSON($this->withToken($this->manager()->startTwoFactor($pending)));
    }

    public function deleteCredential(string $uuid): ResponseInterface
    {
        if (! $this->enabled()) {
            return $this->error(lang('Auth.webauthnDisabled'), 404, 'disabled');
        }
        if (! auth()->loggedIn()) {
            return $this->error(lang('Auth.notLoggedIn'), 403, 'forbidden');
        }

        $user = auth()->user();
        if (service('mfaPolicy')->requires($user) && ! $user->hasTotpEnabled() && count($user->webAuthnCredentials()) <= 1) {
            return $this->error(lang('Auth.mfaLastFactorRequired'), 409, 'last_factor');
        }

        $ok = $user->revokeWebAuthnCredential($uuid);

        return $this->response->setStatusCode($ok ? 200 : 404)->setJSON($this->withToken(['status' => $ok ? 'ok' : 'not_found']));
    }

    /**
     * Browser credential synchronization is restricted to the current account.
     */
    public function credentialSignals(): ResponseInterface
    {
        if (! $this->enabled()) {
            return $this->error(lang('Auth.webauthnDisabled'), 404, 'disabled');
        }
        if (! auth()->loggedIn()) {
            return $this->error(lang('Auth.notLoggedIn'), 403, 'forbidden');
        }

        return $this->response->setHeader('Cache-Control', 'no-store')->setJSON($this->manager()->credentialSignals(auth()->user()));
    }

    /**
     * Extracts the browser PublicKeyCredential JSON from the request body
     * (accepts a `credential` field or the raw body).
     */
    protected function credentialJson(): ?string
    {
        $body = $this->request->getJSON(true);
        if (is_array($body) && isset($body['credential'])) {
            return json_encode($body['credential'], JSON_THROW_ON_ERROR);
        }
        $posted = $this->request->getPost('credential');
        if (is_string($posted) && $posted !== '') {
            return $posted;
        }
        $raw = (string) $this->request->getBody();

        return $raw !== '' ? $raw : null;
    }
}
