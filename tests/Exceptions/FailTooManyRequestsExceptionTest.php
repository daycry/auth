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

namespace Tests\Exceptions;

use Daycry\Auth\Exceptions\FailTooManyRequestsException;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class FailTooManyRequestsExceptionTest extends TestCase
{
    public function testForApiKeyLimitReturns429AndMarksUnauthorized(): void
    {
        $e = FailTooManyRequestsException::forApiKeyLimit('abc-123');

        $this->assertSame(429, $e->getCode());
        $this->assertFalse(FailTooManyRequestsException::$authorized);
    }

    public function testForInvalidAttemptsLimitReturns429(): void
    {
        $e = FailTooManyRequestsException::forInvalidAttemptsLimit();

        $this->assertSame(429, $e->getCode());
        $this->assertFalse(FailTooManyRequestsException::$authorized);
    }

    public function testForIpAddressTimeLimitReturns429(): void
    {
        $e = FailTooManyRequestsException::forIpAddressTimeLimit();

        $this->assertSame(429, $e->getCode());
        $this->assertFalse(FailTooManyRequestsException::$authorized);
    }
}
