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
        // DISABLED: EKAD logic moved to BedObserver and Controllers (Field Triggers)
        // This prevents duplicate pushes and race conditions.
        /*
        try {
            // Check if patient is admitted
            if (!$patient->isAdmitted()) {
                Log::debug('EKad Observer: Patient not admitted, skipping', [
                    'patient_id' => $patient->id,
                    'status' => $patient->status,
                ]);
                return;
            }

            // ... (rest of the logic commented out) ...

            // Get EKad configuration
            $config = EkadConfiguration::getActive();
            if (!$config) {
                return; 
            }
            // ...
        } catch (\Exception $e) {
            // ...
        }
        */
        return;
    }
}
