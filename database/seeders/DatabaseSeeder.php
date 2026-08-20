<?php

namespace Database\Seeders;

use App\Models\User;
use Bouncer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CategorySeeder::class);
        $this->call(ZoneSeeder::class);

        // Define abilities for the admin role.
        Bouncer::allow('admin')->everything();

        $admin = User::firstOrCreate(
            ['email' => 'admin@ubicanegocios.test'],
            ['name' => 'Administrador', 'password' => Hash::make('password')],
        );

        $admin->assign('admin');

        $this->call(BusinessSeeder::class);
    }
}
