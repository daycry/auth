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

namespace Daycry\Auth\Interfaces;

interface JWTAdapterInterface
{
    /**
     * Issues Signed JWT
     *
     * @param mixed $payload The payload.
     */
    public function encode(mixed $payload): string;

    /**
     * Decode Signed JWT (JWS)
     *
     * @param string
     */
    public function decode(string $encodedToken): mixed;
}
