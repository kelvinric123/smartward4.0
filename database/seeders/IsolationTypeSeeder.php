<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\IsolationType;

class IsolationTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Isolation types from PHKL HIS system (10/12/2025)
     */
    public function run(): void
    {
        $isolationTypes = [
            ['code' => 'DI', 'name' => 'Droplet Isolation'],
            ['code' => 'AI', 'name' => 'Airborne Isolation'],
            ['code' => 'TCI', 'name' => 'Tight Contact Isolation'],
            ['code' => 'IPI', 'name' => 'Immunocompromised Patients Isolation'],
            ['code' => 'CI', 'name' => 'Contact Isolation'],
        ];

        foreach ($isolationTypes as $isolationType) {
            IsolationType::updateOrCreate(
                ['code' => $isolationType['code']],
                [
                    'name' => $isolationType['name'],
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Isolation Types seeded successfully!');
        $this->command->info('- ' . count($isolationTypes) . ' isolation types created/updated');
    }
}









