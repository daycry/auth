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
use CodeIgniter\Database\RawSql;
use Daycry\Auth\Config\Auth;

class CreateDeviceSessionsTable extends Migration
{
    private array $tables;
    private array $attributes;

    public function __construct(?Forge $forge = null)
    {
        /** @var Auth $authConfig */
        $authConfig = config('Auth');

        if ($authConfig->DBGroup !== null) {
            $this->DBGroup = $authConfig->DBGroup;
        }

        parent::__construct($forge);

        $this->tables     = $authConfig->tables;
        $this->attributes = ($this->db->getPlatform() === 'MySQLi') ? ['ENGINE' => 'InnoDB'] : [];
    }

    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'       => ['type' => 'int', 'constraint' => 11, 'unsigned' => true],
            'session_id'    => ['type' => 'varchar', 'constraint' => 128, 'null' => false],
            'device_name'   => ['type' => 'varchar', 'constraint' => 255, 'null' => true, 'default' => null],
            'ip_address'    => ['type' => 'varchar', 'constraint' => 45, 'null' => false],
            'user_agent'    => ['type' => 'text', 'null' => true, 'default' => null],
            'last_active'   => ['type' => 'datetime', 'null' => false, 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'logged_out_at' => ['type' => 'datetime', 'null' => true, 'default' => null],
            'created_at'    => ['type' => 'datetime', 'null' => false, 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at'    => ['type' => 'datetime', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('user_id');
        $this->forge->addKey('session_id');
        $this->forge->addForeignKey('user_id', $this->tables['users'], 'id', '', 'CASCADE');
        $this->forge->createTable($this->tables['device_sessions'], false, $this->attributes);
    }

    public function down(): void
    {
        $this->forge->dropTable($this->tables['device_sessions'], true);
    }
}
