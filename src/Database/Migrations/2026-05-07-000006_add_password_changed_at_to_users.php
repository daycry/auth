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
 * Adds a `password_changed_at` timestamp on the users table.
 *
 * Used by the {@see \Daycry\Auth\Filters\PasswordAgeFilter} to enforce
 * periodic password rotation when `AuthSecurity::$passwordMaxAge > 0`.
 */
class AddPasswordChangedAtToUsers extends Migration
{
    private readonly string $table;

    public function __construct(?Forge $forge = null)
    {
        /** @var Auth $authConfig */
        $authConfig = config('Auth');

        if ($authConfig->DBGroup !== null) {
            $this->DBGroup = $authConfig->DBGroup;
        }

        parent::__construct($forge);

        $this->table = $authConfig->tables['users'];
    }

    public function up(): void
    {
        $this->forge->addColumn($this->table, [
            'password_changed_at' => ['type' => 'datetime', 'null' => true, 'default' => null],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn($this->table, 'password_changed_at');
    }
}
