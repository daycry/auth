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

namespace Daycry\Auth\Database\Migrations;

use CodeIgniter\Database\Forge;
use CodeIgniter\Database\Migration;
use Daycry\Auth\Config\Auth;
use Throwable;

/**
 * Adds a composite index on (user_id, type, revoked_at) to auth_identities.
 *
 * Supports the most common per-user identity lookups:
 *   - listing a user's active access tokens / JWT refresh tokens
 *   - resolving an OAuth identity for a user + provider
 *   - filtering out soft-revoked rows efficiently
 *
 * The existing UNIQUE(type, secret) is unaffected — it covers raw-token
 * lookups in O(1). This composite index covers the per-user query shape.
 */
class AddIdentitiesUserTypeRevokedIndex extends Migration
{
    private readonly string $table;

    public function __construct(?Forge $forge = null)
    {
        /** @var Auth $authConfig */
        $authConfig = config('Auth');

        if ($authConfig->DBGroup !== null) {
            $this->DBGroup = $authConfig->DBGroup;
        }

        parent::__construct($forge);

        $this->table = $authConfig->tables['identities'];
    }

    public function up(): void
    {
        $this->forge->addKey(['user_id', 'type', 'revoked_at']);
        $this->forge->processIndexes($this->table);
    }

    public function down(): void
    {
        $indexName = $this->table . '_user_id_type_revoked_at';

        try {
            $this->forge->dropKey($this->table, $indexName);
        } catch (Throwable) {
            // Index may not exist if up() was never run — safe to ignore.
        }
    }
}
