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

/*
 * IPv4 network ranges can be specified as:
 * 1. Wildcard format:     1.2.3.*
 * 2. CIDR format:         1.2.3/24  OR  1.2.3.4/255.255.255.0
 * 3. Start-End IP format: 1.2.3.0-1.2.3.255
 *
 * IPv6 network ranges use CIDR notation (2001:db8::/32); a bare address
 * is treated as a /128.
 *
 * Every matcher fails closed: malformed addresses, ranges or prefixes never match.
 */

class CheckIpInRange
{
    /**
     * In order to simplify working with IP addresses (in binary) and their
     * netmasks, it is easier to ensure that the binary strings are padded
     * with zeros out to 32 characters - IP addresses are 32 bit numbers
     *
     * @codeCoverageIgnore
     *
     * @param mixed $dec
     */
    public static function decbin32($dec)
    {
        return str_pad(decbin($dec), 32, '0', STR_PAD_LEFT);
    }

    /**
     * This function takes 2 arguments, an IP address and a "range" in several
     * different formats.
     * Network ranges can be specified as:
     * 1. Wildcard format:     1.2.3.*
     * 2. CIDR format:         1.2.3/24  OR  1.2.3.4/255.255.255.0
     * 3. Start-End IP format: 1.2.3.0-1.2.3.255
     * The function will return true if the supplied IP is within the range.
     * It returns false when the IP, the range or the netmask is malformed.
     *
     * @param mixed $ip
     * @param mixed $range
     *
     * @return bool
     */
    public static function ipv4_in_range($ip, $range)
    {
        $ip_dec = ip2long((string) $ip);
        $range  = trim((string) $range);

        if ($ip_dec === false) {
            return false;
        }

        if (str_contains($range, '/')) {
            // $range is in IP/NETMASK format
            [$range, $netmask] = explode('/', $range, 2);
            if (str_contains($netmask, '.')) {
                // $netmask is a 255.255.0.0 format
                $netmask     = str_replace('*', '0', $netmask);
                $netmask_dec = ip2long($netmask);
                $range_dec   = ip2long($range);

                if ($netmask_dec === false || $range_dec === false) {
                    return false;
                }

                return ($ip_dec & $netmask_dec) === ($range_dec & $netmask_dec);
            }

            // $netmask is a CIDR size block
            if (! ctype_digit($netmask) || (int) $netmask > 32) {
                return false;
            }

            // fix the range argument (short form such as 1.2.3/24)
            $x = explode('.', $range);

            if (count($x) > 4) {
                return false;
            }

            while (count($x) < 4) {
                $x[] = '0';
            }

            foreach ($x as $octet) {
                if (! ctype_digit($octet) || (int) $octet > 255) {
                    return false;
                }
            }

            $range_dec = ip2long(implode('.', array_map(intval(...), $x)));

            if ($range_dec === false) {
                return false;
            }

            // Strategy 1 - Create the netmask with 'netmask' 1s and then fill it to 32 with 0s
            // $netmask_dec = bindec(str_pad('', $netmask, '1') . str_pad('', 32-$netmask, '0'));

            // Strategy 2 - Use math to create it
            $wildcard_dec = 2 ** (32 - (int) $netmask) - 1;
            $netmask_dec  = ~$wildcard_dec;

            return ($ip_dec & $netmask_dec) === ($range_dec & $netmask_dec);
        }
        // range might be 255.255.*.* or 1.2.3.0-1.2.3.255
        if (str_contains($range, '*')) { // a.b.*.* format
            // Just convert to A-B format by setting * to 0 for A and 255 for B
            $lower = str_replace('*', '0', $range);
            $upper = str_replace('*', '255', $range);
            $range = "{$lower}-{$upper}";
        }

        if (str_contains($range, '-')) { // A-B format
            [$lower, $upper] = explode('-', $range, 2);
            $lower_long      = ip2long(trim($lower));
            $upper_long      = ip2long(trim($upper));

            if ($lower_long === false || $upper_long === false) {
                return false;
            }

            $lower_dec = (float) sprintf('%u', $lower_long);
            $upper_dec = (float) sprintf('%u', $upper_long);
            $ip_float  = (float) sprintf('%u', $ip_dec);

            return ($ip_float >= $lower_dec) && ($ip_float <= $upper_dec);
        }

        return false;
    }

    /**
     * Determine whether an IPv6 address is within a CIDR range.
     *
     * $ip may be an IPv6 address in any textual notation, or the decimal
     * representation returned by get_ipv6_full() (kept for backward
     * compatibility). $range_ip is an IPv6 address with an optional
     * "/prefix" (0-128); without a prefix it must match exactly (/128).
     *
     * The comparison is done on the 128-bit binary form, so it is exact.
     *
     * @param mixed $ip
     * @param mixed $range_ip
     *
     * @return bool
     */
    public static function ipv6_in_range($ip, $range_ip)
    {
        $ipBinary = self::ipv6ToBinary(trim((string) $ip));

        if ($ipBinary === null) {
            return false;
        }

        $parts        = explode('/', trim((string) $range_ip), 2);
        $subnetBinary = self::textualIpv6ToBinary($parts[0]);

        if ($subnetBinary === null) {
            return false;
        }

        $prefix = 128;

        if (isset($parts[1])) {
            if (! ctype_digit($parts[1]) || (int) $parts[1] > 128) {
                return false;
            }

            $prefix = (int) $parts[1];
        }

        $mask = self::ipv6Mask($prefix);

        return ($ipBinary & $mask) === ($subnetBinary & $mask);
    }

    /**
     * Returns the IPv6 address as an exact base-10 integer string.
     *
     * A trailing "/prefix" is ignored. Returns an empty string when the
     * input is not a valid IPv6 address.
     *
     * @param mixed $ip
     *
     * @return string
     */
    public static function get_ipv6_full($ip)
    {
        $address = explode('/', trim((string) $ip), 2)[0];
        $binary  = self::textualIpv6ToBinary($address);

        if ($binary === null) {
            return '';
        }

        return self::binaryToDecimal($binary);
    }

    /**
     * Converts a textual IPv6 address or a base-10 integer string into its
     * 16-byte binary form, or null when it is neither.
     */
    private static function ipv6ToBinary(string $ip): ?string
    {
        $binary = self::textualIpv6ToBinary($ip);

        if ($binary !== null) {
            return $binary;
        }

        if ($ip !== '' && ctype_digit($ip)) {
            return self::decimalToBinary($ip);
        }

        return null;
    }

    /**
     * Converts a textual IPv6 address into its 16-byte binary form.
     */
    private static function textualIpv6ToBinary(string $ip): ?string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return null;
        }

        $binary = inet_pton($ip);

        return $binary === false ? null : $binary;
    }

    /**
     * Builds the 16-byte network mask for a prefix length (0-128).
     */
    private static function ipv6Mask(int $prefix): string
    {
        $fullBytes = intdiv($prefix, 8);
        $mask      = str_repeat("\xff", $fullBytes);

        if ($fullBytes < 16) {
            $mask .= chr((0xFF << (8 - $prefix % 8)) & 0xFF);
            $mask = str_pad($mask, 16, "\0");
        }

        return $mask;
    }

    /**
     * Converts a 16-byte binary string into an exact base-10 integer string
     * without bcmath/gmp (decimal = decimal * 256 + byte, digit by digit).
     */
    private static function binaryToDecimal(string $binary): string
    {
        $decimal = '0';

        foreach (str_split($binary) as $byte) {
            $carry  = ord($byte);
            $result = '';

            for ($i = strlen($decimal) - 1; $i >= 0; $i--) {
                $value  = (int) $decimal[$i] * 256 + $carry;
                $result = ($value % 10) . $result;
                $carry  = intdiv($value, 10);
            }

            while ($carry > 0) {
                $result = ($carry % 10) . $result;
                $carry  = intdiv($carry, 10);
            }

            $trimmed = ltrim($result, '0');
            $decimal = $trimmed === '' ? '0' : $trimmed;
        }

        return $decimal;
    }

    /**
     * Converts a base-10 integer string into its 16-byte binary form, or
     * null when it does not fit in 128 bits (repeated division by 256).
     */
    private static function decimalToBinary(string $decimal): ?string
    {
        $decimal = ltrim($decimal, '0');
        $bytes   = '';

        while ($decimal !== '') {
            $quotient  = '';
            $remainder = 0;

            for ($i = 0, $length = strlen($decimal); $i < $length; $i++) {
                $remainder = $remainder * 10 + (int) $decimal[$i];
                $digit     = intdiv($remainder, 256);
                $remainder %= 256;

                if ($quotient !== '' || $digit > 0) {
                    $quotient .= $digit;
                }
            }

            $bytes   = chr($remainder) . $bytes;
            $decimal = $quotient;

            if (strlen($bytes) > 16) {
                return null;
            }
        }

        return str_pad($bytes, 16, "\0", STR_PAD_LEFT);
    }
}
