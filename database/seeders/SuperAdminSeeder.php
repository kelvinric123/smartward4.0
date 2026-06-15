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
        User::firstOrCreate(
            ['email' => 'drtai@qmed.asia'],
            [
                'name' => 'Dr. Tai',
                'password' => Hash::make('88888888'),
                'role' => User::ROLE_SUPERADMIN,
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('IHH Superadmin user created/updated successfully.');
    }
}
