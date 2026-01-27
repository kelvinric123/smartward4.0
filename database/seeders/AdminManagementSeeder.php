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

        $this->command->info('Admin Management data seeded successfully!');
        $this->command->info('- 1 Hospital (PHKL - Pantai Hospital Kuala Lumpur)');
    }
}
