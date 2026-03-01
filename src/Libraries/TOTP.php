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

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use InvalidArgumentException;

/**
 * RFC 6238 TOTP (Time-based One-Time Password) implementation.
 *
 * Pure PHP, no external dependencies.
 * Compatible with Google Authenticator, Authy, and any RFC 6238 app.
 */
class TOTP
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Number of digits in the generated code.
     */
    private const DIGITS = 6;

    /**
     * Time step in seconds (standard: 30).
     */
    private const PERIOD = 30;

    /**
     * Generates a cryptographically random base32-encoded TOTP secret.
     *
     * @param int $length Number of bytes of random data (default 20 = 160-bit secret)
     */
    public static function generateSecret(int $length = 20): string
    {
        return self::base32Encode(random_bytes($length));
    }

    /**
     * Generates the current TOTP code for the given secret.
     *
     * @param string   $secret    Base32-encoded TOTP secret
     * @param int|null $timestamp Unix timestamp (null = now)
     */
    public static function getCode(string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $timeStep = (int) floor($timestamp / self::PERIOD);

        return self::computeCode($secret, $timeStep);
    }

    /**
     * Verifies a TOTP code against the given secret.
     * Accepts codes within ±$window time steps (default: ±1, i.e. 90-second window).
     *
     * @param string $secret    Base32-encoded TOTP secret
     * @param string $code      6-digit code to verify
     * @param int    $window    Number of adjacent time steps to accept
     * @param int    $timestamp Unix timestamp (useful for testing)
     */
    public static function verify(string $secret, string $code, int $window = 1, ?int $timestamp = null): bool
    {
        $timestamp ??= time();
        $timeStep = (int) floor($timestamp / self::PERIOD);

        for ($i = -$window; $i <= $window; $i++) {
            $expected = self::computeCode($secret, $timeStep + $i);

            if (hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Builds the `otpauth://` URI that can be encoded as a QR code
     * for scanning with Google Authenticator or any compatible app.
     *
     * @param string $secret  Base32-encoded TOTP secret
     * @param string $account User's email or username
     * @param string $issuer  Application / service name shown in the app
     */
    public static function getOtpAuthUrl(string $secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode($account);

        return 'otpauth://totp/' . $label
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1'
            . '&digits=' . self::DIGITS
            . '&period=' . self::PERIOD;
    }

    /**
     * Returns a base64 data URI (data:image/png;base64,...) containing the QR
     * code for the given otpauth URI, generated locally via endroid/qr-code.
     * The result can be used directly as the `src` of an `<img>` tag.
     */
    public static function getQRCodeUrl(string $otpAuthUrl, int $size = 200): string
    {
        $qrCode = new QrCode($otpAuthUrl, size: $size);

        $writer = new PngWriter();
        $result = $writer->write($qrCode);

        return $result->getDataUri();
    }

    /**
     * Computes the TOTP code for a specific time step counter.
     */
    private static function computeCode(string $secret, int $counter): string
    {
        $key     = self::base32Decode(strtoupper($secret));
        $message = pack('J', $counter); // 8-byte big-endian counter

        $hash   = hash_hmac('sha1', $message, $key, true);
        $offset = ord($hash[19]) & 0x0F;

        $code = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);

        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Encodes binary data as a base32 string (RFC 4648, no padding).
     */
    public static function base32Encode(string $data): string
    {
        $alphabet = self::BASE32_ALPHABET;
        $result   = '';
        $bits     = 0;
        $buffer   = 0;

        foreach (str_split($data) as $char) {
            $buffer = ($buffer << 8) | ord($char);
            $bits += 8;

            while ($bits >= 5) {
                $bits -= 5;
                $result .= $alphabet[($buffer >> $bits) & 0x1F];
            }
        }

        if ($bits > 0) {
            $result .= $alphabet[($buffer << (5 - $bits)) & 0x1F];
        }

        return $result;
    }

    /**
     * Decodes a base32 string (RFC 4648) into binary data.
     *
     * @throws InvalidArgumentException on invalid base32 characters
     */
    public static function base32Decode(string $base32): string
    {
        $alphabet = self::BASE32_ALPHABET;
        $base32   = strtoupper(rtrim($base32, '='));
        $result   = '';
        $bits     = 0;
        $buffer   = 0;

        foreach (str_split($base32) as $char) {
            $pos = strpos($alphabet, $char);

            if ($pos === false) {
                throw new InvalidArgumentException('Invalid base32 character: ' . $char);
            }

            $buffer = ($buffer << 5) | $pos;
            $bits += 5;

            if ($bits >= 8) {
                $bits -= 8;
                $result .= chr(($buffer >> $bits) & 0xFF);
            }
        }

        return $result;
    }
}
