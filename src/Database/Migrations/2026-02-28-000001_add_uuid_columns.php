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

            // Backfill existing rows with UUID v7 (DB-agnostic PHP generation)
            $rows = $this->db->table($table)->select('id')->get()->getResultArray();

            foreach ($rows as $row) {
                $this->db->table($table)
                    ->where('id', $row['id'])
                    ->update(['uuid' => Uuid::v7()->toRfc4122()]);
            }

            // Add unique index
            $this->forge->addUniqueKey('uuid');
            $this->forge->processIndexes($table);
        }
    }

    public function down(): void
    {
        $this->forge->dropColumn($this->tables['users'], 'uuid');
        $this->forge->dropColumn($this->tables['device_sessions'], 'uuid');
    }
}
