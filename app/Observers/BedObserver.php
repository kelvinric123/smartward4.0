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
     */
    public function updated(Bed $bed): void
    {
        // Check if relevant fields changed
        $relevantChanges = $bed->wasChanged([
            'patient_id',
            'status',
        ]);

        if ($relevantChanges) {
            Log::info('EKad BedObserver: Bed updated', [
                'bed_id' => $bed->id,
                'bed_number' => $bed->bed_number,
                'changes' => $bed->getChanges(),
            ]);
            $this->handleBedChange($bed, 'updated');
        }
    }

    /**
     * Handle bed changes and push to E-Ink if applicable
     */
    protected function handleBedChange(Bed $bed, string $event): void
    {
        try {
            // Get EKad configuration
            $config = EkadConfiguration::getActive();
            if (!$config || !$config->is_active || !$config->auto_push_enabled) {
                return;
            }

            // Check if bed has E-Ink mapping
            $mapping = EkadBedMapping::getForBed($bed->id);
            if (!$mapping) {
                return;
            }

            // If patient_id was cleared (Discharge/Transfer out), push clear screen
            // We check the 'patient_id' attribute explicitly. 
            // If it's currently null, it means the bed is now empty.
            if (!$bed->patient_id) {

                // We might want to push a "Clear" or "Available" status.
                // The EkadService pushPatientInfo usually requires a Patient object.
                // For a cleared bed, we might need a specific "clear" method or pass dummy/placeholder data.
                // However, the AdtApiController and WardDashboardController are ALREADY pushing the clear screen 
                // explicitly *before* the bed is updated to null.
                // So this Observer might race or start redundant pushes.

                // If the controller already pushed "Clear", this might be redundant but safe if it pushes "-" placeholders.
                // But pushPatientInfo requires a Patient object. If patient_id is null, we don't have a patient unique to this bed anymore.

                // Strategy: Only trigger if there IS a patient (Admit/Transfer In).
                // For Discharge (Clean), the Controllers handle it explicitly because they still have the Patient context.
                // OR: We can try to retrieve the *previous* patient if we wanted to verify, but that's complex.

                // Let's log it for now. If we want to support "Available" screens, we'd need a specific payload not tied to a Patient model.
                Log::info('EKad BedObserver: Bed emptied. Skipping automatic push (handled by Controller discharge logic).');
                return;
            }

            // If there is a patient, push their info
            $patient = $bed->patient;
            if (!$patient) {
                Log::warning('EKad BedObserver: Bed has patient_id but relationship returned null', ['patient_id' => $bed->patient_id]);
                return;
            }

            Log::info('EKad BedObserver: Pushing patient info to E-Ink', [
                'patient_id' => $patient->id,
                'bed_number' => $bed->bed_number,
                'mac' => $mapping->mac_address,
            ]);

            $service = new EkadService($config);
            $result = $service->pushPatientInfo($patient, $bed);

            if ($result['success']) {
                Log::info('EKad BedObserver: Auto-push successful');
            } else {
                Log::warning('EKad BedObserver: Auto-push failed', ['error' => $result['message']]);
            }

        } catch (\Exception $e) {
            Log::error('EKad BedObserver: Auto-push error', ['error' => $e->getMessage()]);
        }
    }
}
