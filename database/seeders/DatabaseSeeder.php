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

        // Seed Admin Management data
        $this->call([
            AdminManagementSeeder::class,
            WardSeeder::class,
            BedSeeder::class,
            PatientSeeder::class,
        ]);
    }
}
