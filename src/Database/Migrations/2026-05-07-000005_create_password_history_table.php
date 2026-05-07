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
 * Stores hashes of recently used passwords to prevent password reuse.
 * Only used when `AuthSecurity::$passwordHistorySize > 0`.
 */
class CreatePasswordHistoryTable extends Migration
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

        $this->table = $authConfig->tables['password_history'] ?? 'auth_password_history';
    }

    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'       => ['type' => 'int', 'constraint' => 11, 'unsigned' => true],
            'password_hash' => ['type' => 'varchar', 'constraint' => 255],
            'created_at'    => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['user_id', 'created_at']);

        $this->forge->createTable($this->table, true);
    }

    public function down(): void
    {
        $this->forge->dropTable($this->table, true);
    }
}
