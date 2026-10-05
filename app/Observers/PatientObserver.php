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
            'isolation_type',
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
            // Only push EKAD updates for admitted patients
            // Prebook patients are excluded to prevent issues with E-Ink display
            if ($patient->status !== Patient::STATUS_ADMITTED) {
                Log::debug('EKad PatientObserver: Patient not admitted, skipping', [
                    'patient_id' => $patient->id,
                    'status' => $patient->status,
                ]);
                return;
            }

            // Check if patient is admitted and has a bed
            if (!$patient->bed) {
                Log::debug('EKad PatientObserver: Patient has no bed assignment, skipping', [
                    'patient_id' => $patient->id,
                    'mrn' => $patient->mrn,
                ]);
                return;
            }

            // Get EKad configuration
            $config = EkadConfiguration::getActive();
            if (!$config || !$config->is_active || !$config->auto_push_enabled) {
                Log::debug('EKad PatientObserver: Auto-push not enabled, skipping');
                return;
            }

            // Check if bed has E-Ink mapping
            $mapping = EkadBedMapping::getForBed($patient->bed->id);
            if (!$mapping) {
                Log::debug('EKad PatientObserver: No E-Ink mapping for bed, skipping', [
                    'bed_id' => $patient->bed->id,
                ]);
                return;
            }

            $service = new EkadService($config);

            Log::info('EKad PatientObserver: Pushing patient info update', [
                'patient_id' => $patient->id,
                'bed_number' => $patient->bed->bed_number,
                'event' => $event,
                'changes' => $patient->getChanges(),
            ]);

            $result = $service->pushPatientInfo($patient, $patient->bed, [], "Patient Info Updated ({$event})");

            if ($result['success']) {
                Log::info('EKad PatientObserver: Auto-push successful');
            } else {
                Log::warning('EKad PatientObserver: Auto-push failed', ['error' => $result['message'] ?? 'Unknown error']);
            }

        } catch (\Exception $e) {
            Log::error('EKad PatientObserver: Auto-push error', [
                'error' => $e->getMessage(),
                'patient_id' => $patient->id ?? null,
            ]);
        }
    }
}
