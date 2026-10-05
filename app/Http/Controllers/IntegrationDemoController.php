<?php

namespace App\Http\Controllers;

use App\Models\Ward;
use App\Models\Bed;
use App\Models\ClinicalIndicator;
use App\Models\DietType;
use App\Models\Patient;
use App\Models\SugarReading;
use App\Models\VitalSign;
use App\Models\AdmissionLog;
use App\Models\Infusion;
use App\Models\InfusionPump;
use App\Services\DemoClinicalData;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Faker\Factory as Faker;
use Carbon\Carbon;

class IntegrationDemoController extends Controller
{
    public function index()
    {
        $wards = Ward::where('is_active', true)->get();

        $admittedPatients = Patient::with(['ward.wardType.clinicalIndicators', 'bed'])
            ->where('is_active', true)
            ->whereIn('status', ['admitted', 'pending_discharge'])
            ->get();

        $dietTypes = DietType::where('is_active', true)->orderBy('name')->get();

        // The clinical indicators each patient's ward records, offered on the vital signs form
        $patientIndicators = $admittedPatients->mapWithKeys(fn (Patient $patient) => [$patient->id => [
            'wardType' => $patient->ward?->wardType?->name,
            'indicators' => $this->wardIndicators($patient)
                ->map(fn (ClinicalIndicator $indicator) => [
                    'id' => $indicator->id,
                    'code' => $indicator->code,
                    'name' => $indicator->name,
                    'kind' => DemoClinicalData::kind($indicator->definition()),
                    'detail' => DemoClinicalData::describe($indicator->definition()),
                ])
                ->values()
                ->all(),
        ]]);

        return view('integration.demo.index', compact('wards', 'admittedPatients', 'dietTypes', 'patientIndicators'));
    }

    /**
     * The active clinical indicators bound to the patient's ward type: the
     * ones Patient Details shows, so the only ones worth seeding.
     */
    private function wardIndicators(Patient $patient): Collection
    {
        $wardType = $patient->ward?->wardType;

        return $wardType && $wardType->is_active
            ? $wardType->clinicalIndicators->where('is_active', true)->values()
            : collect();
    }

    public function seedPatients(Request $request)
    {
        $request->validate([
            'seed_action' => 'nullable|string|in:new,reseed',
            'full_seed' => 'nullable|boolean',
            'ward_id' => 'required|exists:wards,id',
            'number_of_beds' => 'required_unless:seed_action,reseed|nullable|integer|min:1|max:50',
            'nursing_level' => 'nullable|string|in:random,none,level_1,level_2,level_3,level_4',
            'fall_risk' => 'nullable|string|in:random,none,low,moderate,high,alert_active',
            'isolation_type' => 'nullable|string|in:random,none,contact,droplet,airborne,protective,mrsa,vre,cdiff,covid,tb',
            'diet_mode' => 'nullable|string|in:none,random,custom',
            'diet_codes' => 'nullable|array',
            'diet_codes.*' => 'string',
            'allergy_mode' => 'nullable|string|in:none,random,custom',
            'custom_allergies' => 'nullable|string|max:255',
            'hgt_mode' => 'nullable|string|in:none,random,enabled',
            'hgt_frequency' => 'nullable|string|in:random,bd,tds,qid,pid',
            'seed_sugar_reading' => 'nullable|boolean',
        ]);

        $ward = Ward::findOrFail($request->ward_id);
        $seedAction = $request->input('seed_action', 'new');
        $faker = Faker::create();

        // Clinical indicator selections ('random' = realistic weighted mix per patient)
        $config = [
            // full_seed: every 'random' choice resolves to an actual value — no patient is left without data
            'full_seed' => $request->boolean('full_seed'),
            'nursing' => $request->input('nursing_level', 'random'),
            'fall' => $request->input('fall_risk', 'random'),
            'isolation' => $request->input('isolation_type', 'random'),
            'diet_mode' => $request->input('diet_mode', 'random'),
            'diet_codes' => collect($request->input('diet_codes', []))
                ->map(fn($c) => strtoupper(trim($c)))->filter()->unique()->values()->all(),
            'allergy_mode' => $request->input('allergy_mode', 'random'),
            'allergies' => collect(explode(',', (string) $request->input('custom_allergies', '')))
                ->map(fn($a) => trim($a))->filter()->values()->all(),
            'hgt_mode' => $request->input('hgt_mode', 'random'),
            'hgt_frequency' => $request->input('hgt_frequency', 'random'),
        ];
        $seedSugarReading = $request->boolean('seed_sugar_reading', true);

        // ---- Reseed mode: overwrite clinical indicators on the ward's existing patients ----
        if ($seedAction === 'reseed') {
            $patients = Patient::where('ward_id', $ward->id)
                ->where('is_active', true)
                ->whereIn('status', ['admitted', 'pending_discharge'])
                ->get();

            if ($patients->isEmpty()) {
                return back()->with('error', "No admitted patients found in {$ward->ward_name} to reseed.");
            }

            foreach ($patients as $patient) {
                $indicators = $this->resolveClinicalIndicators($faker, $config);
                $patient->update($indicators);

                if ($indicators['hgt_enabled'] && $seedSugarReading) {
                    SugarReading::create([
                        'patient_id' => $patient->id,
                        'value' => $faker->randomFloat(1, 3.5, 13.5),
                        'frequency' => $indicators['hgt_frequency'],
                        'notes' => 'Demo seeded data',
                        'recorded_by' => Auth::id() ?? 1,
                        'recorded_at' => now()->subMinutes($faker->numberBetween(5, 240)),
                    ]);
                }
            }

            $suffix = $config['full_seed'] ? ' (fully seeded — every patient received all indicators)' : '';
            return back()->with('success', "Successfully reseeded clinical indicators for {$patients->count()} patients in {$ward->ward_name}.{$suffix}");
        }

        // ---- New-patient mode: find beds in this ward that are not occupied ----
        $availableBeds = Bed::where('ward_id', $ward->id)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('status')
                      ->orWhereNotIn('status', ['occupied', 'maintenance']);
            })
            ->limit($request->number_of_beds)
            ->get();

        if ($availableBeds->isEmpty()) {
            return back()->with('error', "No available beds found in {$ward->ward_name} to admit patients to.");
        }

        $seededCount = 0;

        foreach ($availableBeds as $bed) {
            $admittedAt = now();

            $indicators = $this->resolveClinicalIndicators($faker, $config);
            $hgtEnabled = $indicators['hgt_enabled'];
            $hgtFrequency = $indicators['hgt_frequency'];

            // Create a demo patient
            $patient = Patient::create($indicators + [
                'name' => 'Demo Patient - ' . $faker->name,
                'mrn' => 'MRN-D' . $faker->unique()->numberBetween(100000, 999999),
                'rn' => 'RN-D' . $faker->unique()->numberBetween(100000, 999999),
                'ic_passport' => $faker->numerify('######-##-####'),
                'age' => $faker->numberBetween(18, 90),
                'gender' => $faker->randomElement(['Male', 'Female']),
                'date_of_birth' => $faker->dateTimeBetween('-90 years', '-18 years')->format('Y-m-d'),
                'race' => $faker->randomElement(['Malay', 'Chinese', 'Indian', 'Other']),
                'religion' => $faker->randomElement(['Islam', 'Buddhism', 'Hinduism', 'Christianity']),
                'phone' => $faker->phoneNumber,
                'is_active' => true,
                'ward_id' => $ward->id,
                'bed_number' => $bed->bed_number,
                'admitted_at' => $admittedAt,
                'status' => 'admitted',
            ]);

            // Seed an initial glucose reading so "Last HGT" shows on the dashboard
            if ($hgtEnabled && $seedSugarReading) {
                SugarReading::create([
                    'patient_id' => $patient->id,
                    'value' => $faker->randomFloat(1, 3.5, 13.5),
                    'frequency' => $hgtFrequency,
                    'notes' => 'Demo seeded data',
                    'recorded_by' => Auth::id() ?? 1,
                    'recorded_at' => now()->subMinutes($faker->numberBetween(5, 240)),
                ]);
            }

            // Update bed
            $bed->update([
                'status' => 'occupied',
                'patient_id' => $patient->id,
            ]);

            // Create admission log
            AdmissionLog::create([
                'patient_id' => $patient->id,
                'ward_id' => $ward->id,
                'user_id' => Auth::id() ?? 1,
                'bed_number' => $bed->bed_number,
                'action' => 'admit',
                'patient_name' => $patient->name,
                'mrn' => $patient->mrn,
                'gender' => $patient->gender,
                'age' => $patient->age,
                'admitted_at' => $admittedAt,
                'source' => 'manual',
            ]);

            $seededCount++;
        }

        $message = "Successfully seeded {$seededCount} patients into {$ward->ward_name} with clinical indicators.";
        if ($seededCount < $request->number_of_beds) {
            $message .= " (Requested {$request->number_of_beds}, but only {$seededCount} beds were available)";
        }

        return back()->with('success', $message);
    }

    /**
     * Resolve one patient's clinical indicators from the seed form config.
     * With full_seed, 'random' choices always resolve to real data ('none'
     * outcomes are removed from the pools and diet/allergy/HGT always assign).
     */
    private function resolveClinicalIndicators(\Faker\Generator $faker, array $config): array
    {
        $fullSeed = $config['full_seed'];

        $nursingLevel = $config['nursing'] === 'random'
            ? $this->weightedRandom($fullSeed
                ? ['level_1' => 40, 'level_2' => 30, 'level_3' => 20, 'level_4' => 10]
                : ['none' => 30, 'level_1' => 30, 'level_2' => 20, 'level_3' => 13, 'level_4' => 7])
            : $config['nursing'];

        $fallRisk = $config['fall'] === 'random'
            ? $this->weightedRandom($fullSeed
                ? ['low' => 38, 'moderate' => 30, 'high' => 20, 'alert_active' => 12]
                : ['none' => 35, 'low' => 25, 'moderate' => 20, 'high' => 12, 'alert_active' => 8])
            : $config['fall'];

        $isolationType = $config['isolation'] === 'random'
            ? $this->weightedRandom($fullSeed
                ? ['contact' => 27, 'droplet' => 23, 'protective' => 14, 'mrsa' => 13, 'airborne' => 10, 'covid' => 7, 'tb' => 6]
                : ['none' => 70, 'contact' => 8, 'droplet' => 7, 'protective' => 4, 'mrsa' => 4, 'airborne' => 3, 'covid' => 2, 'tb' => 2])
            : $config['isolation'];

        $randomDietPool = ['RD' => 30, 'SD' => 15, 'DMD' => 15, 'LSD' => 10, 'HPD' => 8, 'LFD' => 7, 'NBM' => 6, 'CLQD' => 5, 'FLD' => 4];

        $dietTypes = null;
        if ($config['diet_mode'] === 'custom' && !empty($config['diet_codes'])) {
            $dietTypes = $config['diet_codes'];
        } elseif ($config['diet_mode'] === 'random') {
            // Normally ~40% stay regular (no diet orders); full seed gives everyone 1-2 orders
            if ($fullSeed || $faker->numberBetween(1, 100) > 40) {
                $picked = [];
                $count = $faker->numberBetween(1, 2);
                for ($i = 0; $i < $count; $i++) {
                    $picked[] = $this->weightedRandom($randomDietPool);
                }
                $dietTypes = array_values(array_unique($picked));
            }
        }

        $allergyPool = ['Penicillin', 'Paracetamol', 'Aspirin', 'NSAIDs', 'Sulfa Drugs', 'Latex', 'Seafood', 'Peanuts', 'Eggs', 'Dust Mites'];

        $allergies = null;
        if ($config['allergy_mode'] === 'custom' && !empty($config['allergies'])) {
            $allergies = $config['allergies'];
        } elseif ($config['allergy_mode'] === 'random' && ($fullSeed || $faker->numberBetween(1, 100) <= 35)) {
            $allergies = $faker->randomElements($allergyPool, $faker->numberBetween(1, 2));
        }

        $hgtEnabled = $config['hgt_mode'] === 'enabled'
            || ($config['hgt_mode'] === 'random' && ($fullSeed || $faker->numberBetween(1, 100) <= 30));
        $hgtFrequency = null;
        if ($hgtEnabled) {
            $hgtFrequency = $config['hgt_frequency'] === 'random'
                ? $faker->randomElement(['bd', 'tds', 'qid', 'pid'])
                : $config['hgt_frequency'];
        }

        return [
            'nursing_level' => $nursingLevel,
            'fall_risk' => $fallRisk,
            'isolation_type' => $isolationType,
            'diet_types' => $dietTypes,
            'allergies' => $allergies,
            'hgt_enabled' => $hgtEnabled,
            'hgt_frequency' => $hgtFrequency,
        ];
    }

    /**
     * Pick a key from [value => weight] using weighted randomness.
     */
    private function weightedRandom(array $weights): string
    {
        $rand = mt_rand(1, max(1, array_sum($weights)));
        foreach ($weights as $value => $weight) {
            $rand -= $weight;
            if ($rand <= 0) {
                return (string) $value;
            }
        }
        return (string) array_key_first($weights);
    }

    /**
     * Vital signs and, for the clinical indicators the patient's ward records,
     * monitor readings or scores, all following one clinical course.
     */
    public function seedVitalSigns(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'readings_per_day' => 'required|integer|min:1|max:24',
            'past_days' => 'required|integer|min:0|max:30',
            'seed_vitals' => 'nullable|boolean',
            'pattern' => ['nullable', 'string', Rule::in(array_keys(DemoClinicalData::PATTERNS))],
            'indicator_ids' => 'nullable|array',
            'indicator_ids.*' => 'integer',
            'monitor_per_day' => 'nullable|integer|min:1|max:24',
            'assessments_per_day' => 'nullable|integer|min:1|max:24',
        ]);

        $patient = Patient::with('ward.wardType.clinicalIndicators')->findOrFail($request->patient_id);
        $admissionId = $patient->getCurrentAdmissionId() ?? ('ADM-' . $patient->id . '-' . now()->format('YmdHis'));
        $pattern = $request->input('pattern') ?: 'stable';
        // A form from before the clinical indicator options only seeded vital signs
        $seedVitals = $request->boolean('seed_vitals', true);

        // Only what the patient's ward records, and has the detail to seed from
        $indicators = $this->wardIndicators($patient)
            ->whereIn('id', array_map('intval', (array) $request->input('indicator_ids', [])))
            ->filter(fn (ClinicalIndicator $indicator) => DemoClinicalData::kind($indicator->definition()) !== null)
            ->values();

        if (!$seedVitals && $indicators->isEmpty()) {
            return back()->withInput()->with('demo_tab', 'seed-vitals')
                ->with('error', "Choose vital signs or a clinical indicator recorded on {$patient->name}'s ward to seed.");
        }

        $faker = Faker::create();
        $totalSeeded = 0;
        $indicatorsSeeded = [];

        $startDate = Carbon::now()->subDays($request->past_days)->startOfDay();
        $endDate = Carbon::now();

        // Prevent generating vital signs before the admission date
        if ($patient->admitted_at && $startDate->lt($patient->admitted_at)) {
            $startDate = $patient->admitted_at->copy();
        }

        // The course runs over the whole period, whatever is seeded
        $from = $startDate->copy();
        $ventilated = $indicators->contains('code', DemoClinicalData::VENTILATOR);

        DB::transaction(function () use ($request, $patient, $admissionId, $pattern, $seedVitals, $indicators, $faker, $startDate, $endDate, $from, $ventilated, &$totalSeeded, &$indicatorsSeeded) {
            for ($day = 0; $seedVitals && $day <= $request->past_days; $day++) {
                $currentDate = $startDate->copy()->addDays($day);
                if ($currentDate->gt($endDate)) {
                    break;
                }

                for ($i = 0; $i < $request->readings_per_day; $i++) {
                    // Spread the readings evenly across the day, or randomly. Let's do random hours.
                    $readingTime = $currentDate->copy()->addMinutes($faker->numberBetween(0, 1439)); // 0 to 1439 mins in a day

                    // If the reading time is in the future, cap it to now
                    if ($readingTime->gt($endDate)) {
                        $readingTime = $endDate->copy()->subMinutes($faker->numberBetween(1, 60));
                    }

                    // If the reading time is before admission, adjust it
                    if ($patient->admitted_at && $readingTime->lt($patient->admitted_at)) {
                        $readingTime = $patient->admitted_at->copy()->addMinutes($faker->numberBetween(1, 60));
                    }

                    $severity = DemoClinicalData::severity($pattern, DemoClinicalData::progress($readingTime, $from, $endDate));

                    VitalSign::create(DemoClinicalData::vitalSigns($pattern, $severity, $ventilated) + [
                        'patient_id' => $patient->id,
                        'admission_id' => $admissionId,
                        'recorded_by' => Auth::id() ?? 1,
                        'reading_type' => 'full',
                        'notes' => DemoClinicalData::NOTE,
                        'recorded_at' => $readingTime,
                    ]);

                    $totalSeeded++;
                }
            }

            foreach ($indicators as $indicator) {
                $kind = DemoClinicalData::kind($indicator->definition());
                $perDay = $kind === DemoClinicalData::KIND_READINGS
                    ? ($request->integer('monitor_per_day') ?: 6)
                    : ($request->integer('assessments_per_day') ?: 2);

                $count = DemoClinicalData::seedIndicator($patient, $indicator, $from, $endDate, $perDay, $pattern, Auth::id());
                $indicatorsSeeded[] = $count . ' ' . $indicator->code . match ($kind) {
                    DemoClinicalData::KIND_READINGS => ' readings',
                    DemoClinicalData::KIND_SCREEN => ' screens',
                    default => ' scores',
                };
            }
        });

        $seeded = collect($seedVitals ? ["{$totalSeeded} vital sign readings"] : [])->merge($indicatorsSeeded);
        $course = $pattern === 'stable' ? '' : ' (' . Str::lower(Str::before(DemoClinicalData::PATTERNS[$pattern], ':')) . ' course)';

        return back()->withInput()->with('demo_tab', 'seed-vitals')
            ->with('success', 'Successfully seeded ' . $seeded->join(', ', ' and ') . " for {$patient->name}{$course}.");
    }

    public function seedInfusion(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
        ]);

        $patient = Patient::findOrFail($request->patient_id);
        
        // Create or find a pump for this patient
        $pump = InfusionPump::firstOrCreate([
            'device_id' => 'DEMO-PUMP-' . $patient->id,
        ], [
            'device_name' => 'Demo Pump ' . $patient->id,
            'pump_model' => 'Infusomat Space',
            'ward_id' => $patient->ward_id,
            'patient_id' => $patient->id,
            'is_active' => true,
            'power_status' => 'mains',
            'battery_percent' => 100,
            'wifi_strength' => 85,
            'linked_at' => now(),
            'last_seen_at' => now(),
        ]);

        // Ensure pump is linked
        if ($pump->patient_id !== $patient->id) {
            $pump->update([
                'patient_id' => $patient->id,
                'ward_id' => $patient->ward_id,
                'linked_at' => now(),
            ]);
        }

        $faker = Faker::create();

        // Create an active running infusion
        $totalVolume = $faker->randomFloat(2, 100, 1000);
        $infusedVolume = $faker->randomFloat(2, 0, $totalVolume - 10);
        $remainingVolume = $totalVolume - $infusedVolume;
        $flowRate = $faker->randomFloat(2, 10, 100);
        $remainingMinutes = $flowRate > 0 ? ($remainingVolume / $flowRate) * 60 : 0;

        Infusion::create([
            'patient_id' => $patient->id,
            'infusion_pump_id' => $pump->id,
            'medication_name' => $faker->randomElement(['Normal Saline 0.9%', 'Dextrose 5%', 'Propofol', 'Dopamine', 'Fentanyl']),
            'total_volume' => $totalVolume,
            'infused_volume' => $infusedVolume,
            'remaining_volume' => $remainingVolume,
            'flow_rate' => $flowRate,
            'duration_minutes' => ($totalVolume / $flowRate) * 60,
            'remaining_minutes' => $remainingMinutes,
            'status' => 'running',
            'delivery_mode' => 'continuous',
            'started_at' => now()->subMinutes((($totalVolume - $remainingVolume) / $flowRate) * 60),
            'last_updated_at' => now(),
        ]);

        return back()->with('demo_tab', 'seed-infusions')->with('success', "Successfully seeded an active infusion for {$patient->name}.");
    }
}
