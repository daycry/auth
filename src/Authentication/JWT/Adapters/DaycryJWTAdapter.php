<?php

declare(strict_types=1);

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
        $token = $jwt->encode($payload);

        return $token;
    }
}