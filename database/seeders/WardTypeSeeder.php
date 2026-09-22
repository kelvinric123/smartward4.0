<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WardType;

class WardTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * These are the system ward types (hospital_id null), offered to every
     * hospital. Hospital admins add their own alongside them from the UI.
     */
    public function run(): void
    {
        $wardTypes = [
            ['code' => 'PAED', 'name' => 'Peadiatric'],
            ['code' => 'SURG', 'name' => 'Surgical'],
            ['code' => 'MED', 'name' => 'Medical'],
            ['code' => 'OBGY', 'name' => 'Obs and Gynae'],
            ['code' => 'ICU', 'name' => 'ICU'],
            ['code' => 'HDU', 'name' => 'HDU'],
            ['code' => 'EDOB', 'name' => 'ED Observation Bay'],
        ];

        foreach ($wardTypes as $index => $wardType) {
            WardType::updateOrCreate(
                ['hospital_id' => null, 'code' => $wardType['code']],
                [
                    'name' => $wardType['name'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Ward Types seeded successfully!');
        $this->command->info('- ' . count($wardTypes) . ' system ward types created/updated');
    }
}
