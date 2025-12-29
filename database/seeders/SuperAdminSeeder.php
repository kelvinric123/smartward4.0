<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'ihh_support_manager@ihh.com'],
            [
                'name' => 'IHH Support Manager',
                'password' => Hash::make('C@mplexPassw0rd'),
                'role' => User::ROLE_SUPERADMIN,
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('IHH Superadmin user created/updated successfully.');
    }
}
