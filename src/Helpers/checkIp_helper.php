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

use Daycry\Auth\Libraries\CheckIpInRange;

if (! function_exists('checkIp')) {
    /**
     * Checks whether $ip matches any entry of an allow-list.
     *
     * IPv4 entries may be exact addresses, wildcards (1.2.3.*), CIDR
     * (1.2.3.0/24, 1.2.3.0/255.255.255.0) or start-end ranges. Entries
     * containing ':' are IPv6: a bare address (matched as /128, notation
     * independent) or CIDR (2001:db8::/32). Malformed input never matches.
     *
     * @param string                   $ip  IP
     * @param array<array-key, string> $ips Ips
     */
    function checkIp(string $ip, array $ips): bool
    {
        $isIpv6 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;

        foreach ($ips as $i) {
            $entry = trim((string) $i);

            if ($entry === '') {
                continue;
            }

            if (str_contains($entry, ':')) {
                // IPv6 entry: only an IPv6 client can match it.
                if ($isIpv6 && CheckIpInRange::ipv6_in_range($ip, $entry)) {
                    return true;
                }

                continue;
            }

            if (str_contains($entry, '/') || str_contains($entry, '-') || str_contains($entry, '*')) {
                if (CheckIpInRange::ipv4_in_range($ip, $entry)) {
                    return true;
                }

                continue;
            }

            if ($ip === $entry) {
                return true;
            }
        }

        return false;
    }
}
