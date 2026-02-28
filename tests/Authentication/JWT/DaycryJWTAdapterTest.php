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

namespace Tests\Authentication\JWT;

use Daycry\Auth\Authentication\JWT\Adapters\DaycryJWTAdapter;
use Daycry\Auth\Exceptions\InvalidJWTException;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class DaycryJWTAdapterTest extends TestCase
{
    private DaycryJWTAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new DaycryJWTAdapter();
    }

    public function testEncodeReturnsString(): void
    {
        $token = $this->adapter->encode(42);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    public function testDecodeRoundTripInteger(): void
    {
        $token   = $this->adapter->encode(123);
        $decoded = $this->adapter->decode($token);

        $this->assertSame(123, $decoded);
    }

    public function testDecodeRoundTripString(): void
    {
        $token   = $this->adapter->encode('hello');
        $decoded = $this->adapter->decode($token);

        $this->assertSame('hello', $decoded);
    }

    public function testDecodeRoundTripNull(): void
    {
        $token   = $this->adapter->encode(null);
        $decoded = $this->adapter->decode($token);

        $this->assertNull($decoded);
    }

    public function testDecodeExpiredTokenThrowsInvalidJWTException(): void
    {
        // BAD_JWT from the JWTAuthenticatorTest — an expired token
        $expiredToken = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9'
            . '.eyJpc3MiOiJJc3N1ZXIgb2YgdGhlIEpXVCIsImF1ZCI6IkF1ZGllbmNlIG9mIHRoZSBKV1QiLCJzdWIiOiIxIiwiaWF0IjoxNjUzOTkxOTg5LCJleHAiOjE2NTM5OTU1ODl9'
            . '.hgOYHEcT6RGHb3po1lspTcmjrylY1Cy1IvYmHOyx0CY';

        $this->expectException(InvalidJWTException::class);

        $this->adapter->decode($expiredToken);
    }

    public function testDecodeMalformedTokenThrowsInvalidJWTException(): void
    {
        $this->expectException(InvalidJWTException::class);

        $this->adapter->decode('this-is-not-a-jwt');
    }

    public function testDecodeEmptyStringThrowsInvalidJWTException(): void
    {
        $this->expectException(InvalidJWTException::class);

        $this->adapter->decode('');
    }

    public function testDecodeTamperedPayloadThrowsInvalidJWTException(): void
    {
        // Structurally valid but with tampered payload / invalid signature
        $tampered = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9'
            . '.eyJkYXRhIjoiOTk5OTkifQ'
            . '.invalidsignatureXXXXXXXXXXXXXXXXX';

        $this->expectException(InvalidJWTException::class);

        $this->adapter->decode($tampered);
    }
}
