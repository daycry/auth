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

class CreateWebauthnCredentials extends Migration
{
    private array $tables;
    private readonly array $attributes;

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
            'id'            => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'uuid'          => ['type' => 'varchar', 'constraint' => 36, 'null' => true],
            'user_id'       => ['type' => 'int', 'constraint' => 11, 'unsigned' => true],
            'credential_id' => ['type' => 'varchar', 'constraint' => 512, 'null' => false],
            'credential'    => ['type' => 'text', 'null' => false],
            'user_handle'   => ['type' => 'varchar', 'constraint' => 255, 'null' => true, 'default' => null],
            'name'          => ['type' => 'varchar', 'constraint' => 255, 'null' => true, 'default' => null],
            'sign_count'    => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'transports'    => ['type' => 'varchar', 'constraint' => 255, 'null' => true, 'default' => null],
            'aaguid'        => ['type' => 'varchar', 'constraint' => 64, 'null' => true, 'default' => null],
            'last_used_at'  => ['type' => 'datetime', 'null' => true, 'default' => null],
            'created_at'    => ['type' => 'datetime', 'null' => false, 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at'    => ['type' => 'datetime', 'null' => true, 'default' => null],
            'revoked_at'    => ['type' => 'datetime', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('credential_id');
        $this->forge->addUniqueKey('uuid');
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('user_id', $this->tables['users'], 'id', '', 'CASCADE');
        $this->forge->createTable($this->tables['webauthn_credentials'], false, $this->attributes);
    }

    public function down(): void
    {
        $this->forge->dropTable($this->tables['webauthn_credentials'], true);
    }
}
