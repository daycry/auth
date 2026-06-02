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
 * Adds a `token_version` counter to the users table. Issued JWT access tokens
 * embed the version they were minted under; bumping it (on ban, password
 * change, or explicit revocation) invalidates every outstanding access token
 * for that user without a per-token denylist.
 */
class AddJwtTokenVersionToUsers extends Migration
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
        $this->forge->addColumn($this->tables['users'], [
            'token_version' => ['type' => 'int', 'constraint' => 11, 'null' => false, 'default' => 0],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn($this->tables['users'], 'token_version');
    }
}
