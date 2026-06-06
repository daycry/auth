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

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Forge;
use CodeIgniter\Database\Migration;
use Config\Database;
use Daycry\Auth\Config\Auth;
use Throwable;

/**
 * Adds supporting indexes for device-session and login lookups.
 *
 * Affected tables:
 *  - auth_device_sessions : user_id+logged_out_at (active-session queries,
 *                           concurrent-session limit), logged_out_at
 *                           (purgeOldSessions DELETE WHERE logged_out_at < cutoff)
 *  - auth_logins          : user_id+success+id (lastLogin / previousLogin lookups)
 */
class AddSessionAndLoginIndexes extends Migration
{
    /**
     * Auth table names, keyed by logical name.
     */
    private array $tables;

    /**
     * Concrete DB connection (typed for getIndexData() / DBPrefix access).
     */
    private readonly BaseConnection $connection;

    /**
     * Maps each table to the list of index definitions to create.
     * Each entry is either a string (single column) or array (composite).
     *
     * @var array<string, list<list<string>|string>>
     */
    private readonly array $indexMap;

    /**
     * @param Forge|null $forge Optional forge instance (injected in tests).
     */
    public function __construct(?Forge $forge = null)
    {
        /** @var Auth $authConfig */
        $authConfig = config('Auth');

        if ($authConfig->DBGroup !== null) {
            $this->DBGroup = $authConfig->DBGroup;
        }

        parent::__construct($forge);

        $this->connection = Database::connect($authConfig->DBGroup);
        $this->tables     = $authConfig->tables;

        $this->indexMap = [
            $this->tables['device_sessions'] => [['user_id', 'logged_out_at'], 'logged_out_at'],
            $this->tables['logins']          => [['user_id', 'success', 'id']],
        ];
    }

    /**
     * Creates the indexes. Guarded so it is safe to re-run when an index
     * already exists (idempotent).
     */
    public function up(): void
    {
        foreach ($this->indexMap as $table => $indexes) {
            $existing = array_map(static fn ($idx) => $idx->name, $this->connection->getIndexData($table));
            $queued   = 0;

            foreach ($indexes as $columns) {
                $name = $this->connection->DBPrefix . $table . '_' . implode('_', (array) $columns);

                if (in_array($name, $existing, true)) {
                    continue;
                }

                $this->forge->addKey($columns);
                $queued++;
            }

            if ($queued > 0) {
                $this->forge->processIndexes($table);
            }
        }
    }

    /**
     * Drops the indexes created by up(). Missing indexes are ignored so the
     * rollback is safe even when up() never ran.
     */
    public function down(): void
    {
        // processIndexes() names indexes as: {DBPrefix}{table}_{col1}_{col2}
        // dropKey($table, $name, true) resolves to: {DBPrefix}{name}
        // So we pass {table}_{col1}_{col2} as $name to get the correct full name.
        foreach ($this->indexMap as $table => $indexes) {
            foreach ($indexes as $columns) {
                $cols      = (array) $columns;
                $indexName = $table . '_' . implode('_', $cols);

                try {
                    $this->forge->dropKey($table, $indexName);
                } catch (Throwable) {
                    // Index may not exist if up() was never run — safe to ignore.
                }
            }
        }
    }
}
