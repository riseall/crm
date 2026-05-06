<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $roles = [
            'Super Admin',
            'Admin IT',
            'Marketing Insight',
            'General Manager',
            'Regional Manager',
            'Product Manager',
            'Senior Area Manager',
            'Area Manager',
            'Medical Representative',
            'Sales Representative'
        ];

        foreach ($roles as $role) {
            Role::create(['name' => $role]);
        }

        $admin1 = User::create([
            'nik' => '00343',
            'name' => '',
            'email' => 'qxRdD@example.com',
            'jabatan' => '',
        ]);
        $admin1->assignRole('Admin IT');

        $admin2 = User::create([
            'nik' => '00836',
            'name' => '',
            'email' => 'wM0lU@example.com',
            'jabatan' => '',
        ]);
        $admin2->assignRole('Admin IT');

        $admin3 = User::create([
            'nik' => '03130',
            'name' => '',
            'email' => 'q1LJH@example.com',
            'jabatan' => '',
        ]);
        $admin3->assignRole('Admin IT');
    }
}
