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
use Symfony\Component\Uid\Uuid;

/**
 * Adds a `uuid` column (UUID v7, unique) to the users and device_sessions tables.
 *
 * The `id` column remains as the internal integer primary key.
 * `uuid` is the external identifier safe to expose in APIs.
 */
class AddUuidColumns extends Migration
{
    private array $tables;

    public function __construct(?Forge $forge = null)
    {
        /** @var Auth $authConfig */
        $authConfig = config('Auth');

        if ($authConfig->DBGroup !== null) {
            $this->DBGroup = $authConfig->DBGroup;
        }

        parent::__construct($forge);

        $this->tables = $authConfig->tables;
    }

    public function up(): void
    {
        foreach ([$this->tables['users'], $this->tables['device_sessions']] as $table) {
            // Add as nullable first (avoids issues with existing rows and SQLite ALTER TABLE)
            $this->forge->addColumn($table, [
                'uuid' => ['type' => 'varchar', 'constraint' => 36, 'null' => true, 'after' => 'id'],
            ]);

            // Backfill existing rows with UUID v7 (DB-agnostic PHP generation).
            // Use updateBatch in chunks to avoid one query per row on large tables —
            // a fresh install on a populated DB now does O(N/chunk) UPDATEs instead of O(N).
            $this->backfillUuids($table);

            // Add unique index
            $this->forge->addUniqueKey('uuid');
            $this->forge->processIndexes($table);
        }
    }

    /**
     * Backfills the `uuid` column in chunks using updateBatch().
     * Skips rows that already have a uuid (idempotent re-run).
     */
    private function backfillUuids(string $table): void
    {
        $chunkSize = 1000;
        $offset    = 0;

        while (true) {
            $rows = $this->db->table($table)
                ->select('id')
                ->where('uuid')
                ->orderBy('id', 'ASC')
                ->limit($chunkSize, $offset)
                ->get()
                ->getResultArray();

            if ($rows === []) {
                break;
            }

            $batch = [];

            foreach ($rows as $row) {
                $batch[] = [
                    'id'   => $row['id'],
                    'uuid' => Uuid::v7()->toRfc4122(),
                ];
            }

            // updateBatch executes a single UPDATE … CASE … per chunk,
            // rather than one UPDATE per row.
            $this->db->table($table)->updateBatch($batch, 'id');

            // Rows are now non-null, so future iterations skip them via WHERE uuid IS NULL.
            // No need to bump $offset — keep reading from the start of remaining nulls.
            if (count($rows) < $chunkSize) {
                break;
            }
        }
    }

    public function down(): void
    {
        $this->forge->dropColumn($this->tables['users'], 'uuid');
        $this->forge->dropColumn($this->tables['device_sessions'], 'uuid');
    }
}
