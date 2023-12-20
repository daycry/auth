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
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;

class DaycryJWTAdapter implements JWTAdapterInterface
{
    /**
     * {@inheritDoc}
     */
    public function decode(string $encodedToken): mixed
    {
        try {
            $jwt = new JWT();
            $jwt->setSplitData(false)->setParamData('data');
            $token = $jwt->decode($encodedToken);

            return $token->get($jwt->getParamData());
        } catch (RequiredConstraintsViolated $e) {
            throw InvalidJWTException::forInvalidToken($e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function encode(mixed $payload): string
    {
        $jwt = new JWT();
        $jwt->setSplitData(false)->setParamData('data');

        return $jwt->encode($payload);
    }
}
