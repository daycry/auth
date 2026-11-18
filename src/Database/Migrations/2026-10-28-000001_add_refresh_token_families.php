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
 * Nullable metadata preserves outstanding tokens until their first rotation.
 */
class AddRefreshTokenFamilies extends Migration
{
    private array $tables;

    public function __construct(?Forge $forge = null)
    {
        $config = config(Auth::class);
        if ($config->DBGroup !== null) {
            $this->DBGroup = $config->DBGroup;
        }
        parent::__construct($forge);
        $this->tables = $config->tables;
    }

    public function up(): void
    {
        $table = $this->tables['identities'];
        $this->forge->addColumn($table, ['refresh_family' => ['type' => 'varchar', 'constraint' => 64, 'null' => true, 'default' => null]]);
        $this->forge->addKey(['user_id', 'type', 'refresh_family']);
        $this->forge->processIndexes($table);
    }

    public function down(): void
    {
        $this->forge->dropKey($this->tables['identities'], $this->tables['identities'] . '_user_id_type_refresh_family');
        $this->forge->dropColumn($this->tables['identities'], 'refresh_family');
    }
}
