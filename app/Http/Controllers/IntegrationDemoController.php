<?php

namespace App\Http\Controllers;

use App\Models\Ward;
use App\Models\Bed;
use App\Models\Patient;
use App\Models\VitalSign;
use App\Models\AdmissionLog;
use App\Models\Infusion;
use App\Models\InfusionPump;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Faker\Factory as Faker;
use Carbon\Carbon;

class IntegrationDemoController extends Controller
{
    public function index()
    {
        $wards = Ward::where('is_active', true)->get();
        
        $admittedPatients = Patient::with(['ward', 'bed'])
            ->where('is_active', true)
            ->whereIn('status', ['admitted', 'pending_discharge'])
            ->get();

        return view('integration.demo.index', compact('wards', 'admittedPatients'));
    }

    public function seedPatients(Request $request)
    {
        $request->validate([
            'ward_id' => 'required|exists:wards,id',
            'number_of_beds' => 'required|integer|min:1|max:50',
        ]);

        $ward = Ward::findOrFail($request->ward_id);
        
        // Find beds in this ward that are not occupied
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

        $faker = Faker::create();
        $seededCount = 0;

        foreach ($availableBeds as $bed) {
            $admittedAt = now();
            
            // Create a demo patient
            $patient = Patient::create([
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

        $message = "Successfully seeded {$seededCount} patients into {$ward->ward_name}.";
        if ($seededCount < $request->number_of_beds) {
            $message .= " (Requested {$request->number_of_beds}, but only {$seededCount} beds were available)";
        }

        return back()->with('success', $message);
    }

    public function seedVitalSigns(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'readings_per_day' => 'required|integer|min:1|max:24',
            'past_days' => 'required|integer|min:0|max:30',
        ]);

        $patient = Patient::findOrFail($request->patient_id);
        $admissionId = $patient->getCurrentAdmissionId() ?? ('ADM-' . $patient->id . '-' . now()->format('YmdHis'));

        $faker = Faker::create();
        $totalSeeded = 0;

        $startDate = Carbon::now()->subDays($request->past_days)->startOfDay();
        $endDate = Carbon::now();

        // Prevent generating vital signs before the admission date
        if ($patient->admitted_at && $startDate->lt($patient->admitted_at)) {
            $startDate = $patient->admitted_at->copy();
        }

        for ($day = 0; $day <= $request->past_days; $day++) {
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

                VitalSign::create([
                    'patient_id' => $patient->id,
                    'admission_id' => $admissionId,
                    'recorded_by' => Auth::id() ?? 1,
                    'systolic_bp' => $faker->numberBetween(100, 140),
                    'diastolic_bp' => $faker->numberBetween(60, 90),
                    'pulse_rate' => $faker->numberBetween(60, 100),
                    'temperature' => $faker->randomFloat(1, 36.5, 37.5),
                    'spo2' => $faker->numberBetween(95, 100),
                    'respiratory_rate' => $faker->numberBetween(12, 20),
                    'reading_type' => 'full',
                    'notes' => 'Demo seeded data',
                    'recorded_at' => $readingTime,
                ]);

                $totalSeeded++;
            }
        }

        return back()->with('success', "Successfully seeded {$totalSeeded} vital sign readings for {$patient->name}.");
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

        return back()->with('success', "Successfully seeded an active infusion for {$patient->name}.");
    }
}
