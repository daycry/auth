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

/**
 * Creates the auth_audit_logs table for granular security event tracking.
 *
 * Distinct from auth_logs (request-level activity) and auth_logins (login
 * attempts) — this table records sensitive account-level events:
 *   - 2FA enable/disable, password change, email change, role change
 *   - lockout triggered, token revoke, trusted-device add/remove
 *
 * Lookups are typically by (user_id, created_at) and (event_type, created_at).
 */
class CreateAuditLogsTable extends Migration
{
    private string $table;

    public function __construct(?Forge $forge = null)
    {
        /** @var Auth $authConfig */
        $authConfig = config('Auth');

        if ($authConfig->DBGroup !== null) {
            $this->DBGroup = $authConfig->DBGroup;
        }

        parent::__construct($forge);

        $this->table = $authConfig->tables['audit_logs'] ?? 'auth_audit_logs';
    }

    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'actor_id'   => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'event_type' => ['type' => 'varchar', 'constraint' => 64],
            'ip_address' => ['type' => 'varchar', 'constraint' => 45, 'null' => true],
            'user_agent' => ['type' => 'varchar', 'constraint' => 500, 'null' => true],
            'metadata'   => ['type' => 'text', 'null' => true],
            'created_at' => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['user_id', 'created_at']);
        $this->forge->addKey(['event_type', 'created_at']);

        $this->forge->createTable($this->table, true);
    }

    public function down(): void
    {
        $this->forge->dropTable($this->table, true);
    }
}
