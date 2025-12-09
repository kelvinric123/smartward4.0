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

        // Create 22 beds for Ward D6
        // Bed code format: D6XX where XX is the bed number (01-22)
        // Example: D601, D602, ... D622
        for ($i = 1; $i <= 22; $i++) {
            // Bed number format: D6 + 2-digit number (D601, D602, ..., D622)
            $bedNumber = 'D6' . str_pad($i, 2, '0', STR_PAD_LEFT);
            
            Bed::create([
                'ward_id' => $ward->id,
                'bed_number' => $bedNumber,
                'bed_id' => $bedNumber, // Using same format for bed_id
                'bed_display_name' => 'Bed ' . $i,
                'status' => 'available',
                'is_active' => true,
            ]);
        }

        $this->command->info('Successfully created 22 beds (D601-D622) for ward: ' . $ward->ward_name);
    }
}
