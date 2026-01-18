<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Nurse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class NurseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = 'nurse@qmed.asia';
        $password = '88888888';

        // Create User
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Nurse Test',
                'password' => Hash::make($password),
                'role' => User::ROLE_NURSE,
                'email_verified_at' => now(),
            ]
        );

        // Ensure role is set correctly if user existed but had different role
        if ($user->role !== User::ROLE_NURSE) {
            $user->role = User::ROLE_NURSE;
            $user->save();
        }

        // Create Nurse Record linked by email
        Nurse::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Nurse Test',
                'personnel_code' => 'N001',
                'registration_number' => 'N' . Str::random(5), // Ensure uniqueness if running multiple times/fresh
                'phone' => '0123456789',
                'department' => 'General Ward',
                'designation' => 'Staff Nurse',
                'qualification' => 'Degree',
                'years_of_experience' => 5,
                'is_active' => true,
            ]
        );
    }
}
