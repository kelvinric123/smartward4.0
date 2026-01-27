<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Bed;
use App\Models\EkadBedMapping;

class EkadBedMappingD6Seeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mappings = [
            'D601' => 'D43D393D1C32',
            'D602' => 'D43D3978FA36',
            'D603' => 'D43D3978F274',
            'D604' => 'D43D39717508',
            'D605' => 'D43D397160EA',
            'D606' => 'D43D39715CC8',
            'D607' => 'D43D3971543E',
            'D608' => 'D43D393C9256',
            'D609' => 'D43D393C7C1C',
            'D610' => 'D43D39715A40',
            'D611' => 'D43D3971732A',
            'D612' => 'D43D393D13C6',
            'D613' => 'D43D393C7B6E',
            'D614' => 'D43D3978E838',
            'D615' => 'D43D39716116',
            'D616' => 'D43D397166E0',
            'D617' => 'D43D393C9638',
            'D618' => 'D43D393C7B6E',
            'D619' => 'D43D39716B20',
            'D620' => 'D43D393C9B00',
            'D621' => 'D43D397166E6',
            'D622' => 'D43D393CC02C',
        ];

        foreach ($mappings as $bedNumber => $macAddress) {
            $bed = Bed::where('bed_number', $bedNumber)->first();

            if ($bed) {
                EkadBedMapping::updateOrCreate(
                    ['bed_id' => $bed->id],
                    [
                        'mac_address' => $macAddress,
                        'is_active' => true,
                        // Optional: Set a default device name or leave it null/empty
                        'device_name' => 'EKAD Monitor ' . $bedNumber
                    ]
                );
                $this->command->info("Mapped $bedNumber to $macAddress");
            } else {
                $this->command->warn("Bed $bedNumber not found!");
            }
        }
    }
}
