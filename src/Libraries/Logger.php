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

namespace Daycry\Auth\Libraries;

use CodeIgniter\Debug\Timer;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Daycry\Auth\Entities\Endpoint;
use Daycry\Auth\Models\LogModel;

class Logger
{
    protected LogModel $logModel;
    protected IncomingRequest $request;
    protected ResponseInterface $response;
    protected bool $logAuthorized = false;
    protected bool $authorized    = true;
    protected Timer $benchmark;
    protected int $responseCode = 0;
    protected int $insertId     = 0;

    public function __construct(?Endpoint $endpoint)
    {
        $this->benchmark = Services::timer();
        $this->benchmark->start('auth');

        $this->logModel = new LogModel();
        $this->request  = Services::request();
        $this->response = Services::response();

        if (
            (null === $endpoint && service('settings')->get('Auth.enableLogs') === true)
            || (service('settings')->get('Auth.enableLogs') === true && (null !== $endpoint && null === $endpoint->log))
            || (null !== $endpoint && $endpoint->log)
        ) {
            $this->logAuthorized = true;
        }
    }

    public function setLogAuthorized(bool $logAuthorized): self
    {
        $this->logAuthorized = $logAuthorized;

        return $this;
    }

    public function setAuthorized(bool $authorized): self
    {
        $this->authorized = $authorized;

        return $this;
    }

    public function setResponseCode(int $responseCode): self
    {
        $this->responseCode = $responseCode;

        return $this;
    }

    public function save(): int
    {
        if ($this->logAuthorized) {
            $params = Utils::getAllParams();

            $this->response = Services::response();

            $this->benchmark->stop('auth');

            $data = [
                'user_id'       => auth()->id() ?? null,
                'uri'           => $this->request->getUri()->__toString(),
                'method'        => $this->request->getMethod(),
                'params'        => serialize($params),
                'ip_address'    => $this->request->getIPAddress(),
                'duration'      => $this->benchmark->getElapsedTime('auth'),
                'response_code' => $this->responseCode,
                'authorized'    => $this->authorized,
            ];

            $this->logModel->save($data);
            $this->insertId = $this->logModel->getInsertID();
        }

        return $this->insertId;
    }
}
