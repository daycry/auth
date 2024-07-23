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

namespace Daycry\Auth\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CoreSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            [
                'name'        => 'admin',
                'description' => 'Admin group',
            ],
            [
                'name'        => 'user',
                'description' => 'default group',
            ],
        ];

        $builder = $this->db->table(config('Auth')->tables['groups']);
        $builder->insertBatch($groups);
    }
}
