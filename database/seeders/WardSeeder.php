<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Ward;

class WardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first hospital or create one if it doesn't exist
        $hospital = \App\Models\Hospital::first();
        
        if (!$hospital) {
            $hospital = \App\Models\Hospital::create([
                'name' => 'Pusat Hemodialisis Klinik Lim (PHKL)',
                'address' => 'Malaysia',
                'phone' => '+60-000-0000',
                'email' => 'info@phkl.com',
                'description' => 'Main hospital facility',
                'is_active' => true,
            ]);
        }

        $wards = [
            [
                'hospital_id' => $hospital->id,
                'ward_code' => 'D6',
                'ward_name' => 'PHKL D6',
                'capacity' => 20,
                'specialties' => 'General Medicine, Surgery',
                'description' => 'Main general ward for PHKL',
                'is_active' => true,
            ],
        ];

        foreach ($wards as $ward) {
            Ward::create($ward);
        }
    }
}
