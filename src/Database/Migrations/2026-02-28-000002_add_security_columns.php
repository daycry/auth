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

class AddSecurityColumns extends Migration
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
        // Add revoked_at to identities table
        $this->forge->addColumn($this->tables['identities'], [
            'revoked_at' => ['type' => 'datetime', 'null' => true, 'default' => null],
        ]);

        // Add lockout columns to users table
        $this->forge->addColumn($this->tables['users'], [
            'failed_login_count' => ['type' => 'int', 'constraint' => 11, 'null' => false, 'default' => 0],
            'locked_until'       => ['type' => 'datetime', 'null' => true, 'default' => null],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn($this->tables['identities'], 'revoked_at');
        $this->forge->dropColumn($this->tables['users'], ['failed_login_count', 'locked_until']);
    }
}
