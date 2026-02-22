<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'drtai@qmed.asia',
            'password' => Hash::make('88888888'),
            'role' => 'superadmin',
        ]);

        User::factory()->create([
            'name' => 'IHH Support Manager',
            'email' => 'ihh_support_manager@ihh.com',
            'password' => Hash::make('88888888'),
            'role' => 'superadmin',
        ]);

        // Seed Admin Management data
        $this->call([
            SuperAdminSeeder::class,
            ApiUserSeeder::class,
            AdminManagementSeeder::class,
            DietTypeSeeder::class,        // Patient Additional Fields - Diet Types
            IsolationTypeSeeder::class,   // Patient Additional Fields - Isolation Types
            WardSeeder::class,
            BedSeeder::class,
            PatientSeeder::class,
            SpecialtySeeder::class,
            ConsultantSeeder::class,
            AnaesthetistSeeder::class,
            NurseSeeder::class,
            EkadBedMappingD6Seeder::class,
            InfusionPumpSeeder::class,
            RegisteredPumpUserSeeder::class,
        ]);
    }
}
