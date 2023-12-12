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

namespace Daycry\Auth\Entities\Cast;

use CodeIgniter\Entity\Cast\BaseCast;

/**
 * Serialize Cast
 */
final class SerializeCast extends BaseCast
{
    /**
     * @param string $value
     */
    public static function get($value, array $params = []): ?array
    {
        if ($value) {
            return unserialize($value);
        }

        return null;
    }

    /**
     * @param bool|int|string $value
     */
    public static function set($value, array $params = []): ?string
    {
        if ($value) {
            return serialize($value);
        }

        return null;
    }
}
