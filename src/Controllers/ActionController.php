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
use CodeIgniter\HTTP\Response;
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
     * @return Response|string
     */
    public function _remap(string $method, ...$params)
    {
        $authenticator = $this->getSessionAuthenticator();

        // Grab our action instance if one has been set.
        $this->action = $authenticator->getAction();

        if (empty($this->action) || ! $this->action instanceof ActionInterface) {
            throw new PageNotFoundException();
        }

        return $this->{$method}(...$params);
    }

    /**
     * Shows the initial screen to the user to start the flow.
     * This might be asking for the user's email to reset a password,
     * or asking for a cell-number for a 2FA.
     *
     * @return Response|string
     */
    public function show()
    {
        return $this->action->show();
    }

    /**
     * Processes the form that was displayed in the previous form.
     *
     * @return Response|string
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
     * @return Response|string
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
