<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Bed;
use App\Models\Ward;

class BedSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first ward
        $ward = Ward::first();
        
        if (!$ward) {
            $this->command->error('No ward found! Please create a ward first.');
            return;
        }

        // Create 20 beds
        for ($i = 1; $i <= 20; $i++) {
            $bedNumber = 'B' . str_pad($i, 2, '0', STR_PAD_LEFT);
            
            Bed::create([
                'ward_id' => $ward->id,
                'bed_number' => $bedNumber,
                'bed_id' => 'BED-' . $ward->ward_code . '-' . $bedNumber,
                'bed_display_name' => 'Bed ' . $bedNumber,
                'status' => 'available',
                'is_active' => true,
            ]);
        }

        $this->command->info('Successfully created 20 beds for ward: ' . $ward->ward_name);
    }
}
