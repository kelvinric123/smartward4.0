<?php

namespace App\Observers;

use App\Models\PatientCareProvider;
use App\Models\EkadConfiguration;
use App\Models\EkadBedMapping;
use App\Services\EkadService;
use Illuminate\Support\Facades\Log;

class PatientCareProviderObserver
{
    /**
     * Handle the PatientCareProvider "created" event.
     */
    public function created(PatientCareProvider $careProvider): void
    {
        Log::info('EKad CareProviderObserver: Care provider created', [
            'care_provider_id' => $careProvider->id,
            'role' => $careProvider->role,
            'patient_id' => $careProvider->patient_id,
        ]);

        $this->handleCareProviderChange($careProvider, 'created');
    }

    /**
     * Handle the PatientCareProvider "updated" event.
     */
    public function updated(PatientCareProvider $careProvider): void
    {
        // Check if relevant fields changed
        $relevantChanges = $careProvider->wasChanged([
            'role',
            'consultant_id',
            'anaesthetist_id',
            'is_active',
        ]);

        if ($relevantChanges) {
            Log::info('EKad CareProviderObserver: Care provider updated with relevant changes', [
                'care_provider_id' => $careProvider->id,
                'role' => $careProvider->role,
                'changes' => $careProvider->getChanges(),
            ]);

            $this->handleCareProviderChange($careProvider, 'updated');
        }
    }

    /**
     * Handle the PatientCareProvider "deleted" event.
     */
    public function deleted(PatientCareProvider $careProvider): void
    {
        Log::info('EKad CareProviderObserver: Care provider deleted', [
            'care_provider_id' => $careProvider->id,
            'role' => $careProvider->role,
            'patient_id' => $careProvider->patient_id,
        ]);

        $this->handleCareProviderChange($careProvider, 'deleted');
    }

    /**
     * Handle care provider changes and push to E-Ink if applicable
     */
    protected function handleCareProviderChange(PatientCareProvider $careProvider, string $event): void
    {
        try {
            // Only trigger for referring or consulting doctors (potential anaesthetists)
            if (
                !in_array($careProvider->role, [
                    PatientCareProvider::ROLE_REFERRING,
                    PatientCareProvider::ROLE_CONSULTING
                ])
            ) {
                Log::debug('EKad CareProviderObserver: Role not relevant for anaesthetist, skipping', [
                    'role' => $careProvider->role,
                ]);
                return;
            }

            // Check if the provider is linked to an anaesthetist
            $isAnaesthetist = $careProvider->anaesthetist_id !== null;

            // Or check if the consultant has anaesthetist specialty
            if (!$isAnaesthetist && $careProvider->consultant_id) {
                $consultant = $careProvider->consultant;
                // You can add additional logic here to check if consultant is an anaesthetist
                // based on specialty or other criteria
            }

            // Get the patient
            $patient = $careProvider->patient;
            if (!$patient) {
                Log::debug('EKad CareProviderObserver: Patient not found, skipping');
                return;
            }

            // Check if patient has a bed
            if (!$patient->bed) {
                Log::debug('EKad CareProviderObserver: Patient has no bed assignment, skipping', [
                    'patient_id' => $patient->id,
                ]);
                return;
            }

            // Get EKad configuration
            $config = EkadConfiguration::getActive();
            if (!$config || !$config->is_active || !$config->auto_push_enabled) {
                Log::debug('EKad CareProviderObserver: Auto-push not enabled, skipping');
                return;
            }

            // Check if bed has E-Ink mapping
            $mapping = EkadBedMapping::getForBed($patient->bed->id);
            if (!$mapping) {
                Log::debug('EKad CareProviderObserver: No E-Ink mapping for bed, skipping', [
                    'bed_id' => $patient->bed->id,
                ]);
                return;
            }

            $service = new EkadService($config);

            Log::info('EKad CareProviderObserver: Pushing patient info update (care provider change)', [
                'patient_id' => $patient->id,
                'bed_number' => $patient->bed->bed_number,
                'care_provider_id' => $careProvider->id,
                'role' => $careProvider->role,
                'event' => $event,
                'is_anaesthetist' => $isAnaesthetist,
            ]);

            $result = $service->pushPatientInfo($patient, $patient->bed, [], "Care Provider Updated ({$event})");

            if ($result['success']) {
                Log::info('EKad CareProviderObserver: Auto-push successful');
            } else {
                Log::warning('EKad CareProviderObserver: Auto-push failed', ['error' => $result['message'] ?? 'Unknown error']);
            }

        } catch (\Exception $e) {
            Log::error('EKad CareProviderObserver: Auto-push error', [
                'error' => $e->getMessage(),
                'care_provider_id' => $careProvider->id ?? null,
            ]);
        }
    }
}
