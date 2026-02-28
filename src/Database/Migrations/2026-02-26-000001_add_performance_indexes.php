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
 * Adds performance indexes to auth tables identified during audit.
 *
 * Affected tables:
 *  - auth_identities  : expires, force_reset+type (token cleanup & forced-reset lookups)
 *  - auth_logins      : ip_address, user_id+date   (security audits, login history)
 *  - auth_attempts    : ip_address+hour_started_at (rate-limit window queries)
 *  - auth_rates       : uri+hour_started_at, user_id+uri+hour_started_at
 *  - auth_logs        : created_at                 (time-range audit queries)
 */
class AddPerformanceIndexes extends Migration
{
    private array $tables;

    /**
     * Maps each table to the list of index definitions to create.
     * Each entry is either a string (single column) or array (composite).
     *
     * @var array<string, list<list<string>|string>>
     */
    private array $indexMap;

    public function __construct(?Forge $forge = null)
    {
        /** @var Auth $authConfig */
        $authConfig = config('Auth');

        if ($authConfig->DBGroup !== null) {
            $this->DBGroup = $authConfig->DBGroup;
        }

        parent::__construct($forge);

        $this->tables = $authConfig->tables;

        $this->indexMap = [
            $this->tables['identities'] => [
                'expires',
                ['force_reset', 'type'],
                'last_used_at',
            ],
            $this->tables['logins'] => [
                'ip_address',
                ['user_id', 'date'],
            ],
            $this->tables['attempts'] => [
                ['ip_address', 'hour_started_at'],
            ],
            $this->tables['rates'] => [
                ['uri', 'hour_started_at'],
                ['user_id', 'uri', 'hour_started_at'],
            ],
            $this->tables['logs'] => [
                'created_at',
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->indexMap as $table => $indexes) {
            foreach ($indexes as $columns) {
                $this->forge->addKey($columns);
            }

            $this->forge->processIndexes($table);
        }
    }

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
                } catch (Throwable $e) {
                    // Index may not exist if up() was never run — safe to ignore.
                }
            }
        }
    }
}
