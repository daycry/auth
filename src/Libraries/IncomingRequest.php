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

use CodeIgniter\HTTP\IncomingRequest as BaseIncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use Config\App;

class IncomingRequest extends BaseIncomingRequest
{
    /**
     * Constructor
     *
     * @param App         $config
     * @param string|null $body
     */
    public function __construct($config, ?URI $uri = null, $body = 'php://input', ?UserAgent $userAgent = null)
    {
        parent::__construct($config, $uri, $body, $userAgent);
    }

    public function getParsedHeaders()
    {
        return array_map(
            static fn ($header) => $header->getValueLine(),
            $this->headers()
        );
    }

    public function getAllParams(): array
    {
        $content = [];
        if ($this->is('json')) {
            $content = $this->getJSON();
        } elseif ($this->is('put') || $this->is('patch') || $this->is('delete')) {
            // @codeCoverageIgnoreStart
            $content = $this->getRawInput();
            // @codeCoverageIgnoreEnd
        }

        return array_merge($this->getCookie(), $this->getGetPost(), $this->getParsedHeaders(), ['body' => $content]);
    }
}
