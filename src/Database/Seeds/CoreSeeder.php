<?php

namespace Daycry\Auth\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Daycry\Auth\Config\Services;

class CoreSeeder extends Seeder
{
    public function run()
    {
        $groups = [
            [
                'name'    => 'admin',
                'description' => 'Admin group'
            ],
            [
                'name'    => 'user',
                'description' => 'default group'
            ]
        ];

        $builder = $this->db->table(config('Auth')->tables['groups']);
        $builder->insertBatch($groups);
    }
}
