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
 * Stores one-time backup codes that let a user authenticate when they
 * have lost access to their TOTP authenticator app.
 *
 * Codes are stored as SHA-256 hashes; the plaintext is shown to the user
 * only once during enrollment. Once consumed, `used_at` is set and the
 * row is no longer eligible for verification.
 */
class CreateTotpBackupCodesTable extends Migration
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

        $this->table = $authConfig->tables['totp_backup_codes'] ?? 'auth_totp_backup_codes';
    }

    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'int', 'constraint' => 11, 'unsigned' => true],
            'code_hash'  => ['type' => 'varchar', 'constraint' => 64],
            'used_at'    => ['type' => 'datetime', 'null' => true, 'default' => null],
            'created_at' => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['user_id', 'used_at']);
        $this->forge->addUniqueKey(['user_id', 'code_hash']);

        $this->forge->createTable($this->table, true);
    }

    public function down(): void
    {
        $this->forge->dropTable($this->table, true);
    }
}
