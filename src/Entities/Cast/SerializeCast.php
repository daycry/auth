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
 *
 * Stores values as JSON. Provides backward-compatible reading of
 * legacy PHP-serialized data (read-only fallback, never writes serialized).
 */
final class SerializeCast extends BaseCast
{
    /**
     * @param string $value
     */
    public static function get($value, array $params = []): ?array
    {
        if (! $value) {
            return null;
        }

        // Primary: try JSON (new format)
        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Fallback: legacy PHP-serialized data — allowed_classes=false prevents object injection
        $unserialized = unserialize($value, ['allowed_classes' => false]);

        return is_array($unserialized) ? $unserialized : null;
    }

    /**
     * @param bool|int|list<mixed>|string $value
     */
    public static function set($value, array $params = []): ?string
    {
        if (! $value) {
            return null;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
