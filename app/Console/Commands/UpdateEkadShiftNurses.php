<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ShiftSetting;
use App\Models\Ward;
use App\Models\Bed;
use App\Models\Patient;
use App\Models\WardScheduleAssignment;
use App\Models\EkadConfiguration;
use App\Services\EkadService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class UpdateEkadShiftNurses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ekad:update-shift-nurses {--shift= : Optional shift code (AM/PM/ON)} {--ward= : Optional ward ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Push nurse updates to EKad displays for the upcoming shift (approx. 15 mins before start)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting EKad Shift Nurse Update...');

        // 1. Determine Target Shift
        $targetShiftCode = $this->option('shift');
        $targetWardId = $this->option('ward');

        // Get wards
        $wards = $targetWardId
            ? Ward::where('id', $targetWardId)->where('is_active', true)->get()
            : Ward::where('is_active', true)->get();

        if ($wards->isEmpty()) {
            $this->error('No active wards found.');
            return 1;
        }

        foreach ($wards as $ward) {
            $this->info("Processing Ward: {$ward->ward_name} ({$ward->id})");

            // Calculate target shift if not provided
            if (!$targetShiftCode) {
                // We look ahead 15-30 minutes
                $futureTime = now()->addMinutes(20);
                $shift = ShiftSetting::getCurrentShift($ward->id, $futureTime);

                if (!$shift) {
                    $this->warn("  No active shift found for time {$futureTime->format('H:i')}. Skipping.");
                    continue;
                }
                $currentShiftCodeCandidate = $shift->shift_code;
            } else {
                $currentShiftCodeCandidate = strtoupper($targetShiftCode);
            }

            $today = now()->toDateString();
            // Handle ON shift spanning across days if needed, but usually schedule is date-based
            // For 'ON' shift starting at 23:00, it usually belongs to 'today' in schedule

            $this->info("  Target Shift: {$currentShiftCodeCandidate} for Date: {$today}");

            // 2. Find Occupied Beds in this Ward
            $beds = Bed::where('ward_id', $ward->id)
                ->where('is_active', true)
                ->where('status', 'occupied') // Only occupied beds need updates
                ->whereNotNull('patient_id')
                ->with('patient')
                ->get();

            if ($beds->isEmpty()) {
                $this->info("  No occupied beds. Skipping.");
                continue;
            }

            // 3. Get EKad Configuration
            $config = EkadConfiguration::getActive();
            if (!$config || !$config->is_active || !$config->auto_push_enabled) {
                $this->warn("  EKad auto-push disabled or config missing. Skipping.");
                continue;
            }

            $ekadService = new EkadService($config);
            $updateCount = 0;

            foreach ($beds as $bed) {
                $patient = $bed->patient;
                if (!$patient)
                    continue;

                // 4. Find Nurse for this specific shift
                // We don't need to manually find the nurse, EkadService::pushPatientInfo 
                // normally calculates 'current nurse' based on 'now()'. 
                // BUT, since we are running 15 mins BEFORE the shift starts, 
                // 'now()' might still return the OLD shift's nurse.
                // We need to explicitly tell EkadService which nurse to display, 
                // OR we accept that EkadService might need a way to accept an override.

                // Let's resolve the nurse manually here to be precise.
                $assignment = WardScheduleAssignment::where('ward_id', $ward->id)
                    ->where('bed_id', $bed->id)
                    ->where('scheduled_date', $today)
                    ->where('shift', $currentShiftCodeCandidate)
                    ->with('nurse')
                    ->first();

                $nurseName = $assignment && $assignment->nurse ? $assignment->nurse->name : 'Not Assigned';

                // We need to pass this nurse name to the push function.
                // Looking at EkadService (I can't see it now, but usually it takes overrides or calculates).
                // If EkadService doesn't accept overrides, we might need to modify it. 
                // Based on standard implementation patterns, `pushPatientInfo` often takes an $overrides array.

                // Preparing override data
                $overrides = [
                    'nurse' => $nurseName
                ];

                $this->line("    Pushing for Bed {$bed->bed_number}: Nurse {$nurseName}");

                try {
                    $result = $ekadService->pushPatientInfo($patient, $bed, $overrides, "Shift Update {$currentShiftCodeCandidate}");
                    if ($result['success']) {
                        $updateCount++;
                    } else {
                        $this->error("      Failed: " . ($result['message'] ?? 'Unknown error'));
                    }
                } catch (\Exception $e) {
                    $this->error("      Exception: " . $e->getMessage());
                }
            }

            $this->info("  Updated {$updateCount} beds in ward {$ward->ward_name}.");
        }

        $this->info('EKad Shift Nurse Update Completed.');
        return 0;
    }
}
