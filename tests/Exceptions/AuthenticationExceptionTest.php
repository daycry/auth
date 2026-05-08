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

use CodeIgniter\HTTP\Exceptions\HTTPException;
use Daycry\Auth\Exceptions\AuthenticationException;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class AuthenticationExceptionTest extends TestCase
{
    public function testForUnknownAuthenticatorReturns401WithMessage(): void
    {
        $e = AuthenticationException::forUnknownAuthenticator('foo');

        $this->assertSame(401, $e->getCode());
        $this->assertTrue($e->authorized);
        $this->assertNotSame('', $e->getMessage());
    }

    public function testForInvalidLibraryImplementationReturns401(): void
    {
        $e = AuthenticationException::forInvalidLibraryImplementation();

        $this->assertSame(401, $e->getCode());
        $this->assertTrue($e->authorized);
    }

    public function testForUnknownUserProviderReturns401(): void
    {
        $e = AuthenticationException::forUnknownUserProvider();

        $this->assertSame(401, $e->getCode());
    }

    public function testForInvalidUserMarksUnauthorized(): void
    {
        $e = AuthenticationException::forInvalidUser();

        $this->assertFalse($e->authorized);
    }

    public function testForBannedUserMarksUnauthorized(): void
    {
        $e = AuthenticationException::forBannedUser();

        $this->assertFalse($e->authorized);
    }

    public function testForNoEntityProvidedReturns500(): void
    {
        $e = AuthenticationException::forNoEntityProvided();

        $this->assertSame(500, $e->getCode());
    }

    public function testForUnsetPasswordLengthReturns500(): void
    {
        $e = AuthenticationException::forUnsetPasswordLength();

        $this->assertSame(500, $e->getCode());
    }

    public function testForHIBPCurlFailWrapsHttpException(): void
    {
        $http = HTTPException::forCurlError('28', 'upstream down');
        $e    = AuthenticationException::forHIBPCurlFail($http);

        $this->assertNotSame('', $e->getMessage());
        $this->assertSame($http, $e->getPrevious());
    }
}
