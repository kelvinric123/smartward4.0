<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Hospital;
use App\Models\Specialty;
use App\Models\Consultant;
use App\Models\Anaesthetist;
use App\Models\Nurse;

class AdminManagementSeeder extends Seeder
{
    public function run(): void
    {
        // Create PHKL Hospital
        $hospital = Hospital::create([
            'name' => 'Pusat Hemodialisis Kota Laksamana (PHKL)',
            'address' => 'Jalan Kota Laksamana, 75200 Melaka, Malaysia',
            'phone' => '+606-283-5555',
            'email' => 'info@phkl.com.my',
            'description' => 'Premier dialysis center in Melaka providing comprehensive hemodialysis services with state-of-the-art facilities and experienced medical professionals dedicated to kidney care.',
            'is_active' => true,
        ]);

        // Create 10 Specialties
        $specialtiesData = [
            ['name' => 'Cardiology', 'description' => 'Diagnosis and treatment of heart and cardiovascular diseases'],
            ['name' => 'Orthopedics', 'description' => 'Treatment of musculoskeletal system disorders'],
            ['name' => 'Pediatrics', 'description' => 'Medical care for infants, children, and adolescents'],
            ['name' => 'Obstetrics & Gynecology', 'description' => "Women's reproductive health and pregnancy care"],
            ['name' => 'General Surgery', 'description' => 'Surgical treatment of abdominal organs and related conditions'],
            ['name' => 'Neurology', 'description' => 'Treatment of nervous system disorders'],
            ['name' => 'Oncology', 'description' => 'Diagnosis and treatment of cancer'],
            ['name' => 'Dermatology', 'description' => 'Treatment of skin, hair, and nail conditions'],
            ['name' => 'Ophthalmology', 'description' => 'Eye and vision care'],
            ['name' => 'ENT (Ear, Nose & Throat)', 'description' => 'Treatment of ear, nose, throat, and related structures'],
        ];

        $specialties = [];
        foreach ($specialtiesData as $data) {
            $specialties[] = Specialty::create([
                'name' => $data['name'],
                'description' => $data['description'],
                'is_active' => true,
            ]);
        }

        // Malaysian names (mix of Malay, Chinese, and Indian names)
        $consultantNames = [
            'Dr. Ahmad bin Abdullah', 'Dr. Siti Nurhaliza binti Hassan',
            'Dr. Tan Wei Ming', 'Dr. Lee Mei Ling',
            'Dr. Rajesh Kumar a/l Subramaniam', 'Dr. Priya Devi a/p Murugan',
            'Dr. Muhammad Hafiz bin Ismail', 'Dr. Nurul Aina binti Mohd Yusof',
            'Dr. Wong Kar Wai', 'Dr. Lim Su Lin',
            'Dr. Suresh a/l Narayanan', 'Dr. Kavitha a/p Raman',
            'Dr. Azman bin Othman', 'Dr. Farah Diyana binti Abdul Rahman',
            'Dr. Chen Li Hua', 'Dr. Teo Siew Peng',
            'Dr. Kumar a/l Gopal', 'Dr. Lakshmi a/p Krishnan',
            'Dr. Zulkifli bin Hassan', 'Dr. Aisha binti Ibrahim',
        ];

        // Create 2 Consultants for each specialty (20 consultants total)
        $consultantIndex = 0;
        foreach ($specialties as $specialty) {
            for ($i = 0; $i < 2; $i++) {
                Consultant::create([
                    'name' => $consultantNames[$consultantIndex],
                    'specialty_id' => $specialty->id,
                    'registration_number' => 'MMC' . str_pad(20000 + $consultantIndex, 5, '0', STR_PAD_LEFT),
                    'phone' => '+601' . rand(2, 9) . '-' . rand(200, 999) . ' ' . rand(1000, 9999),
                    'email' => strtolower(str_replace([' ', '.', 'Dr. ', ' bin ', ' binti ', ' a/l ', ' a/p '], ['', '', '', '', '', '', ''], $consultantNames[$consultantIndex])) . '@qmed.asia',
                    'qualifications' => 'MBBS, ' . ($i == 0 ? 'MD' : 'MRCP') . ', Fellowship in ' . $specialty->name,
                    'years_of_experience' => rand(5, 25),
                    'is_active' => true,
                ]);
                $consultantIndex++;
            }
        }

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

        // Create Anaesthetists with Malaysian names
        $anaesthetistNames = [
            'Dr. Mohd Faizal bin Abdullah',
            'Dr. Norzahirah binti Ismail',
            'Dr. Khoo Boon Seng',
            'Dr. Goh Siew Chin',
            'Dr. Maniam a/l Suppiah',
        ];

        foreach ($anaesthetistNames as $index => $name) {
            Anaesthetist::create([
                'name' => $name,
                'registration_number' => 'MMA' . str_pad(30000 + $index, 5, '0', STR_PAD_LEFT),
                'phone' => '+601' . rand(2, 9) . '-' . rand(200, 999) . ' ' . rand(1000, 9999),
                'email' => strtolower(str_replace([' ', '.', 'Dr. ', ' bin ', ' binti ', ' a/l '], ['', '', '', '', '', ''], $name)) . '@qmed.asia',
                'qualifications' => 'MBBS, MMed (Anaesthesiology), FANZCA',
                'years_of_experience' => rand(8, 20),
                'is_active' => true,
            ]);
        }

        $this->command->info('Admin Management data seeded successfully!');
        $this->command->info('- 1 Hospital (PHKL - Pusat Hemodialisis Kota Laksamana)');
        $this->command->info('- 10 Specialties');
        $this->command->info('- 20 Consultants (2 per specialty)');
        $this->command->info('- 20 Nurses');
        $this->command->info('- 5 Anaesthetists');
    }
}
