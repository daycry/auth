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

use Config\Services;

class Utils
{
    /**
     * Generates a random numeric code of the given length.
     * Each digit is in the range 1-9 (no zeros) to avoid leading-zero ambiguity.
     *
     * @param positive-int $length Number of digits (default 6)
     */
    public static function generateNumericCode(int $length = 6): string
    {
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $result .= (string) random_int(1, 9);
        }

        return $result;
    }

    public static function getParsedHeaders(): array
    {
        $request = Services::request();

        return array_map(
            static fn ($header) => $header->getValueLine(),
            $request->headers(),
        );
    }

    public static function getAllParams(): array
    {
        $request = Services::request();

        $content = [];
        if ($request->is('json')) {
            $content = $request->getJSON();
        } elseif ($request->is('put') || $request->is('patch') || $request->is('delete')) {
            // @codeCoverageIgnoreStart
            $content = $request->getRawInput();
            // @codeCoverageIgnoreEnd
        }

        return array_merge($request->getCookie(), $request->getGetPost(), self::getParsedHeaders(), ['body' => $content]);
    }
}
