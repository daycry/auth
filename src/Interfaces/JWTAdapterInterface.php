<?php

declare(strict_types=1);

namespace Daycry\Auth\Interfaces;

interface JWTAdapterInterface
{
    /**
     * Issues Signed JWT
     *
     * @param mixed               $payload The payload.
     * @return string
     */
    public function encode(mixed $payload): string;

    /**
     * Decode Signed JWT (JWS)
     *
     * @param string
     *
     * @return mixed
     */
    public function decode(string $encodedToken): mixed;
}