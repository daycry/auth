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

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Daycry\Auth\Interfaces\ActionInterface;

/**
 * Class ActionController
 *
 * A generic controller to handle Authentication Actions.
 */
class ActionController extends BaseAuthController
{
    protected ?ActionInterface $action = null;

    /**
     * Perform an initial check if we have a valid action or not.
     *
     * @param list<string> $params
     *
     * @return ResponseInterface|string
     */
    public function _remap(string $method, ...$params)
    {
        $authenticator = $this->getSessionAuthenticator();

        // Grab our action instance if one has been set.
        $this->action = $authenticator->getAction();

        if (! $this->action instanceof ActionInterface || ! $this->isRoutableMethod($method)) {
            throw new PageNotFoundException();
        }

        if ($this->invalidJsonBody) {
            return $this->authJsonResponse(['status' => 'error', 'message' => 'The request body must be a valid JSON object.'], 400);
        }

        if ($this->request->is('json')) {
            // Actions share the IncomingRequest contract. Present the parsed
            // body to existing/custom actions without mixing query parameters.
            $this->request->setGlobal('post', array_map(
                static fn ($value) => is_scalar($value) ? $value : null,
                $this->authRequestData(),
            ));
        }

        $result = $this->{$method}(...$params);
        if (! $this->expectsJsonResponse()) {
            return $result;
        }

        if ($authenticator->loggedIn()) {
            if (($pending = $this->pendingMfaResponse()) !== null) {
                return $pending;
            }

            return $this->authJsonResponse([
                'status'        => 'success',
                'authenticated' => true,
                'redirect'      => $result instanceof RedirectResponse ? $result->getHeaderLine('Location') : config('Auth')->loginRedirect(),
            ]);
        }

        $error = session()->getFlashdata('error');

        return $this->authJsonResponse([
            'status'        => 'action_required',
            'authenticated' => false,
            'message'       => is_string($error) ? $error : $authenticator->getPendingMessage(),
            'redirect'      => url_to('auth-action-show'),
        ], $method === 'verify' || $error !== null ? 422 : 202);
    }

    /**
     * Shows the initial screen to the user to start the flow.
     * This might be asking for the user's email to reset a password,
     * or asking for a cell-number for a 2FA.
     *
     * @return ResponseInterface|string
     */
    public function show()
    {
        return $this->action->show();
    }

    /**
     * Processes the form that was displayed in the previous form.
     *
     * @return ResponseInterface|string
     */
    public function handle()
    {
        return $this->action->handle($this->request);
    }

    /**
     * This handles the response after the user takes action
     * in response to the show/handle flow. This might be
     * from clicking the 'confirm my email' action or
     * following entering a code sent in an SMS.
     *
     * @return ResponseInterface|string
     */
    public function verify()
    {
        return $this->action->verify($this->request);
    }

    /**
     * ActionController doesn't use validation rules directly
     * as it delegates to action objects
     */
    protected function getValidationRules(): array
    {
        return [];
    }
}
