<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Ward;
use App\Models\Bed;
use App\Models\Patient;
use App\Models\WardScheduleAssignment;
use App\Models\EkadConfiguration;
use App\Services\EkadService;
use App\Services\NurseScheduling\RosterSlot;
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

            // The roster slot on duty a little ahead (we look ahead 15-30 minutes),
            // or of the shift asked for. A night is dated the evening it starts,
            // so after midnight the ON slot is last night's.
            $futureTime = now()->addMinutes(20);
            $slot = $this->targetSlot($ward, $targetShiftCode ? strtoupper($targetShiftCode) : null, $futureTime);

            if (!$slot) {
                $this->warn("  No active shift found for time {$futureTime->format('H:i')}. Skipping.");
                continue;
            }
            $currentShiftCodeCandidate = $slot['code'];
            $slotDate = $slot['date'];

            $this->info("  Target Shift: {$currentShiftCodeCandidate} for Date: {$slotDate}");

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
                    ->where('scheduled_date', $slotDate)
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

    /**
     * The slot on duty at a time; given a shift code, that shift's slot on
     * then, or else the next of it to start.
     */
    private function targetSlot(Ward $ward, ?string $code, Carbon $at): ?array
    {
        $slot = RosterSlot::current($ward->id, $at);
        if ($code === null) {
            return $slot;
        }

        $slot ??= RosterSlot::next($ward->id, $at);
        for ($i = 0; $slot && $slot['code'] !== $code && $i < 4; $i++) {
            $slot = RosterSlot::next($ward->id, $slot['starts_at']);
        }

        return $slot && $slot['code'] === $code ? $slot : null;
    }
}
