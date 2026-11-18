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

namespace Daycry\Auth\Traits;

/**
 * `beforeInsert` callback giving new rows a UUID v7 in their `uuid` column
 * (users, device sessions, WebAuthn credentials). Register it with
 * `protected $beforeInsert = ['generateUuid'];`.
 */
trait GeneratesUuid
{
    /**
     * Generates a UUID v7 unless the row already carries one.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function generateUuid(array $data): array
    {
        if (empty($data['data']['uuid'])) {
            $data['data']['uuid'] = service('uuid')->uuid7()->toRfc4122();
        }

        return $data;
    }
}
