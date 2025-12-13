<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Hospital;
use App\Models\Nurse;

class AdminManagementSeeder extends Seeder
{
    public function run(): void
    {
        // Create PHKL Hospital
        $hospital = Hospital::create([
            'name' => 'Pantai Hospital Kuala Lumpur (PHKL)',
            'address' => 'Kuala Lumpur, Malaysia',
            'phone' => '+603-2296-0888',
            'email' => 'info@phkl.com.my',
            'description' => 'Premier hospital in Kuala Lumpur providing comprehensive healthcare services with state-of-the-art facilities and experienced medical professionals.',
            'is_active' => true,
        ]);

        // Create 20 Nurses with Malaysian names
        $nurseNames = [
            'Aminah binti Yusof', 'Salmah binti Ahmad', 'Rosnah binti Ibrahim',
            'Fatimah binti Hassan', 'Zainab binti Mohd Ali',
            'Tan Siew Ling', 'Ng Mei Fong', 'Liew Hui Ying',
            'Chong Li Na', 'Ong Su Mei',
            'Devi a/p Raman', 'Malini a/p Kumar', 'Saraswathi a/p Muniandy',
            'Geetha a/p Subramaniam', 'Shanti a/p Gopal',
            'Norazlina binti Zakaria', 'Norfaizah binti Razak',
            'Lim Pei Shan', 'Chua Yan Ting', 'Kavitha a/p Raj',
        ];

        $qualifications = ['Diploma', 'Degree', 'Masters'];
        
        foreach ($nurseNames as $index => $name) {
            Nurse::create([
                'name' => $name,
                'registration_number' => 'MNB' . str_pad(10000 + $index, 5, '0', STR_PAD_LEFT),
                'phone' => '+601' . rand(2, 9) . '-' . rand(200, 999) . ' ' . rand(1000, 9999),
                'email' => strtolower(str_replace([' ', ' binti ', ' a/p '], ['', '', ''], $name)) . '@qmed.asia',
                'qualification' => $qualifications[array_rand($qualifications)],
                'years_of_experience' => rand(2, 20),
                'is_active' => true,
            ]);
        }

        $this->command->info('Admin Management data seeded successfully!');
        $this->command->info('- 1 Hospital (PHKL - Pantai Hospital Kuala Lumpur)');
        $this->command->info('- 20 Nurses');
    }
}
