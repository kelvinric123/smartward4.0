<?php

namespace App\Console\Commands;

use App\Models\Bed;
use App\Models\Patient;
use App\Models\Ward;
use App\Models\AdtMessageLog;
use App\Models\AdtConfiguration;
use App\Models\AdmissionLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RestoreWardData extends Command
{
    protected $signature = 'ward:restore 
                            {--diagnose : Show orphaned patients and missing data (safe, no changes)}
                            {--fix : Actually perform the restoration (will modify data)}
                            {--fix-auto : Auto-detect wards from bed numbers and restore all orphaned patients}
                            {--fix-from-adt : Replay ADT messages to restore admissions with full patient data}
                            {--ward-id= : Target ward ID to restore patients into}
                            {--dry-run : Show what --fix would do without actually doing it}
                            {--create-beds-for-ward= : Create missing beds for a specific ward up to capacity}';

    protected $description = 'Diagnose and restore ward data after accidental ward deletion';

    public function handle()
    {
        if ($this->option('fix-from-adt')) {
            return $this->fixFromAdt();
        }

        if ($this->option('diagnose') || (!$this->option('fix') && !$this->option('fix-auto') && !$this->option('ward-id') && !$this->option('create-beds-for-ward'))) {
            return $this->diagnose();
        }

        if ($this->option('create-beds-for-ward')) {
            return $this->createBedsForWard($this->option('create-beds-for-ward'));
        }

        if ($this->option('fix-auto')) {
            return $this->fixAuto();
        }

        if ($this->option('fix')) {
            return $this->fix();
        }

        $this->error('Please specify --diagnose, --fix, --fix-auto, or --fix-from-adt');
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

        // 6. Auto-detect ward mapping from bed numbers
        $this->newLine();
        $this->info('🗺️  Auto-detected Ward Mapping (from bed number prefix):');
        $wardMapping = $this->buildWardMapping($orphanedPatients);
        if (!empty($wardMapping)) {
            foreach ($wardMapping as $prefix => $info) {
                $this->info("  Bed prefix '{$prefix}xx' → Ward: {$info['ward']->ward_name} (ID: {$info['ward']->id}) — {$info['count']} patient(s)");
            }
            $this->newLine();
            $this->info('💡 To auto-restore all orphaned patients to their correct wards:');
            $this->info('   Run: php artisan ward:restore --fix-auto');
            $this->info('   Or dry-run first: php artisan ward:restore --fix-auto --dry-run');
        }

        // 7. Summary & Recommendations
        $this->newLine();
        $this->info('=== RECOMMENDATIONS ===');
        
        if ($orphanedPatients->count() > 0) {
            $this->warn("1. {$orphanedPatients->count()} orphaned patients need to be re-linked to a ward");
            $this->info("   Run: php artisan ward:restore --fix-auto");
            
            $bedNumbers = $orphanedPatients->pluck('bed_number')->filter()->unique()->sort()->values();
            if ($bedNumbers->isNotEmpty()) {
                $this->info("   Orphaned patients have these bed numbers: " . $bedNumbers->implode(', '));
                $this->info("   The --fix-auto command will auto-detect wards and create beds if missing.");
            }
        }

        foreach ($wards as $ward) {
            $bedCount = Bed::where('ward_id', $ward->id)->count();
            if ($bedCount < $ward->capacity) {
                $missing = $ward->capacity - $bedCount;
                $this->warn("2. Ward '{$ward->ward_name}' (ID: {$ward->id}) has {$bedCount}/{$ward->capacity} beds ({$missing} missing).");
                $this->info("   Run: php artisan ward:restore --create-beds-for-ward={$ward->id}");
            }
        }

        return 0;
    }

    /**
     * Build a mapping of bed number prefix → ward based on existing ward codes.
     * E.g., bed "D601" → prefix "D6" → ward with code containing "D6" or "WD6".
     */
    private function buildWardMapping($orphanedPatients)
    {
        $wards = Ward::all();
        $mapping = [];

        // Build prefix-to-ward lookup from ward codes
        // Ward codes like "WWD6" → beds are "D6xx", so we extract the meaningful part
        $wardByPrefix = [];
        foreach ($wards as $ward) {
            // Try to extract the ward identifier (e.g., "D6" from "WWD6")
            // Bed numbers are like "D601", "D417", "D522"
            // The prefix is the first 2-3 chars that match a ward
            $code = $ward->ward_code; // e.g., "WWD6"
            
            // Extract bed prefix from ward code: WWD6 → D6, WWD7 → D7, etc.
            if (preg_match('/W*([A-Z]\d+)/i', $code, $matches)) {
                $wardByPrefix[$matches[1]] = $ward;
            }
        }

        // Now match each orphaned patient's bed number to a ward
        foreach ($orphanedPatients as $patient) {
            $bedNumber = $patient->bed_number;
            if (!$bedNumber) continue;

            // Extract prefix from bed number: "D601" → "D6", "D417" → "D4"
            if (preg_match('/^([A-Za-z]+\d)/', $bedNumber, $matches)) {
                $prefix = strtoupper($matches[1]);
                
                if (isset($wardByPrefix[$prefix])) {
                    if (!isset($mapping[$prefix])) {
                        $mapping[$prefix] = [
                            'ward' => $wardByPrefix[$prefix],
                            'count' => 0,
                            'patients' => [],
                        ];
                    }
                    $mapping[$prefix]['count']++;
                    $mapping[$prefix]['patients'][] = $patient;
                }
            }
        }

        return $mapping;
    }

    /**
     * Auto-fix: detect the correct ward for each orphaned patient based on bed number prefix.
     */
    private function fixAuto()
    {
        $dryRun = $this->option('dry-run');

        $this->info('=== AUTO-RESTORE: Detecting wards from bed numbers ===');
        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }
        $this->newLine();

        // Get orphaned patients
        $orphanedPatients = Patient::whereNull('ward_id')
            ->where('is_active', true)
            ->whereIn('status', ['admitted', 'pending_discharge', 'prebook', 'prebook_pending'])
            ->get();

        if ($orphanedPatients->isEmpty()) {
            $this->info('✅ No orphaned patients found. Nothing to restore.');
            return 0;
        }

        $this->info("Found {$orphanedPatients->count()} orphaned patient(s).");

        // Build ward mapping
        $wardMapping = $this->buildWardMapping($orphanedPatients);
        
        if (empty($wardMapping)) {
            $this->error('Could not auto-detect any ward mapping from bed numbers!');
            $this->info('Use --fix --ward-id=<ID> to manually specify the target ward.');
            return 1;
        }

        // Show the plan
        $this->newLine();
        $this->info('📋 Restoration Plan:');
        $noWardPatients = [];
        foreach ($orphanedPatients as $patient) {
            $matched = false;
            foreach ($wardMapping as $prefix => $info) {
                if ($patient->bed_number && stripos($patient->bed_number, $prefix) === 0) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                $noWardPatients[] = $patient;
            }
        }

        foreach ($wardMapping as $prefix => $info) {
            $this->info("  {$prefix}xx → {$info['ward']->ward_name} (ID: {$info['ward']->id}): {$info['count']} patient(s)");
        }
        
        if (!empty($noWardPatients)) {
            $this->warn("  ⚠️  {" . count($noWardPatients) . "} patient(s) with no bed number or unmatched prefix (will be skipped)");
        }

        if (!$dryRun && !$this->confirm('Proceed with restoration?', true)) {
            $this->info('Aborted.');
            return 0;
        }

        $this->newLine();

        DB::beginTransaction();

        try {
            $totalRestored = 0;
            $totalBedsCreated = 0;
            $totalBedsUpdated = 0;

            foreach ($wardMapping as $prefix => $info) {
                $ward = $info['ward'];
                $patients = $info['patients'];

                $this->info("--- Processing Ward: {$ward->ward_name} (ID: {$ward->id}) ---");

                foreach ($patients as $patient) {
                    $bedNumber = $patient->bed_number;

                    // Re-link patient to ward
                    $this->info("  🔗 Patient #{$patient->id} ({$patient->name}) → Ward {$ward->ward_name}, Bed {$bedNumber} [status: {$patient->status}]");
                    
                    if (!$dryRun) {
                        $patient->update(['ward_id' => $ward->id]);
                    }
                    $totalRestored++;

                    // Ensure bed exists and is properly linked
                    $existingBed = Bed::where('ward_id', $ward->id)
                        ->where('bed_number', $bedNumber)
                        ->first();

                    if (!$existingBed) {
                        // Determine bed display name: extract the numeric part
                        $bedIndex = intval(preg_replace('/[^0-9]/', '', substr($bedNumber, strlen($prefix))));
                        $displayName = 'Bed ' . $bedIndex;
                        
                        $this->info("  🛏️  Creating bed: {$bedNumber} (display: {$displayName})");
                        
                        if (!$dryRun) {
                            Bed::create([
                                'ward_id' => $ward->id,
                                'bed_number' => $bedNumber,
                                'bed_id' => $bedNumber,
                                'bed_display_name' => $displayName,
                                'status' => in_array($patient->status, ['prebook', 'prebook_pending']) ? 'reserved' : 'occupied',
                                'patient_id' => $patient->id,
                                'is_active' => true,
                            ]);
                        }
                        $totalBedsCreated++;
                    } else {
                        // Bed exists - update patient assignment if the bed is empty or mismatched
                        $bedStatus = in_array($patient->status, ['prebook', 'prebook_pending']) ? 'reserved' : 'occupied';
                        
                        if ($existingBed->patient_id != $patient->id) {
                            $this->info("  ✅ Bed {$bedNumber} exists → assigning patient #{$patient->id} [status: {$bedStatus}]");
                            if (!$dryRun) {
                                $existingBed->update([
                                    'status' => $bedStatus,
                                    'patient_id' => $patient->id,
                                ]);
                            }
                            $totalBedsUpdated++;
                        } else {
                            $this->info("  ✅ Bed {$bedNumber} already correctly assigned to patient #{$patient->id}");
                        }
                    }
                }
            }

            // Handle patients with no bed number - just re-link to a default ward or skip
            foreach ($noWardPatients as $patient) {
                $this->warn("  ⚠️  Skipping patient #{$patient->id} ({$patient->name}) — no bed number or unmatched prefix");
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

            $this->info("  Patients re-linked to wards: {$totalRestored}");
            $this->info("  New beds created: {$totalBedsCreated}");
            $this->info("  Existing beds updated: {$totalBedsUpdated}");
            $this->info("  Skipped (no bed): " . count($noWardPatients));
            $this->newLine();
            $this->info('The ward dashboard should now show the correct patient assignments.');

            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Restoration failed: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    /**
     * Create missing beds for a specific ward up to its capacity.
     */
    private function createBedsForWard($wardId)
    {
        $dryRun = $this->option('dry-run');
        $ward = Ward::find($wardId);
        
        if (!$ward) {
            $this->error("Ward ID {$wardId} not found!");
            return 1;
        }

        $this->info("=== Creating missing beds for: {$ward->ward_name} (ID: {$ward->id}) ===");
        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        $existingBeds = Bed::where('ward_id', $ward->id)->pluck('bed_number')->toArray();
        $currentCount = count($existingBeds);
        
        $this->info("Current beds: {$currentCount}, Capacity: {$ward->capacity}");

        if ($currentCount >= $ward->capacity) {
            $this->info('✅ Ward already has enough beds.');
            return 0;
        }

        // Detect bed number pattern from existing beds
        // e.g., "D701", "D702" for ward WWD7
        $prefix = '';
        if (!empty($existingBeds)) {
            // Use existing bed pattern
            $sample = $existingBeds[0];
            if (preg_match('/^([A-Za-z]+\d)/', $sample, $m)) {
                $prefix = $m[1];
            }
        } else {
            // Derive from ward code: WWD3 → D3
            if (preg_match('/W*([A-Z]\d+)/i', $ward->ward_code, $m)) {
                $prefix = $m[1];
            }
        }

        if (!$prefix) {
            $this->error("Could not determine bed number prefix from ward code '{$ward->ward_code}'");
            return 1;
        }

        $this->info("Bed number prefix: {$prefix}");
        $bedsCreated = 0;

        for ($i = 1; $i <= $ward->capacity; $i++) {
            $padded = str_pad($i, 2, '0', STR_PAD_LEFT);
            $bedNumber = $prefix . $padded;
            
            if (!in_array($bedNumber, $existingBeds)) {
                $displayName = 'Bed ' . $i;
                $this->info("  🛏️  Creating bed: {$bedNumber} ({$displayName})");
                
                if (!$dryRun) {
                    Bed::create([
                        'ward_id' => $ward->id,
                        'bed_number' => $bedNumber,
                        'bed_id' => $bedNumber,
                        'bed_display_name' => $displayName,
                        'status' => 'available',
                        'is_active' => true,
                    ]);
                }
                $bedsCreated++;
            }
        }

        $this->newLine();
        if ($dryRun) {
            $this->warn("🔍 DRY RUN: Would create {$bedsCreated} beds.");
        } else {
            $this->info("✅ Created {$bedsCreated} new beds for {$ward->ward_name}.");
        }

        return 0;
    }

    private function fix()
    {
        $wardId = $this->option('ward-id');
        $dryRun = $this->option('dry-run');

        if (!$wardId) {
            $this->error('Please specify --ward-id=<ID> for the target ward.');
            $this->info('Or use --fix-auto to auto-detect wards from bed numbers.');
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
            
            $wardPatients = Patient::where('ward_id', $wardId)
                ->where('is_active', true)
                ->whereIn('status', ['admitted', 'pending_discharge', 'prebook'])
                ->get();
            
            if ($wardPatients->isNotEmpty()) {
                $this->info("Found {$wardPatients->count()} patients already in this ward. Ensuring beds exist...");
                $orphanedPatients = $wardPatients;
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
                    $this->info("  🛏️  Creating bed: {$bedNumber}");
                    
                    if (!$dryRun) {
                        $existingBed = Bed::create([
                            'ward_id' => $wardId,
                            'bed_number' => $bedNumber,
                            'bed_id' => $bedNumber,
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

            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Restoration failed: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    /**
     * Restore admissions using ADT message logs.
     * 
     * Approach: Instead of looking at orphaned patients, we scan the ADT logs directly.
     * We find ALL processed A01 (admit) and A02 (transfer) messages for our ward codes,
     * get the latest one per MRN, and check if those patients are properly assigned.
     * 
     * This handles cases where:
     * - Patients have ward_id set but beds are vacant
     * - Patients were discharged/deactivated accidentally  
     * - Bed records exist but have no patient_id assigned
     */
    private function fixFromAdt()
    {
        $dryRun = $this->option('dry-run');

        $this->info('=== RESTORE FROM ADT MESSAGES ===');
        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }
        $this->newLine();

        // Get all current ward codes
        $wards = Ward::where('is_active', true)->get();
        $wardCodes = $wards->pluck('ward_code')->toArray();

        $this->info("Current active wards: " . implode(', ', $wards->map(fn($w) => "{$w->ward_name} ({$w->ward_code}, ID:{$w->id})")->toArray()));
        $this->newLine();

        // Step 1: Find the last processed ADT admission/transfer for each unique MRN
        // that was assigned to one of our wards
        $this->info('📨 Scanning ADT logs for admitted patients...');

        // Get all unique patient MRNs from processed ADT records where bed was assigned
        $adtLogs = AdtMessageLog::where('status', 'processed')
            ->whereIn('event_type', ['A01', 'A02', 'A08'])
            ->whereNotNull('patient_id_ref')
            ->orderBy('created_at', 'desc')
            ->get();

        // Group by patient MRN, take latest record per MRN
        $latestAdtPerMrn = [];
        foreach ($adtLogs as $log) {
            $mrn = $log->patient_mrn;
            if (!$mrn) continue;
            if (!isset($latestAdtPerMrn[$mrn])) {
                $latestAdtPerMrn[$mrn] = $log;
            }
        }

        $this->info("Found " . count($latestAdtPerMrn) . " unique patients with processed ADT records.");
        $this->newLine();

        // Step 2: Check which of these patients are NOT properly in the system
        $this->info('🔍 Checking current patient status vs ADT records...');
        $this->newLine();

        $missingPatients = [];
        $mismatchedBeds = [];
        $inactivePatients = [];

        foreach ($latestAdtPerMrn as $mrn => $adtLog) {
            // Skip if the last ADT event was a discharge (A03)
            $lastEvent = AdtMessageLog::where('patient_mrn', $mrn)
                ->where('status', 'processed')
                ->orderBy('created_at', 'desc')
                ->first();
            
            if ($lastEvent && $lastEvent->event_type === 'A03') {
                continue; // Patient was properly discharged
            }

            $parsedData = $adtLog->parsed_data ?? [];
            $pv1 = $parsedData['pv1'] ?? [];
            $actionTaken = $adtLog->action_taken ?? [];
            
            // Get the bed that was assigned in ADT
            $adtBedNumber = $actionTaken['assigned_bed'] ?? $actionTaken['adt_bed_code'] ?? $pv1['bed'] ?? null;
            $adtWardName = $actionTaken['assigned_ward'] ?? $actionTaken['adt_ward_code'] ?? $pv1['ward'] ?? null;
            
            if (!$adtBedNumber) continue;

            // Find the patient in our system
            $patient = Patient::where('mrn', $mrn)->first();
            if (!$patient) continue;

            // Check 1: Patient is not active or not admitted
            if (!$patient->is_active || !in_array($patient->status, ['admitted', 'pending_discharge', 'prebook', 'prebook_pending'])) {
                // Check if they were discharged AFTER the ADT admission
                if ($patient->discharged_at && $patient->discharged_at > $adtLog->created_at) {
                    continue; // Legitimate discharge after ADT
                }
                $inactivePatients[] = [
                    'patient' => $patient,
                    'adt_log' => $adtLog,
                    'bed_number' => $adtBedNumber,
                    'ward_name' => $adtWardName,
                ];
                continue;
            }

            // Check 2: Patient has no ward_id
            if (!$patient->ward_id) {
                $missingPatients[] = [
                    'patient' => $patient,
                    'adt_log' => $adtLog,
                    'bed_number' => $adtBedNumber,
                    'ward_name' => $adtWardName,
                    'issue' => 'no_ward',
                ];
                continue;
            }

            // Check 3: Patient has ward_id but bed is not properly assigned
            $bed = Bed::where('ward_id', $patient->ward_id)
                ->where('bed_number', $patient->bed_number)
                ->first();

            if ($bed && $bed->patient_id != $patient->id) {
                $mismatchedBeds[] = [
                    'patient' => $patient,
                    'adt_log' => $adtLog,
                    'bed' => $bed,
                    'bed_number' => $patient->bed_number,
                    'issue' => 'bed_not_assigned',
                ];
            } elseif (!$bed && $patient->bed_number) {
                $mismatchedBeds[] = [
                    'patient' => $patient,
                    'adt_log' => $adtLog,
                    'bed' => null,
                    'bed_number' => $patient->bed_number,
                    'issue' => 'bed_not_found',
                ];
            }
        }

        // Also check: beds that are "available" but have patients that think they're in that bed
        $this->info('🛏️  Checking for vacant beds that should have patients...');
        $vacantBeds = Bed::whereNull('patient_id')
            ->where('status', 'available')
            ->where('is_active', true)
            ->get();

        foreach ($vacantBeds as $bed) {
            // Is there an active patient who thinks they're in this bed?
            $patientInBed = Patient::where('ward_id', $bed->ward_id)
                ->where('bed_number', $bed->bed_number)
                ->where('is_active', true)
                ->whereIn('status', ['admitted', 'pending_discharge'])
                ->first();

            if ($patientInBed && !collect($mismatchedBeds)->contains(fn($m) => $m['patient']->id === $patientInBed->id)) {
                $mismatchedBeds[] = [
                    'patient' => $patientInBed,
                    'adt_log' => $latestAdtPerMrn[$patientInBed->mrn] ?? null,
                    'bed' => $bed,
                    'bed_number' => $bed->bed_number,
                    'issue' => 'bed_vacant_but_patient_assigned',
                ];
            }
        }

        // Report findings
        $this->info('=== FINDINGS ===');
        $this->newLine();

        if (empty($missingPatients) && empty($mismatchedBeds) && empty($inactivePatients)) {
            $this->info('✅ All ADT-admitted patients are properly assigned. No issues found.');
            return 0;
        }

        if (!empty($missingPatients)) {
            $this->warn("📋 Patients with NO ward assignment: " . count($missingPatients));
            $headers = ['ID', 'Name', 'MRN', 'Status', 'ADT Bed', 'ADT Ward', 'ADT Date'];
            $rows = array_map(fn($m) => [
                $m['patient']->id,
                substr($m['patient']->name, 0, 35),
                $m['patient']->mrn,
                $m['patient']->status,
                $m['bed_number'],
                $m['ward_name'],
                $m['adt_log']->created_at->format('Y-m-d H:i'),
            ], $missingPatients);
            $this->table($headers, $rows);
        }

        if (!empty($mismatchedBeds)) {
            $this->warn("📋 Bed assignment mismatches: " . count($mismatchedBeds));
            $headers = ['Patient ID', 'Name', 'MRN', 'Bed', 'Issue', 'Current Bed Patient'];
            $rows = array_map(fn($m) => [
                $m['patient']->id,
                substr($m['patient']->name, 0, 35),
                $m['patient']->mrn,
                $m['bed_number'],
                $m['issue'],
                $m['bed'] ? ($m['bed']->patient_id ?? 'null') : 'bed missing',
            ], $mismatchedBeds);
            $this->table($headers, $rows);
        }

        if (!empty($inactivePatients)) {
            $this->warn("📋 Patients deactivated but ADT says admitted: " . count($inactivePatients));
            $headers = ['ID', 'Name', 'MRN', 'Current Status', 'is_active', 'ADT Bed', 'ADT Date'];
            $rows = array_map(fn($m) => [
                $m['patient']->id,
                substr($m['patient']->name, 0, 35),
                $m['patient']->mrn,
                $m['patient']->status,
                $m['patient']->is_active ? 'Yes' : 'No',
                $m['bed_number'],
                $m['adt_log']->created_at->format('Y-m-d H:i'),
            ], $inactivePatients);
            $this->table($headers, $rows);
        }

        $totalIssues = count($missingPatients) + count($mismatchedBeds) + count($inactivePatients);
        $this->newLine();
        $this->info("Total issues to fix: {$totalIssues}");

        if (!$dryRun && !$this->confirm('Proceed with fixing all issues?', true)) {
            $this->info('Aborted.');
            return 0;
        }

        $this->newLine();

        DB::beginTransaction();

        try {
            $fixed = 0;

            // Fix 1: Patients with no ward
            foreach ($missingPatients as $item) {
                $patient = $item['patient'];
                $bedNumber = $item['bed_number'];
                $ward = $this->detectWardFromBedNumber($bedNumber);

                if (!$ward) {
                    $this->warn("  ⚠️  Cannot detect ward for bed {$bedNumber}, skipping patient #{$patient->id}");
                    continue;
                }

                $this->info("  🔗 #{$patient->id} ({$patient->name}) → Ward {$ward->ward_name}, Bed {$bedNumber}");
                if (!$dryRun) {
                    $patient->update(['ward_id' => $ward->id, 'bed_number' => $bedNumber]);
                }

                $this->ensureBedAssignment($ward, $bedNumber, $patient, $dryRun);
                $fixed++;
            }

            // Fix 2: Bed mismatches
            foreach ($mismatchedBeds as $item) {
                $patient = $item['patient'];
                $bedNumber = $item['bed_number'];
                $ward = Ward::find($patient->ward_id);

                if (!$ward) {
                    $ward = $this->detectWardFromBedNumber($bedNumber);
                    if ($ward && !$dryRun) {
                        $patient->update(['ward_id' => $ward->id]);
                    }
                }

                if (!$ward) continue;

                $this->info("  🛏️  Fixing bed {$bedNumber} for #{$patient->id} ({$patient->name}) [{$item['issue']}]");
                $this->ensureBedAssignment($ward, $bedNumber, $patient, $dryRun);
                $fixed++;
            }

            // Fix 3: Inactive patients that should be active
            foreach ($inactivePatients as $item) {
                $patient = $item['patient'];
                $bedNumber = $item['bed_number'];
                $ward = $this->detectWardFromBedNumber($bedNumber);

                if (!$ward) {
                    $this->warn("  ⚠️  Cannot detect ward for bed {$bedNumber}, skipping patient #{$patient->id}");
                    continue;
                }

                $this->info("  🔄 Reactivating #{$patient->id} ({$patient->name}) → Ward {$ward->ward_name}, Bed {$bedNumber}");
                if (!$dryRun) {
                    $patient->update([
                        'ward_id' => $ward->id,
                        'bed_number' => $bedNumber,
                        'status' => 'admitted',
                        'is_active' => true,
                        'discharged_at' => null,
                        'pending_discharge_at' => null,
                    ]);
                }

                $this->ensureBedAssignment($ward, $bedNumber, $patient, $dryRun);
                $fixed++;
            }

            if ($dryRun) {
                DB::rollBack();
                $this->newLine();
                $this->warn("🔍 DRY RUN COMPLETE - No changes were made. Would fix {$fixed} issue(s).");
            } else {
                DB::commit();
                $this->newLine();
                $this->info("✅ RESTORATION COMPLETE! Fixed {$fixed} issue(s).");
            }

            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Restoration failed: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    /**
     * Ensure a bed exists and is properly assigned to a patient.
     */
    private function ensureBedAssignment(Ward $ward, string $bedNumber, Patient $patient, bool $dryRun): void
    {
        $bed = Bed::where('ward_id', $ward->id)
            ->where('bed_number', $bedNumber)
            ->first();

        $bedStatus = in_array($patient->status, ['prebook', 'prebook_pending']) ? 'reserved' : 'occupied';

        if (!$bed) {
            $bedIndex = intval(preg_replace('/[^0-9]/', '', substr($bedNumber, 2)));
            $displayName = 'Bed ' . $bedIndex;

            $this->info("    🛏️  Creating bed: {$bedNumber} ({$displayName})");
            if (!$dryRun) {
                Bed::create([
                    'ward_id' => $ward->id,
                    'bed_number' => $bedNumber,
                    'bed_id' => $bedNumber,
                    'bed_display_name' => $displayName,
                    'status' => $bedStatus,
                    'patient_id' => $patient->id,
                    'is_active' => true,
                ]);
            }
        } elseif ($bed->patient_id != $patient->id || $bed->status !== $bedStatus) {
            $this->info("    ✅ Updating bed {$bedNumber} → patient #{$patient->id} [status: {$bedStatus}]");
            if (!$dryRun) {
                $bed->update([
                    'status' => $bedStatus,
                    'patient_id' => $patient->id,
                ]);
            }
        } else {
            $this->info("    ✅ Bed {$bedNumber} already correct");
        }
    }

    /**
     * Detect the correct ward from a bed number like "D601" → ward with code containing "D6".
     */
    private function detectWardFromBedNumber(string $bedNumber): ?Ward
    {
        // Extract prefix: "D601" → "D6", "D417" → "D4"
        if (!preg_match('/^([A-Za-z]+\d)/', $bedNumber, $matches)) {
            return null;
        }

        $prefix = strtoupper($matches[1]); // e.g., "D6"

        // Find ward whose code contains this prefix (e.g., "WWD6" contains "D6")
        $wards = Ward::where('is_active', true)->get();

        foreach ($wards as $ward) {
            if (preg_match('/W*(' . preg_quote($prefix, '/') . ')/i', $ward->ward_code)) {
                return $ward;
            }
        }

        return null;
    }
}
