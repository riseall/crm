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
        $adminRole = Role::create(['name' => 'admin']);

        $admin1 = User::create([
            'nik' => '00343',
        ]);
        $admin1->assignRole($adminRole);

        $admin2 = User::create([
            'nik' => '00836',
        ]);
        $admin2->assignRole($adminRole);

        $admin3 = User::create([
            'nik' => '03130',
        ]);
        $admin3->assignRole($adminRole);
    }
}
