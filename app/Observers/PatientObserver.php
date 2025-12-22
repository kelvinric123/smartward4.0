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
        Log::info('EKad Observer: Patient created', ['patient_id' => $patient->id, 'name' => $patient->name]);
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
            Log::info('EKad Observer: Patient updated with relevant changes', [
                'patient_id' => $patient->id,
                'name' => $patient->name,
                'changes' => $patient->getChanges(),
            ]);
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
                Log::debug('EKad Observer: Patient not admitted, skipping', [
                    'patient_id' => $patient->id,
                    'status' => $patient->status,
                ]);
                return;
            }

            // Get EKad configuration
            $config = EkadConfiguration::getActive();
            if (!$config) {
                Log::debug('EKad Observer: No active configuration found');
                return;
            }

            if (!$config->is_active) {
                Log::debug('EKad Observer: Configuration is not active');
                return;
            }

            if (!$config->auto_push_enabled) {
                Log::debug('EKad Observer: Auto-push is disabled');
                return;
            }

            // Get patient's bed
            $bed = $patient->bed;
            if (!$bed) {
                Log::debug('EKad Observer: Patient has no bed assigned', [
                    'patient_id' => $patient->id,
                ]);
                return;
            }

            // Check if bed has E-Ink mapping
            $mapping = EkadBedMapping::getForBed($bed->id);
            if (!$mapping) {
                Log::debug('EKad Observer: No E-Ink mapping for bed', [
                    'bed_id' => $bed->id,
                    'bed_number' => $bed->bed_number,
                ]);
                return;
            }

            // Push to E-Ink display
            Log::info('EKad Observer: Pushing patient info to E-Ink', [
                'patient_id' => $patient->id,
                'patient_name' => $patient->name,
                'bed_id' => $bed->id,
                'mac' => $mapping->mac_address,
            ]);

            $service = new EkadService($config);
            $result = $service->pushPatientInfo($patient, $bed);

            if ($result['success']) {
                Log::info('EKad Observer: Auto-push successful', [
                    'event' => $event,
                    'patient_id' => $patient->id,
                    'patient_name' => $patient->name,
                    'bed_id' => $bed->id,
                    'mac' => $mapping->mac_address,
                ]);
            } else {
                Log::warning('EKad Observer: Auto-push failed', [
                    'event' => $event,
                    'patient_id' => $patient->id,
                    'error' => $result['message'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('EKad Observer: Auto-push error', [
                'event' => $event,
                'patient_id' => $patient->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
