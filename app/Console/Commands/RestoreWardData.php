<?php

namespace App\Console\Commands;

use App\Models\Bed;
use App\Models\Patient;
use App\Models\Ward;
use App\Models\AdtMessageLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RestoreWardData extends Command
{
    protected $signature = 'ward:restore 
                            {--diagnose : Show orphaned patients and missing data (safe, no changes)}
                            {--fix : Actually perform the restoration (will modify data)}
                            {--ward-id= : Target ward ID to restore patients into}
                            {--dry-run : Show what --fix would do without actually doing it}';

    protected $description = 'Diagnose and restore ward data after accidental ward deletion';

    public function handle()
    {
        if ($this->option('diagnose') || (!$this->option('fix') && !$this->option('ward-id'))) {
            return $this->diagnose();
        }

        if ($this->option('fix')) {
            return $this->fix();
        }

        $this->error('Please specify --diagnose or --fix');
        return 1;
    }

    private function diagnose()
    {
        $this->info('=== WARD DATA RECOVERY DIAGNOSIS ===');
        $this->newLine();

        // 1. Show all wards
        $this->info('📋 Current Wards:');
        $wards = Ward::all();
        if ($wards->isEmpty()) {
            $this->warn('  No wards found!');
        } else {
            $headers = ['ID', 'Ward Code', 'Ward Name', 'Hospital ID', 'Capacity', 'Active', 'Beds Count', 'Created At'];
            $rows = $wards->map(fn($w) => [
                $w->id,
                $w->ward_code,
                $w->ward_name,
                $w->hospital_id,
                $w->capacity,
                $w->is_active ? 'Yes' : 'No',
                Bed::where('ward_id', $w->id)->count(),
                $w->created_at,
            ]);
            $this->table($headers, $rows);
        }

        // 2. Find orphaned patients (ward_id = NULL but still active/admitted)
        $this->newLine();
        $this->info('🔍 Orphaned Patients (ward_id = NULL, still active):');
        $orphanedPatients = Patient::whereNull('ward_id')
            ->where('is_active', true)
            ->whereIn('status', ['admitted', 'pending_discharge', 'prebook', 'prebook_pending'])
            ->get();

        if ($orphanedPatients->isEmpty()) {
            $this->info('  ✅ No orphaned active patients found.');
        } else {
            $this->warn("  ⚠️  Found {$orphanedPatients->count()} orphaned patient(s):");
            $headers = ['ID', 'Name', 'MRN', 'Status', 'Bed Number', 'Ward ID', 'Admitted At'];
            $rows = $orphanedPatients->map(fn($p) => [
                $p->id,
                $p->name,
                $p->mrn,
                $p->status,
                $p->bed_number ?? 'NULL',
                $p->ward_id ?? 'NULL',
                $p->admitted_at,
            ]);
            $this->table($headers, $rows);
        }

        // 3. Also show patients that are in admitted status but have ward_id set
        // (in case the user re-added the ward and some patients got linked)
        $this->newLine();
        $this->info('📊 All Active Admitted/Pending Patients (across all wards):');
        $activePatients = Patient::where('is_active', true)
            ->whereIn('status', ['admitted', 'pending_discharge', 'prebook', 'prebook_pending'])
            ->get();

        if ($activePatients->isEmpty()) {
            $this->warn('  No active patients found at all!');
        } else {
            $headers = ['ID', 'Name', 'MRN', 'Status', 'Bed Number', 'Ward ID', 'Admitted At'];
            $rows = $activePatients->map(fn($p) => [
                $p->id,
                $p->name,
                $p->mrn,
                $p->status,
                $p->bed_number ?? 'NULL',
                $p->ward_id ?? 'NULL',
                $p->admitted_at,
            ]);
            $this->table($headers, $rows);
        }

        // 4. Check beds for each ward
        $this->newLine();
        $this->info('🛏️  Bed Status per Ward:');
        foreach ($wards as $ward) {
            $beds = Bed::where('ward_id', $ward->id)->get();
            $this->info("  Ward: {$ward->ward_name} (ID: {$ward->id}) - {$beds->count()} beds");
            if ($beds->isEmpty()) {
                $this->warn("    ⚠️  No beds defined for this ward!");
            } else {
                foreach ($beds as $bed) {
                    $patientInfo = $bed->patient_id ? "Patient #{$bed->patient_id}" : 'Empty';
                    $this->line("    Bed {$bed->bed_number} ({$bed->bed_display_name}) - Status: {$bed->status} - {$patientInfo}");
                }
            }
        }

        // 5. Check ADT message logs for recent admissions (these survive ward deletion)
        $this->newLine();
        $this->info('📨 Recent ADT Messages (last 7 days):');
        $adtLogs = AdtMessageLog::where('created_at', '>=', now()->subDays(7))
            ->whereIn('event_type', ['A01', 'A02', 'A08'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        if ($adtLogs->isEmpty()) {
            $this->info('  No recent ADT admission messages found.');
        } else {
            $headers = ['ID', 'Event', 'Patient MRN', 'Patient Name', 'Location', 'Status', 'Created At'];
            $rows = $adtLogs->map(fn($l) => [
                $l->id,
                $l->event_type,
                $l->patient_mrn,
                $l->patient_name,
                $l->assigned_location,
                $l->status,
                $l->created_at,
            ]);
            $this->table($headers, $rows);
        }

        // 6. Summary & Recommendations
        $this->newLine();
        $this->info('=== RECOMMENDATIONS ===');
        
        if ($orphanedPatients->count() > 0) {
            $this->warn("1. {$orphanedPatients->count()} orphaned patients need to be re-linked to a ward");
            $this->info("   Run: php artisan ward:restore --fix --ward-id=<WARD_ID>");
            
            // Show unique bed numbers from orphaned patients
            $bedNumbers = $orphanedPatients->pluck('bed_number')->filter()->unique()->sort()->values();
            if ($bedNumbers->isNotEmpty()) {
                $this->info("   Orphaned patients have these bed numbers: " . $bedNumbers->implode(', '));
                $this->info("   The --fix command will auto-create these beds if missing.");
            }
        }

        foreach ($wards as $ward) {
            $bedCount = Bed::where('ward_id', $ward->id)->count();
            if ($bedCount === 0) {
                $this->warn("2. Ward '{$ward->ward_name}' (ID: {$ward->id}) has NO beds. They need to be recreated.");
            }
        }

        return 0;
    }

    private function fix()
    {
        $wardId = $this->option('ward-id');
        $dryRun = $this->option('dry-run');

        if (!$wardId) {
            $this->error('Please specify --ward-id=<ID> for the target ward.');
            $this->info('Run --diagnose first to see available ward IDs.');
            return 1;
        }

        $ward = Ward::find($wardId);
        if (!$ward) {
            $this->error("Ward ID {$wardId} not found!");
            return 1;
        }

        $this->info("=== RESTORING DATA FOR WARD: {$ward->ward_name} (ID: {$ward->id}) ===");
        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }
        $this->newLine();

        // Step 1: Find orphaned patients
        $orphanedPatients = Patient::whereNull('ward_id')
            ->where('is_active', true)
            ->whereIn('status', ['admitted', 'pending_discharge', 'prebook', 'prebook_pending'])
            ->get();

        if ($orphanedPatients->isEmpty()) {
            $this->info('No orphaned patients found. Checking if patients need bed re-linking...');
            
            // Also check patients that have the ward but beds might be missing
            $wardPatients = Patient::where('ward_id', $wardId)
                ->where('is_active', true)
                ->whereIn('status', ['admitted', 'pending_discharge', 'prebook'])
                ->get();
            
            if ($wardPatients->isNotEmpty()) {
                $this->info("Found {$wardPatients->count()} patients already in this ward. Ensuring beds exist...");
                $orphanedPatients = $wardPatients; // Use these for bed creation
            } else {
                $this->warn('No patients to restore. Are you sure the ward ID is correct?');
                return 0;
            }
        }

        $this->info("Found {$orphanedPatients->count()} patient(s) to process.");
        $this->newLine();

        DB::beginTransaction();

        try {
            $restoredCount = 0;
            $bedsCreated = 0;

            foreach ($orphanedPatients as $patient) {
                $bedNumber = $patient->bed_number;
                
                if (!$bedNumber) {
                    $this->warn("  ⚠️  Patient #{$patient->id} ({$patient->name}) has no bed_number, skipping.");
                    continue;
                }

                // Step 2: Re-link patient to ward
                if ($patient->ward_id === null) {
                    $this->info("  🔗 Re-linking patient #{$patient->id} ({$patient->name}, MRN: {$patient->mrn}) to ward {$ward->ward_name}");
                    $this->info("     Status: {$patient->status}, Bed: {$bedNumber}");
                    
                    if (!$dryRun) {
                        $patient->update(['ward_id' => $wardId]);
                    }
                    $restoredCount++;
                }

                // Step 3: Ensure bed exists
                $existingBed = Bed::where('ward_id', $wardId)
                    ->where('bed_number', $bedNumber)
                    ->first();

                if (!$existingBed) {
                    $bedId = $ward->ward_code . '-' . $bedNumber;
                    $this->info("  🛏️  Creating bed: {$bedNumber} (bed_id: {$bedId})");
                    
                    if (!$dryRun) {
                        $existingBed = Bed::create([
                            'ward_id' => $wardId,
                            'bed_number' => $bedNumber,
                            'bed_id' => $bedId,
                            'bed_display_name' => 'Bed ' . $bedNumber,
                            'status' => $patient->status === 'prebook' ? 'reserved' : 'occupied',
                            'patient_id' => $patient->id,
                            'is_active' => true,
                        ]);
                    }
                    $bedsCreated++;
                } else {
                    // Bed exists, update patient assignment
                    $this->info("  ✅ Bed {$bedNumber} already exists, updating patient assignment");
                    if (!$dryRun) {
                        $bedStatus = $patient->status === 'prebook' ? 'reserved' : 'occupied';
                        $existingBed->update([
                            'status' => $bedStatus,
                            'patient_id' => $patient->id,
                        ]);
                    }
                }
            }

            // Step 4: Create any additional empty beds up to ward capacity
            $currentBedCount = $dryRun 
                ? Bed::where('ward_id', $wardId)->count() + $bedsCreated
                : Bed::where('ward_id', $wardId)->count();
            
            if ($currentBedCount < $ward->capacity) {
                $this->newLine();
                $this->info("📊 Ward capacity is {$ward->capacity}, currently have {$currentBedCount} beds.");
                
                if ($this->confirm("Do you want to create additional empty beds up to capacity?", false)) {
                    $existingBedNumbers = Bed::where('ward_id', $wardId)->pluck('bed_number')->toArray();
                    
                    for ($i = 1; $i <= $ward->capacity; $i++) {
                        $padded = str_pad($i, 2, '0', STR_PAD_LEFT);
                        $bedNumber = 'B' . $padded;
                        
                        if (!in_array($bedNumber, $existingBedNumbers)) {
                            $bedId = $ward->ward_code . '-' . $bedNumber;
                            
                            // Check if bed_id already exists (unique constraint)
                            if (!Bed::where('bed_id', $bedId)->exists()) {
                                $this->info("  🛏️  Creating empty bed: {$bedNumber}");
                                if (!$dryRun) {
                                    Bed::create([
                                        'ward_id' => $wardId,
                                        'bed_number' => $bedNumber,
                                        'bed_id' => $bedId,
                                        'bed_display_name' => 'Bed ' . $bedNumber,
                                        'status' => 'available',
                                        'is_active' => true,
                                    ]);
                                }
                                $bedsCreated++;
                            }
                        }
                    }
                }
            }

            if ($dryRun) {
                DB::rollBack();
                $this->newLine();
                $this->warn('🔍 DRY RUN COMPLETE - No changes were made.');
            } else {
                DB::commit();
                $this->newLine();
                $this->info('✅ RESTORATION COMPLETE!');
            }

            $this->info("  Patients re-linked: {$restoredCount}");
            $this->info("  Beds created: {$bedsCreated}");
            $this->newLine();
            $this->info('The ward dashboard should now show the correct patient assignments.');
            $this->info('The syncBedsWithPatients() method will handle final bed status sync on next dashboard load.');

            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Restoration failed: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }
}
