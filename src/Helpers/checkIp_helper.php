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
     * Provides a valid IP for actual request
     *
     * @param string $ip  IP
     * @param array  $ips Ips
     */
    function checkIp(string $ip, array $ips): bool
    {
        $return = false;

        foreach ($ips as $i) {
            if (strpos($i, '/') !== false || strpos($i, '-') !== false || strpos($i, '*') !== false) {
                $return = CheckIpInRange::ipv4_in_range($ip, $i);
            } elseif ($ip === trim($i)) {
                $return = true;
            }

            if ($return) {
                break;
            }
        }

        return $return;
    }
}
