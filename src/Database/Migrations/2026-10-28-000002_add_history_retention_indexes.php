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
 * Time-leading indexes support scheduled retention across all users.
 */
class AddHistoryRetentionIndexes extends Migration
{
    /**
     * @var array<string, string>
     */
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
        foreach (['logins' => 'date', 'audit_logs' => 'created_at'] as $key => $column) {
            $this->forge->addKey([$column, 'id']);
            $this->forge->processIndexes($this->tables[$key]);
        }
    }

    public function down(): void
    {
        foreach (['logins' => 'date', 'audit_logs' => 'created_at'] as $key => $column) {
            $this->forge->dropKey($this->tables[$key], $this->tables[$key] . '_' . $column . '_id');
        }
    }
}
