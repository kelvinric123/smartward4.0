<?php

namespace App\Observers;

use App\Models\Bed;
use App\Models\EkadConfiguration;
use App\Models\EkadBedMapping;
use App\Services\EkadService;
use Illuminate\Support\Facades\Log;

class BedObserver
{
    /**
     * Handle the Bed "updated" event.
     * Triggered when patient is admitted, transferred, or discharged from a bed
     */
    public function updated(Bed $bed): void
    {
        // Check if patient_id changed (admission, transfer, or discharge)
        if ($bed->wasChanged('patient_id')) {
            Log::info('EKad BedObserver: Bed patient_id changed', [
                'bed_id' => $bed->id,
                'bed_number' => $bed->bed_number,
                'old_patient_id' => $bed->getOriginal('patient_id'),
                'new_patient_id' => $bed->patient_id,
            ]);

            $this->handleBedChange($bed);
        }
    }

    /**
     * Handle bed change and push to E-Ink if applicable
     */
    protected function handleBedChange(Bed $bed): void
    {
        try {
            // Get EKad configuration
            $config = EkadConfiguration::getActive();
            if (!$config) {
                Log::debug('EKad BedObserver: No active configuration found');
                return;
            }

            if (!$config->is_active) {
                Log::debug('EKad BedObserver: Configuration is not active');
                return;
            }

            if (!$config->auto_push_enabled) {
                Log::debug('EKad BedObserver: Auto-push is disabled');
                return;
            }

            // Check if bed has E-Ink mapping
            $mapping = EkadBedMapping::getForBed($bed->id);
            if (!$mapping) {
                Log::debug('EKad BedObserver: No E-Ink mapping for bed', [
                    'bed_id' => $bed->id,
                    'bed_number' => $bed->bed_number,
                ]);
                return;
            }

            // Check if patient is assigned (admission/transfer IN)
            if ($bed->patient_id) {
                // Load the patient
                $patient = $bed->patient;
                if (!$patient) {
                    Log::warning('EKad BedObserver: Patient not found', [
                        'bed_id' => $bed->id,
                        'patient_id' => $bed->patient_id,
                    ]);
                    return;
                }

                Log::info('EKad BedObserver: Pushing patient info to E-Ink (admission/transfer)', [
                    'bed_id' => $bed->id,
                    'bed_number' => $bed->bed_number,
                    'patient_id' => $patient->id,
                    'patient_name' => $patient->name,
                    'mac' => $mapping->mac_address,
                ]);

                $service = new EkadService($config);
                $result = $service->pushPatientInfo($patient, $bed);

                if ($result['success']) {
                    Log::info('EKad BedObserver: Auto-push successful', [
                        'bed_id' => $bed->id,
                        'patient_name' => $patient->name,
                        'mac' => $mapping->mac_address,
                    ]);
                } else {
                    Log::warning('EKad BedObserver: Auto-push failed', [
                        'bed_id' => $bed->id,
                        'error' => $result['message'],
                    ]);
                }
            } else {
                // Patient was removed (discharge/transfer OUT)
                Log::info('EKad BedObserver: Bed is now vacant (discharge/transfer out)', [
                    'bed_id' => $bed->id,
                    'bed_number' => $bed->bed_number,
                    'mac' => $mapping->mac_address,
                ]);

                // Push "vacant" info to E-Ink
                $service = new EkadService($config);
                $result = $service->pushToBed($mapping->mac_address, [
                    'mrn' => '-',
                    'patient_name' => 'VACANT',
                    'diet_type' => '-',
                    'doctor' => '-',
                    'nurse' => '-',
                    'anaesthetist' => '-',
                ]);

                if ($result['success']) {
                    Log::info('EKad BedObserver: Vacant push successful', [
                        'bed_id' => $bed->id,
                        'mac' => $mapping->mac_address,
                    ]);
                } else {
                    Log::warning('EKad BedObserver: Vacant push failed', [
                        'bed_id' => $bed->id,
                        'error' => $result['message'],
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('EKad BedObserver: Error', [
                'bed_id' => $bed->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
