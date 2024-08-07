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
 * Network ranges can be specified as:
 * 1. Wildcard format:     1.2.3.*
 * 2. CIDR format:         1.2.3/24  OR  1.2.3.4/255.255.255.0
 * 3. Start-End IP format: 1.2.3.0-1.2.3.255
 *
 * Return value BOOLEAN : ip_in_range($ip, $range);
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
     * Note little validation is done on the range inputs - it expects you to
     *
     * @param mixed $ip
     * @param mixed $range
     */
    public static function ipv4_in_range($ip, $range)
    {
        if (str_contains($range, '/')) {
            // $range is in IP/NETMASK format
            [$range, $netmask] = explode('/', $range, 2);
            if (str_contains($netmask, '.')) {
                // $netmask is a 255.255.0.0 format
                $netmask     = str_replace('*', '0', $netmask);
                $netmask_dec = ip2long($netmask);

                return (ip2long($ip) & $netmask_dec) === (ip2long($range) & $netmask_dec);
            }
            // $netmask is a CIDR size block
            // fix the range argument
            $x = explode('.', $range);

            while (count($x) < 4) {
                $x[] = '0';
            }
            [$a, $b, $c, $d] = $x;
            $range           = sprintf('%u.%u.%u.%u', $a === '' || $a === '0' ? '0' : $a, $b === '' || $b === '0' ? '0' : $b, $c === '' || $c === '0' ? '0' : $c, $d === '' || $d === '0' ? '0' : $d);
            $range_dec       = ip2long($range);
            $ip_dec          = ip2long($ip);

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
            $lower_dec       = (float) sprintf('%u', ip2long($lower));
            $upper_dec       = (float) sprintf('%u', ip2long($upper));
            $ip_dec          = (float) sprintf('%u', ip2long($ip));

            return ($ip_dec >= $lower_dec) && ($ip_dec <= $upper_dec);
        }

        return false;
    }

    /**
     * Determine whether the IPV6 address is within range.
     * $ip is the IPV6 address in decimal format to check if its within the IP range created by the cloudflare IPV6 address, $range_ip.
     * $ip and $range_ip are converted to full IPV6 format.
     * Returns true if the IPV6 address, $ip,  is within the range from $range_ip.  False otherwise.
     *
     * @codeCoverageIgnore
     *
     * @param mixed $ip
     * @param mixed $range_ip
     */
    public static function ipv6_in_range($ip, $range_ip)
    {
        $pieces     = explode('/', $range_ip, 2);
        $left_piece = $pieces[0];

        // Extract out the main IP pieces
        $ip_pieces     = explode('::', $left_piece, 2);
        $main_ip_piece = $ip_pieces[0];
        $last_ip_piece = $ip_pieces[1];

        // Pad out the shorthand entries.
        $main_ip_pieces = explode(':', $main_ip_piece);

        foreach (array_keys($main_ip_pieces) as $key) {
            $main_ip_pieces[$key] = str_pad($main_ip_pieces[$key], 4, '0', STR_PAD_LEFT);
        }

        // Create the first and last pieces that will denote the IPV6 range.
        $first = $main_ip_pieces;
        $last  = $main_ip_pieces;

        // Check to see if the last IP block (part after ::) is set
        $last_piece = '';
        $size       = count($main_ip_pieces);
        if (trim($last_ip_piece) !== '') {
            $last_piece = str_pad($last_ip_piece, 4, '0', STR_PAD_LEFT);

            // Build the full form of the IPV6 address considering the last IP block set
            for ($i = $size; $i < 7; $i++) {
                $first[$i] = '0000';
                $last[$i]  = 'ffff';
            }
            $main_ip_pieces[7] = $last_piece;
        } else {
            // Build the full form of the IPV6 address
            for ($i = $size; $i < 8; $i++) {
                $first[$i] = '0000';
                $last[$i]  = 'ffff';
            }
        }

        // Rebuild the final long form IPV6 address
        $first = self::ip2long6(implode(':', $first));
        $last  = self::ip2long6(implode(':', $last));

        return $ip >= $first && $ip <= $last;
    }

    /**
     * @codeCoverageIgnore
     *
     * @param mixed $ip
     */
    private static function ip2long6($ip)
    {
        if (substr_count($ip, '::') !== 0) {
            $ip = str_replace('::', str_repeat(':0000', 8 - substr_count($ip, ':')) . ':', $ip);
        }

        $ip   = explode(':', $ip);
        $r_ip = '';

        foreach ($ip as $v) {
            $r_ip .= str_pad(base_convert($v, 16, 2), 16, '0', STR_PAD_LEFT);
        }

        return base_convert($r_ip, 2, 10);
    }

    /**
     * @codeCoverageIgnore
     *
     * @param mixed $ip
     */
    // Get the ipv6 full format and return it as a decimal value.
    public static function get_ipv6_full($ip)
    {
        $pieces     = explode('/', $ip, 2);
        $left_piece = $pieces[0];

        // Extract out the main IP pieces
        $ip_pieces     = explode('::', $left_piece, 2);
        $main_ip_piece = $ip_pieces[0];
        $last_ip_piece = $ip_pieces[1];

        // Pad out the shorthand entries.
        $main_ip_pieces = explode(':', $main_ip_piece);

        foreach (array_keys($main_ip_pieces) as $key) {
            $main_ip_pieces[$key] = str_pad($main_ip_pieces[$key], 4, '0', STR_PAD_LEFT);
        }

        // Check to see if the last IP block (part after ::) is set
        $last_piece = '';
        $size       = count($main_ip_pieces);
        if (trim($last_ip_piece) !== '') {
            $last_piece = str_pad($last_ip_piece, 4, '0', STR_PAD_LEFT);

            // Build the full form of the IPV6 address considering the last IP block set
            for ($i = $size; $i < 7; $i++) {
                $main_ip_pieces[$i] = '0000';
            }
            $main_ip_pieces[7] = $last_piece;
        } else {
            // Build the full form of the IPV6 address
            for ($i = $size; $i < 8; $i++) {
                $main_ip_pieces[$i] = '0000';
            }
        }

        // Rebuild the final long form IPV6 address
        $final_ip = implode(':', $main_ip_pieces);

        return self::ip2long6($final_ip);
    }
}
