<?php

namespace App\Observers;

use App\Models\Patient;
use App\Models\EkadConfiguration;
use App\Models\EkadBedMapping;
use App\Services\EkadService;
use Illuminate\Support\Facades\Log;

class PatientObserver
{
    /**
     * Handle the Patient "created" event.
     */
    public function created(Patient $patient): void
    {
        $this->handlePatientChange($patient, 'created');
    }

    /**
     * Handle the Patient "updated" event.
     */
    public function updated(Patient $patient): void
    {
        // Check if relevant fields changed
        $relevantChanges = $patient->wasChanged([
            'name',
            'status',
            'bed_number',
            'ward_id',
            'consultant_id',
            'nurse_id',
            'anaesthetist_id',
            'diet_types',
            'mrn',
        ]);

        if ($relevantChanges) {
            $this->handlePatientChange($patient, 'updated');
        }
    }

    /**
     * Handle patient changes and push to E-Ink if applicable
     */
    protected function handlePatientChange(Patient $patient, string $event): void
    {
        try {
            // Check if patient is admitted
            if (!$patient->isAdmitted()) {
                return;
            }

            // Get EKad configuration
            $config = EkadConfiguration::getActive();
            if (!$config || !$config->is_active || !$config->auto_push_enabled) {
                return;
            }

            // Get patient's bed
            $bed = $patient->bed;
            if (!$bed) {
                return;
            }

            // Check if bed has E-Ink mapping
            $mapping = EkadBedMapping::getForBed($bed->id);
            if (!$mapping) {
                return;
            }

            // Push to E-Ink display
            $service = new EkadService($config);
            $result = $service->pushPatientInfo($patient, $bed);

            if ($result['success']) {
                Log::info('EKad: Auto-push successful', [
                    'event' => $event,
                    'patient_id' => $patient->id,
                    'patient_name' => $patient->name,
                    'bed_id' => $bed->id,
                    'mac' => $mapping->mac_address,
                ]);
            } else {
                Log::warning('EKad: Auto-push failed', [
                    'event' => $event,
                    'patient_id' => $patient->id,
                    'error' => $result['message'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('EKad: Auto-push error', [
                'event' => $event,
                'patient_id' => $patient->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
