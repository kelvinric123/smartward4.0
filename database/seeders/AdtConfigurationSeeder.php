<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AdtConfiguration;
use App\Models\AdtHospitalMapping;
use App\Models\AdtWardMapping;
use App\Models\AdtBedMapping;
use App\Models\AdtDoctorMapping;
use App\Models\Hospital;
use App\Models\Ward;
use App\Models\Bed;
use App\Models\Consultant;

class AdtConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Seeds ADT configuration and mappings for HL7 integration testing
     */
    public function run(): void
    {
        // Get existing data
        $hospital = Hospital::first();
        $ward = Ward::first();
        $consultants = Consultant::where('is_active', true)->limit(10)->get();

        if (!$hospital) {
            $this->command->error('No hospital found! Please run AdminManagementSeeder first.');
            return;
        }

        if (!$ward) {
            $this->command->error('No ward found! Please run WardSeeder first.');
            return;
        }

        // Create ADT Configuration
        $configuration = AdtConfiguration::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'Default ADT Configuration',
                'listener_host' => '0.0.0.0',
                'listener_port' => 3000,
                'is_active' => true,
                'auto_admit' => true,
                'auto_discharge' => true,
                'auto_transfer' => true,
                'settings' => [
                    'log_raw_messages' => true,
                    'create_unknown_patients' => true,
                    'update_existing_patients' => true,
                ],
            ]
        );

        $this->command->info('Created ADT Configuration');

        // Hospital Mapping - Map PHKL code to PHKL hospital
        AdtHospitalMapping::updateOrCreate(
            [
                'adt_configuration_id' => $configuration->id,
                'adt_hospital_code' => 'PHKL',
            ],
            [
                'adt_hospital_name' => 'Pantai Hospital Kuala Lumpur (HIS)',
                'hospital_id' => $hospital->id,
                'is_active' => true,
            ]
        );

        $this->command->info("Created Hospital Mapping: PHKL -> {$hospital->name}");

        // Ward Mapping - Map common ward codes to D6
        $wardCodes = ['D6', '5A', 'ICU', 'W5', 'WARD5'];
        foreach ($wardCodes as $code) {
            AdtWardMapping::updateOrCreate(
                [
                    'adt_configuration_id' => $configuration->id,
                    'adt_ward_code' => $code,
                ],
                [
                    'adt_ward_name' => "Ward {$code} (HIS)",
                    'ward_id' => $ward->id,
                    'is_active' => true,
                ]
            );
        }

        $this->command->info("Created Ward Mappings: " . implode(', ', $wardCodes) . " -> {$ward->ward_name}");

        // Bed Mappings - Map HIS bed codes to SmartWard beds
        $beds = Bed::where('ward_id', $ward->id)->where('is_active', true)->get();
        
        foreach ($beds as $bed) {
            // Map various formats: B01 -> B01, B1 -> B01, etc.
            $bedNum = (int) filter_var($bed->bed_number, FILTER_SANITIZE_NUMBER_INT);
            
            // Create mapping for short format (B1, B2, etc.)
            AdtBedMapping::updateOrCreate(
                [
                    'adt_configuration_id' => $configuration->id,
                    'adt_bed_code' => 'B' . $bedNum,
                ],
                [
                    'adt_bed_name' => "Bed {$bedNum} (HIS)",
                    'bed_id' => $bed->id,
                    'is_active' => true,
                ]
            );

            // Also create mapping for long format (B01, B02, etc.)
            if ($bedNum < 10) {
                AdtBedMapping::updateOrCreate(
                    [
                        'adt_configuration_id' => $configuration->id,
                        'adt_bed_code' => 'B0' . $bedNum,
                    ],
                    [
                        'adt_bed_name' => "Bed 0{$bedNum} (HIS)",
                        'bed_id' => $bed->id,
                        'is_active' => true,
                    ]
                );
            }
        }

        $this->command->info("Created Bed Mappings for {$beds->count()} beds");

        // Doctor Mappings - Map HIS doctor codes to SmartWard consultants
        $doctorCodes = [
            'DOCTOR0' => 'attending',
            'DOCTOR1' => 'attending',
            'DOCTOR2' => 'referring',
            'DOCTOR3' => 'admitting',
            'DR001' => 'attending',
            'DR002' => 'attending',
            'DR003' => 'referring',
        ];

        $consultantIndex = 0;
        foreach ($doctorCodes as $code => $type) {
            if ($consultantIndex >= $consultants->count()) {
                $consultantIndex = 0; // Wrap around if we run out of consultants
            }
            
            $consultant = $consultants[$consultantIndex];
            
            AdtDoctorMapping::updateOrCreate(
                [
                    'adt_configuration_id' => $configuration->id,
                    'adt_doctor_code' => $code,
                    'doctor_type' => $type,
                ],
                [
                    'adt_doctor_name' => "{$code} (HIS)",
                    'consultant_id' => $consultant->id,
                    'is_active' => true,
                ]
            );

            $consultantIndex++;
        }

        $this->command->info("Created Doctor Mappings: " . implode(', ', array_keys($doctorCodes)));

        // Summary
        $this->command->newLine();
        $this->command->info('========================================');
        $this->command->info('ADT Configuration Seeding Complete!');
        $this->command->info('========================================');
        $this->command->info('Configuration:');
        $this->command->info("  - Host: {$configuration->listener_host}");
        $this->command->info("  - Port: {$configuration->listener_port}");
        $this->command->info("  - Auto Admit: " . ($configuration->auto_admit ? 'Yes' : 'No'));
        $this->command->info("  - Auto Discharge: " . ($configuration->auto_discharge ? 'Yes' : 'No'));
        $this->command->info("  - Auto Transfer: " . ($configuration->auto_transfer ? 'Yes' : 'No'));
        $this->command->newLine();
        $this->command->info('Mappings Created:');
        $this->command->info("  - 1 Hospital Mapping (PHKL)");
        $this->command->info("  - " . count($wardCodes) . " Ward Mappings");
        $this->command->info("  - " . ($beds->count() * 2) . " Bed Mappings (short + long format)");
        $this->command->info("  - " . count($doctorCodes) . " Doctor Mappings");
        $this->command->newLine();
        $this->command->info('You can now test with the sample HL7 message sender:');
        $this->command->info('  cd HL7 && python sample_adt_sender.py');
    }
}




