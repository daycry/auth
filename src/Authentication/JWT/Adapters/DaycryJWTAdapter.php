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

namespace Daycry\Auth\Authentication\JWT\Adapters;

use Daycry\Auth\Exceptions\InvalidJWTException;
use Daycry\Auth\Interfaces\JWTAdapterInterface;
use Daycry\JWT\JWT;
use Throwable;

class DaycryJWTAdapter implements JWTAdapterInterface
{
    /**
     * {@inheritDoc}
     */
    public function decode(string $encodedToken): mixed
    {
        try {
            // daycry/jwt v3 defaults to split=false / paramData='data', and
            // getPayload() round-trips JSON for compact tokens (cty=json).
            return JWT::for(config('JWT'))->getPayload($encodedToken);
        } catch (Throwable $e) {
            throw InvalidJWTException::forInvalidToken($e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function encode(mixed $payload): string
    {
        return JWT::for(config('JWT'))->encode($payload);
    }
}
