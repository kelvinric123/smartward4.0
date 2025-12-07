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
                'name' => 'Pantai Hospital Kuala Lumpur (PHKL)',
                'address' => 'Kuala Lumpur, Malaysia',
                'phone' => '+603-2296-0888',
                'email' => 'info@phkl.com.my',
                'description' => 'Premier hospital in Kuala Lumpur providing comprehensive healthcare services.',
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
