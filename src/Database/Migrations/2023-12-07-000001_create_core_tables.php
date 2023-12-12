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

class CreateCoreTables extends Migration
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
        // Users Table
        $this->forge->addField([
            'id'             => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'username'       => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'status'         => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'status_message' => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'active'         => ['type' => 'tinyint', 'constraint' => 1, 'null' => false, 'default' => 0],
            'last_active'    => ['type' => 'datetime', 'null' => true, 'default' => null],
            'created_at'     => ['type' => 'datetime', 'null' => false, 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at'     => ['type' => 'datetime', 'null' => true],
            'deleted_at'     => ['type' => 'datetime', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('username');
        $this->createTable($this->tables['users']);

        // Users Permissions Table
        $this->forge->addField([
            'id'         => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'int', 'constraint' => 11, 'unsigned' => true],
            'permission' => ['type' => 'varchar', 'constraint' => 255, 'null' => false],
            'until_at'   => ['type' => 'datetime', 'null' => true],
            'created_at' => ['type' => 'datetime', 'null' => false, 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at' => ['type' => 'datetime', 'null' => true],
            'deleted_at' => ['type' => 'datetime', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('user_id', $this->tables['users'], 'id', '', 'CASCADE');
        $this->createTable($this->tables['permissions_users']);

        /*
         * Auth Identities Table
         * Used for storage of passwords, access tokens.
         */
        $this->forge->addField([
            'id'            => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'       => ['type' => 'int', 'constraint' => 11, 'unsigned' => true],
            'type'          => ['type' => 'varchar', 'constraint' => 255],
            'name'          => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'secret'        => ['type' => 'varchar', 'constraint' => 255],
            'secret2'       => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'extra'         => ['type' => 'text', 'null' => true],
            'expires'       => ['type' => 'datetime', 'null' => true, 'default' => null],
            'force_reset'   => ['type' => 'tinyint', 'constraint' => 1, 'default' => 0],
            'ignore_limits' => ['type' => 'tinyint', 'constraint' => 1, 'null' => false, 'default' => 0],
            'is_private'    => ['type' => 'tinyint', 'constraint' => 1, 'null' => false, 'default' => 0],
            'ip_addresses'  => ['type' => 'text', 'null' => true, 'default' => null],
            'last_used_at'  => ['type' => 'datetime', 'null' => true],
            'created_at'    => ['type' => 'datetime', 'null' => false, 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at'    => ['type' => 'datetime', 'null' => true],
            'deleted_at'    => ['type' => 'datetime', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['type', 'secret']);
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('user_id', $this->tables['users'], 'id', '', 'CASCADE');
        $this->createTable($this->tables['identities']);

        /**
         * Auth Login Attempts Table
         * Records login attempts. A login means users think it is a login.
         * To login, users do action(s) like posting a form.
         */
        $this->forge->addField([
            'id'         => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'ip_address' => ['type' => 'varchar', 'constraint' => 255],
            'user_agent' => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'id_type'    => ['type' => 'varchar', 'constraint' => 255],
            'identifier' => ['type' => 'varchar', 'constraint' => 255],
            'user_id'    => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'null' => true], // Only for successful logins
            'date'       => ['type' => 'datetime'],
            'success'    => ['type' => 'tinyint', 'constraint' => 1],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['id_type', 'identifier']);
        $this->forge->addKey('user_id');
        // NOTE: Do NOT delete the user_id or identifier when the user is deleted for security audits
        $this->createTable($this->tables['logins']);

        $this->forge->addField([
            'id'              => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'selector'        => ['type' => 'varchar', 'constraint' => 255],
            'hashedValidator' => ['type' => 'varchar', 'constraint' => 255],
            'user_id'         => ['type' => 'int', 'constraint' => 11, 'unsigned' => true],
            'expires'         => ['type' => 'datetime'],
            'created_at'      => ['type' => 'datetime', 'null' => false, 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at'      => ['type' => 'datetime', 'null' => false],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('selector');
        $this->forge->addForeignKey('user_id', $this->tables['users'], 'id', '', 'CASCADE');
        $this->createTable($this->tables['remember_tokens']);

        // Groups Table
        $this->forge->addField([
            'id'         => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'varchar', 'constraint' => 30, 'null' => true],
            'created_at' => ['type' => 'datetime', 'null' => false, 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at' => ['type' => 'datetime', 'null' => true],
            'deleted_at' => ['type' => 'datetime', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('name');
        $this->forge->createTable($this->tables['groups']);

        // Groups Permissions Table
        $this->forge->addField([
            'id'         => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'group_id'   => ['type' => 'int', 'constraint' => 11, 'unsigned' => true],
            'permission' => ['type' => 'varchar', 'constraint' => 255, 'null' => false],
            'until_at'   => ['type' => 'datetime', 'null' => true],
            'created_at' => ['type' => 'datetime', 'null' => false, 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at' => ['type' => 'datetime', 'null' => true],
            'deleted_at' => ['type' => 'datetime', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('group_id', $this->tables['groups'], 'id', '', 'CASCADE');
        $this->createTable($this->tables['permissions_groups']);

        // Users Groups Table
        $this->forge->addField([
            'id'         => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'int', 'constraint' => 11, 'unsigned' => true],
            'group_id'   => ['type' => 'int', 'constraint' => 11, 'unsigned' => true],
            'until_at'   => ['type' => 'datetime', 'null' => true],
            'created_at' => ['type' => 'datetime', 'null' => false, 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at' => ['type' => 'datetime', 'null' => true],
            'deleted_at' => ['type' => 'datetime', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['user_id', 'group_id']);
        $this->forge->addForeignKey('user_id', $this->tables['users'], 'id', '', 'CASCADE');
        $this->forge->addForeignKey('group_id', $this->tables['groups'], 'id', '', 'CASCADE');
        $this->forge->createTable($this->tables['groups_users']);

        // Logs Table
        $this->forge->addField([
            'id'            => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'       => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'default' => null],
            'uri'           => ['type' => 'varchar', 'constraint' => 255, 'null' => false],
            'method'        => ['type' => 'varchar', 'constraint' => 6, 'null' => false],
            'params'        => ['type' => 'text', 'null' => true],
            'ip_address'    => ['type' => 'varchar', 'constraint' => 45, 'null' => false],
            'duration'      => ['type' => 'float', 'null' => true, 'default' => null],
            'response_code' => ['type' => 'int', 'constraint' => 3, 'null' => true],
            'authorized'    => ['type' => 'tinyint', 'constraint' => 1, 'null' => true, 'default' => null],
            'created_at'    => ['type' => 'datetime', 'null' => false, 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at'    => ['type' => 'datetime', 'null' => true, 'default' => null],
            'deleted_at'    => ['type' => 'datetime', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('user_id', $this->tables['users'], 'id', '', 'CASCADE');
        $this->forge->createTable($this->tables['logs']);

        // Apis Table
        $this->forge->addField([
            'id'         => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'url'        => ['type' => 'varchar', 'constraint' => 255, 'null' => false],
            'checked_at' => ['type' => 'datetime', 'null' => false, 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'created_at' => ['type' => 'datetime', 'null' => true, 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at' => ['type' => 'datetime', 'null' => true, 'default' => null],
            'deleted_at' => ['type' => 'datetime', 'null' => true, 'default' => null],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('checked_at');
        $this->forge->addUniqueKey('url');
        $this->forge->createTable($this->tables['apis']);

        // Controllers Table
        $this->forge->addField([
            'id'         => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'api_id'     => ['type' => 'int', 'constraint' => 11, 'unsigned' => true],
            'controller' => ['type' => 'varchar', 'constraint' => 255, 'null' => false],
            'checked_at' => ['type' => 'datetime', 'null' => false, 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'created_at' => ['type' => 'datetime', 'null' => false, 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at' => ['type' => 'datetime', 'null' => true, 'default' => null],
            'deleted_at' => ['type' => 'datetime', 'null' => true, 'default' => null],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('checked_at');
        $this->forge->addKey('controller', false, true);
        $this->forge->addForeignKey('api_id', $this->tables['apis'], 'id', '', 'CASCADE');
        $this->forge->createTable($this->tables['controllers']);

        // Endpoints Table
        $this->forge->addField([
            'id'            => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'controller_id' => ['type' => 'int', 'constraint' => 11, 'unsigned' => true],
            'method'        => ['type' => 'varchar', 'constraint' => 255, 'null' => true, 'default' => null],
            'checked_at'    => ['type' => 'datetime', 'null' => false, 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'auth'          => ['type' => 'varchar', 'constraint' => 10, 'null' => true, 'default' => null],
            'access_token'  => ['type' => 'tinyint', 'constraint' => 1, 'null' => true, 'default' => null],
            'log'           => ['type' => 'tinyint', 'constraint' => 1, 'null' => true, 'default' => null],
            'limit'         => ['type' => 'tinyint', 'constraint' => 1, 'null' => true, 'default' => null],
            'time'          => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'default' => null],
            'scope'         => ['type' => 'varchar', 'constraint' => 255, 'null' => true, 'default' => null],
            'created_at'    => ['type' => 'datetime', 'null' => false, 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at'    => ['type' => 'datetime', 'null' => true, 'default' => null],
            'deleted_at'    => ['type' => 'datetime', 'null' => true, 'default' => null],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('checked_at');
        $this->forge->addUniqueKey(['controller_id', 'method']);
        $this->forge->addForeignKey('controller_id', $this->tables['controllers'], 'id', '', 'CASCADE');
        $this->forge->createTable($this->tables['endpoints']);

        // Attemps Table
        $this->forge->addField([
            'id'              => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'         => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'default' => null],
            'ip_address'      => ['type' => 'varchar', 'constraint' => 45, 'null' => false],
            'attempts'        => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'null' => false, 'default' => 0],
            'hour_started_at' => ['type' => 'datetime', 'null' => false, 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'created_at'      => ['type' => 'datetime', 'null' => false, 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at'      => ['type' => 'datetime', 'null' => true, 'default' => null],
            'deleted_at'      => ['type' => 'datetime', 'null' => true, 'default' => null],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('ip_address');
        $this->forge->addForeignKey('user_id', $this->tables['users'], 'id', '', 'CASCADE');
        $this->forge->createTable($this->tables['attempts']);

        // Limits Table
        $this->forge->addField([
            'id'              => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'         => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'default' => null],
            'uri'             => ['type' => 'varchar', 'constraint' => 255, 'null' => false],
            'hour_started_at' => ['type' => 'datetime', 'null' => false, 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'count'           => ['type' => 'int', 'constraint' => 10, 'null' => false],
            'created_at'      => ['type' => 'datetime', 'null' => false, 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at'      => ['type' => 'datetime', 'null' => true, 'default' => null],
            'deleted_at'      => ['type' => 'datetime', 'null' => true, 'default' => null],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('uri');
        $this->forge->addForeignKey('user_id', $this->tables['users'], 'id', '', 'CASCADE');
        $this->forge->createTable($this->tables['limits']);
    }

    public function down(): void
    {
        $this->db->disableForeignKeyChecks();

        $this->forge->dropTable($this->tables['users'], true);
        $this->forge->dropTable($this->tables['permissions_users'], true);
        $this->forge->dropTable($this->tables['logins'], true);
        $this->forge->dropTable($this->tables['remember_tokens'], true);
        $this->forge->dropTable($this->tables['identities'], true);
        $this->forge->dropTable($this->tables['groups'], true);
        $this->forge->dropTable($this->tables['permissions_groups'], true);
        $this->forge->dropTable($this->tables['groups_users'], true);
        $this->forge->dropTable($this->tables['logs'], true);
        $this->forge->dropTable($this->tables['apis'], true);
        $this->forge->dropTable($this->tables['controllers'], true);
        $this->forge->dropTable($this->tables['endpoints'], true);
        $this->forge->dropTable($this->tables['attempts'], true);
        $this->forge->dropTable($this->tables['limits'], true);

        $this->db->enableForeignKeyChecks();
    }

    private function createTable(string $tableName): void
    {
        $this->forge->createTable($tableName, false, $this->attributes);
    }
}
