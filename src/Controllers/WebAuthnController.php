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
use Daycry\Auth\Exceptions\WebAuthnDuplicateCredentialException;
use Daycry\Auth\Exceptions\WebAuthnException;
use Daycry\Auth\Libraries\WebAuthn\WebAuthnManager;

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

    private function enabled(): bool
    {
        return (bool) (setting('AuthSecurity.webauthnEnabled') ?? false);
    }

    private function manager(): WebAuthnManager
    {
        return service('webAuthnManager');
    }

    private function error(string $message, int $status, string $code = 'error'): ResponseInterface
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
    private function withToken(array $payload): array
    {
        $payload['token'] = csrf_hash();

        return $payload;
    }

    public function registerOptions(): ResponseInterface
    {
        if (! $this->enabled()) {
            return $this->error(lang('Auth.webauthnDisabled'), 404, 'disabled');
        }
        if (! auth()->loggedIn()) {
            return $this->error(lang('Auth.notLoggedIn'), 403, 'forbidden');
        }

        try {
            $options = $this->manager()->startRegistration(auth()->user(), $this->request->getPost('name') ?: null);
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

        $options = $this->manager()->startLogin($this->request->getPost('email') ?: null);

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

        // A verified passkey (with user verification) is multi-factor: complete
        // the session directly without re-running the 'login' Action pipeline.
        auth()->login($user, false);

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

        $ok = auth()->user()->revokeWebAuthnCredential($uuid);

        return $this->response->setStatusCode($ok ? 200 : 404)->setJSON($this->withToken(['status' => $ok ? 'ok' : 'not_found']));
    }

    /**
     * Extracts the browser PublicKeyCredential JSON from the request body
     * (accepts a `credential` field or the raw body).
     */
    private function credentialJson(): ?string
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
