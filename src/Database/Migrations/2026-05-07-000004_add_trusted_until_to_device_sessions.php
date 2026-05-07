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
 * Adds a `trusted_until` timestamp to `auth_device_sessions` to support
 * the "Trust this device" feature: once a user passes 2FA on a device
 * and ticks the trust checkbox, subsequent logins from the same device
 * skip the 2FA challenge until this date.
 */
class AddTrustedUntilToDeviceSessions extends Migration
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

        $this->table = $authConfig->tables['device_sessions'];
    }

    public function up(): void
    {
        $this->forge->addColumn($this->table, [
            'trusted_until' => ['type' => 'datetime', 'null' => true, 'default' => null],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn($this->table, 'trusted_until');
    }
}
