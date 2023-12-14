<?php

declare(strict_types=1);

namespace Daycry\Auth\Libraries;

use CodeIgniter\HTTP\IncomingRequest as BaseIncomingRequest;
use Config\App;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use Daycry\Auth\Libraries\InputFormat;

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
            function ($header) {
                return $header->getValueLine();
            },
            $this->headers()
        );
    }

    public function getAllParams(): array
    {
        $content = [];
        if ($this->is('json')) {
            $content = $this->getJSON();
        } else if( $this->is('put') || $this->is('patch') || $this->is('delete') ) {
            // @codeCoverageIgnoreStart
            $content = $this->getRawInput();
            // @codeCoverageIgnoreEnd
        }

        return array_merge($this->getCookie(), $this->getGetPost(), $this->getParsedHeaders(), ['body' => $content]);
    }
}
