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

namespace Tests\Database;

use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class WebAuthnCredentialsMigrationTest extends DatabaseTestCase
{
    public function testTableExistsWithColumns(): void
    {
        $table = config('Auth')->tables['webauthn_credentials'];

        $this->assertTrue($this->db->tableExists($table));

        $fields = $this->db->getFieldNames($table);

        foreach (['id', 'uuid', 'user_id', 'credential_id', 'credential', 'user_handle', 'name', 'sign_count', 'transports', 'aaguid', 'last_used_at', 'created_at', 'updated_at', 'revoked_at'] as $column) {
            $this->assertContains($column, $fields, "missing column {$column}");
        }
    }

    public function testCredentialIdIsUnique(): void
    {
        $table   = config('Auth')->tables['webauthn_credentials'];
        $indexes = $this->db->getIndexData($table);

        $hasUnique = false;

        foreach ($indexes as $index) {
            if (in_array('credential_id', (array) $index->fields, true) && $index->type === 'UNIQUE') {
                $hasUnique = true;
            }
        }
        $this->assertTrue($hasUnique, 'credential_id must have a UNIQUE index');
    }
}
