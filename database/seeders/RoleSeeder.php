<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'admin',    'description' => 'Full access to all modules'],
            ['name' => 'manager',  'description' => 'Access to department dashboards'],
            ['name' => 'employee', 'description' => 'Limited access based on assigned role'],
            ['name' => 'customer', 'description' => 'Customer with shop access only'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role['name']], $role);
        }
    }
}