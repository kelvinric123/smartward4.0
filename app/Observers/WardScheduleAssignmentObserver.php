<?php

namespace App\Observers;

use App\Models\WardScheduleAssignment;
use App\Models\EkadConfiguration;
use App\Models\EkadBedMapping;
use App\Services\EkadService;
use App\Models\ShiftSetting;
use Illuminate\Support\Facades\Log;

class WardScheduleAssignmentObserver
{
    /**
     * Handle the WardScheduleAssignment "created" event.
     */
    public function created(WardScheduleAssignment $assignment): void
    {
        Log::info('EKad ScheduleObserver: Assignment created', [
            'assignment_id' => $assignment->id,
            'date' => $assignment->scheduled_date->toDateString(),
            'shift' => $assignment->shift,
            'bed_id' => $assignment->bed_id,
        ]);

        $this->handleScheduleChange($assignment, 'created');
    }

    /**
     * Handle the WardScheduleAssignment "updated" event.
     */
    public function updated(WardScheduleAssignment $assignment): void
    {
        Log::info('EKad ScheduleObserver: Assignment updated', [
            'assignment_id' => $assignment->id,
            'date' => $assignment->scheduled_date->toDateString(),
            'shift' => $assignment->shift,
            'bed_id' => $assignment->bed_id,
            'changes' => $assignment->getChanges(),
        ]);

        $this->handleScheduleChange($assignment, 'updated');
    }

    /**
     * Handle the WardScheduleAssignment "deleted" event.
     */
    public function deleted(WardScheduleAssignment $assignment): void
    {
        Log::info('EKad ScheduleObserver: Assignment deleted', [
            'assignment_id' => $assignment->id,
            'date' => $assignment->scheduled_date->toDateString(),
            'shift' => $assignment->shift,
            'bed_id' => $assignment->bed_id,
        ]);

        $this->handleScheduleChange($assignment, 'deleted');
    }

    /**
     * Handle schedule changes and push to E-Ink if applicable.
     */
    protected function handleScheduleChange(WardScheduleAssignment $assignment, string $event): void
    {
        // Only trigger if the assignment update is relevant to the CURRENT time or VERY SOON
        // Logic:
        // 1. If assignment is for TODAY, check if it's the current shift or future shift today
        // 2. We generally push updates for today to ensure screens are up to date
        // 3. We typically ignore past dates or far future dates (though future dates don't hurt, just bandwidth)

        try {
            $assignmentDate = $assignment->scheduled_date;
            $today = now()->toDateString();

            // If assignment is not for today, we might skip it unless we want to support pre-loading
            // For now, let's strictly update only if the change affects TODAY's display
            if ($assignmentDate->format('Y-m-d') !== $today) {
                // Special case: If it's ON shift for "yesterday" but we are in the early morning hours (before shift end)
                // However, our system generally treats dates as calendar dates.
                // Let's stick to: Update only if assignment is for TODAY.
                return;
            }

            // Get bed
            $bed = $assignment->bed;
            if (!$bed) {
                return;
            }

            // Check if bed has active patient/admitted
            $patient = $bed->patient; // Assuming bed->patient relationship exists and is active

            // If direct relationship is missing, try to find active patient in bed
            if (!$patient) {
                // Double check manually if relationship wasn't loaded or is tricky
                $patient = \App\Models\Patient::where('ward_id', $assignment->ward_id)
                    ->where('bed_number', $bed->bed_number)
                    ->where('is_active', true)
                    ->where('status', 'admitted')
                    ->first();
            }

            if (!$patient) {
                // No patient, nothing to show (E-Ink shows "Vacant" mainly, which doesn't need nurse name)
                return;
            }

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

            // Determine if the updated shift is the ACTIVE shift
            $currentShift = ShiftSetting::getCurrentShift($assignment->ward_id);
            if (!$currentShift) {
                // Fallback or skip
                return;
            }

            // If the changed assignment corresponds to the CURRENT active shift, update immediately.
            // Or if we want to be safe, just update regardless for today, as the EkadService::pushPatientInfo
            // logic will verify "Who is the nurse NOW?" internally or we pass the specific nurse.
            // *CRITICAL*: EkadService typically pulls the "Current Nurse" based on the schedule at the moment of generating payload.
            // So if we just trigger a push, `EkadService` will re-evaluate "Who is the nurse right now?".
            // If we changed the AM shift, but it's currently PM, re-pushing might not change the display (which is correct).
            // If we changed the PM shift and it IS PM, it will update.

            $service = new EkadService($config);

            Log::info('EKad ScheduleObserver: Pushing update due to schedule change', [
                'patient_id' => $patient->id,
                'bed_number' => $bed->bed_number,
            ]);

            $result = $service->pushPatientInfo($patient, $bed, [], "Schedule Updated ({$event})");

            if ($result['success']) {
                Log::info('EKad ScheduleObserver: Auto-push successful');
            } else {
                Log::warning('EKad ScheduleObserver: Auto-push failed', ['error' => $result['message'] ?? 'Unknown error']);
            }

        } catch (\Exception $e) {
            Log::error('EKad ScheduleObserver: Error', ['error' => $e->getMessage()]);
        }
    }
}
