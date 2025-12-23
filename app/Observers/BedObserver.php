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

            // Check if bed has E-Ink mapping (quick check before service instantiation)
            $mapping = EkadBedMapping::getForBed($bed->id);
            if (!$mapping) {
                return;
            }

            $service = new EkadService($config);

            // Case 1: Bed is now Empty (Discharge)
            // We check if patient_id is null.
            if (!$bed->patient_id) {
                Log::info('EKad BedObserver: Bed emptied. Pushing Vacant status.', [
                    'bed_number' => $bed->bed_number,
                    'mac' => $mapping->mac_address,
                ]);

                $result = $service->pushVacant($bed, 'Bed Vacated');
            }
            // Case 2: Bed is Occupied (Admit / Transfer In)
            else {
                $patient = $bed->patient;
                if ($patient) {
                    Log::info('EKad BedObserver: Bed occupied/updated. Pushing Patient info.', [
                        'patient_id' => $patient->id,
                        'bed_number' => $bed->bed_number,
                        'mac' => $mapping->mac_address,
                    ]);

                    $result = $service->pushPatientInfo($patient, $bed, [], 'Bed Updated');
                } else {
                    Log::warning('EKad BedObserver: Bed has patient_id but relation failed to load', ['patient_id' => $bed->patient_id]);
                    return;
                }
            }

            if (isset($result) && $result['success']) {
                Log::info('EKad BedObserver: Auto-push successful');
            } else {
                Log::warning('EKad BedObserver: Auto-push failed', ['error' => $result['message'] ?? 'Unknown error']);
            }

        } catch (\Exception $e) {
            Log::error('EKad BedObserver: Auto-push error', ['error' => $e->getMessage()]);
        }
    }
}
