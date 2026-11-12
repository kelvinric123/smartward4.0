<?php

namespace App\Console\Commands;

use App\Models\AdmissionLog;
use App\Models\AdtMessageLog;
use App\Models\Bed;
use App\Models\EkadBedMapping;
use App\Models\EkadConfiguration;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\PatientCareProvider;
use App\Models\Ward;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Swaps a hospital's real patients for demo ones, for a showcase. Every real
 * patient in a bed is discharged (a discharge entry is logged) and every real
 * prebooking cancelled; a demo patient, demo-000001 and on, takes their place in
 * the same ward and bed with the same gender, age and doctors, admitted (or booked)
 * now. Patients in a ward or bed that is switched off are discharged without one.
 *
 * Before changing anything it saves the patients' and beds' rows to an undo record
 * in storage/app/demo, which demo:undo puts back exactly. The e-ink screens are
 * repainted with the demo patients (one push per mapped bed), unless --no-screens.
 * Anything that feeds real patients in (the C+ sync, ADT) has to be paused first,
 * or it undoes the demo.
 */
class DemoPatientSwap extends Command
{
    public const SOURCE = 'demo-swap';
    public const MRN_PREFIX = 'DEMO';

    protected $signature = 'demo:swap
                            {--hospital= : The hospital (id or name); needed when there are several}
                            {--dry-run : Show what would change and stop}
                            {--no-screens : Change the data without pushing to the e-ink screens}
                            {--force : Apply without asking}';

    protected $description = 'Discharge every real patient and put demo patients (demo-000001...) in their beds; demo:undo reverses it';

    private const IN_BED = [Patient::STATUS_ADMITTED, Patient::STATUS_PENDING_DISCHARGE];
    private const BOOKED = [Patient::STATUS_PREBOOK, Patient::STATUS_PREBOOK_PENDING];

    /** Copied to the demo patient: where they are and how they are cared for, nothing that names them. */
    private const COPIED = [
        'ward_id', 'bed_number', 'target_bed_number', 'status', 'gender', 'age', 'consultant_id', 'nurse_id', 'anaesthetist_id',
        'patient_class', 'nursing_level', 'diet_types', 'fall_risk', 'isolation_type', 'hgt_enabled', 'hgt_frequency',
        'feeding_routes', 'diet_orders', 'estimated_length_of_stay', 'payor_type',
    ];

    public function handle(): int
    {
        $hospital = $this->hospital();
        if ($hospital === null) {
            return self::FAILURE;
        }

        $wards = Ward::where('hospital_id', $hospital->id)->get()->keyBy('id');
        $beds = Bed::whereIn('ward_id', $wards->keys())->get();
        $bedAt = $beds->keyBy(fn (Bed $b) => $b->ward_id . '|' . $b->bed_number);

        // Bookings not given a ward yet belong to no hospital: they are taken along when there is only one
        $onlyHospital = Hospital::count() === 1;
        $real = Patient::where(fn ($q) => $q->whereIn('ward_id', $wards->keys())->when($onlyHospital, fn ($q) => $q->orWhereNull('ward_id')))
            ->where('is_active', true)
            ->whereIn('status', array_merge(self::IN_BED, self::BOOKED))
            ->where('mrn', 'not like', self::MRN_PREFIX . '%')
            ->orderBy('ward_id')->orderBy('bed_number')->orderBy('id')
            ->get();
        $demoNow = Patient::where(fn ($q) => $q->whereIn('ward_id', $wards->keys())->when($onlyHospital, fn ($q) => $q->orWhereNull('ward_id')))
            ->where('is_active', true)->where('mrn', 'like', self::MRN_PREFIX . '%')->count();

        $this->info("Demo swap for hospital #{$hospital->id} {$hospital->name}");
        $this->warnAboutFeeds($wards);

        if ($real->isEmpty()) {
            $this->line($demoNow
                ? "No real patients left: {$demoNow} demo patient(s) are in already."
                : 'No patients to swap.');

            return self::SUCCESS;
        }

        // Who gets a demo twin: anyone in a ward that is on, in a bed that is on (or booked without a bed yet)
        $plan = $real->map(function (Patient $p) use ($wards, $bedAt) {
            $ward = $p->ward_id ? $wards[$p->ward_id] : null;
            $bed = $p->ward_id && $p->bed_number ? ($bedAt[$p->ward_id . '|' . $p->bed_number] ?? null) : null;
            $twin = $ward === null
                ? in_array($p->status, self::BOOKED, true)
                : $ward->is_active && ($bed === null ? $p->bed_number === null : (bool) $bed->is_active);
            // Taken now, before the swap changes the patient: what the demo patient copies (cast
            // values, so JSON columns aren't encoded twice; only columns this database has)
            $copy = collect(self::COPIED)
                ->filter(fn ($key) => array_key_exists($key, $p->getAttributes()))
                ->mapWithKeys(fn ($key) => [$key => $p->getAttribute($key)])
                ->all();
            if (! in_array($copy['gender'] ?? null, ['Male', 'Female'], true)) {
                $copy['gender'] = $p->id % 2 ? 'Male' : 'Female';
            }

            return ['patient' => $p, 'ward' => $ward, 'bed' => $bed, 'twin' => $twin, 'copy' => $copy,
                'in_bed' => in_array($p->status, self::IN_BED, true), 'pending' => $p->status === Patient::STATUS_PENDING_DISCHARGE];
        });

        $this->table(['Ward', 'Name', 'Real in beds', 'Real bookings', 'Demo patients', 'Without one (ward/bed off)'], $plan
            ->groupBy(fn ($row) => $row['ward']?->id ?? 0)
            ->map(fn (Collection $rows) => [
                $rows[0]['ward']?->ward_code ?? '-',
                $rows[0]['ward'] ? $rows[0]['ward']->ward_name . ($rows[0]['ward']->is_active ? '' : ' (off)') : '(booked, no ward yet)',
                $rows->where('in_bed', true)->count(),
                $rows->where('in_bed', false)->count(),
                $rows->where('twin', true)->count(),
                $rows->where('twin', false)->count(),
            ])->values()->all());

        $inBed = $plan->where('in_bed', true)->count();
        $booked = $plan->count() - $inBed;
        $twins = $plan->where('twin', true)->count();
        $this->line("  {$inBed} real patient(s) to discharge (logged as discharges now), {$booked} booking(s) to cancel, {$twins} demo patient(s) to admit or book now.");

        $screens = $this->screenBeds($plan);
        if ($this->option('no-screens')) {
            $this->line('  E-ink screens: left alone (--no-screens).');
        } elseif ($screens === null) {
            $this->line('  E-ink screens: EKad auto-push is off, so none are repainted.');
        } else {
            $this->line("  E-ink screens: {$screens} mapped bed(s) get repainted (one push each, or two for a new demo patient).");
        }

        if ($this->option('dry-run')) {
            $this->line('Dry run: nothing changed.');

            return self::SUCCESS;
        }
        if (! $this->option('force') && ! $this->confirm('Discharge these patients and put in the demo ones?', false)) {
            $this->line('Nothing changed.');

            return self::SUCCESS;
        }

        $path = $this->saveRecord($hospital, $plan, $beds);

        $created = ['patients' => [], 'logs' => [], 'providers' => []];
        DB::transaction(function () use ($plan, $path, &$created) {
            $now = now();
            $number = $this->nextDemoNumber();

            // 1. The real patients leave, quietly: their beds are repainted once, with the demo patient
            Model::withoutEvents(function () use ($plan, $now, &$created) {
                foreach ($plan as $row) {
                    $p = $row['patient'];
                    // The admission log needs a ward: a booking without one goes unlogged (the undo record has it)
                    if ($p->ward_id) $created['logs'][] = AdmissionLog::create([
                        'patient_id' => $p->id,
                        'ward_id' => $p->ward_id,
                        'bed_number' => $p->bed_number ?? $p->target_bed_number,
                        'action' => $row['in_bed'] ? 'discharge' : 'cancel-prebook',
                        'patient_name' => $p->name,
                        'mrn' => $p->mrn,
                        'consultant_name' => $p->consultant?->name,
                        'gender' => $p->gender,
                        'age' => $p->age,
                        'notes' => 'For the demo (demo:swap); demo:undo brings the patient back',
                        'discharged_at' => $row['in_bed'] ? $now : null,
                        'source' => self::SOURCE,
                    ])->id;

                    $p->update([
                        'status' => $row['in_bed'] ? Patient::STATUS_DISCHARGED : Patient::STATUS_CANCELLED,
                        'is_active' => false,
                        'ward_id' => null,
                        'bed_number' => null,
                        'target_bed_number' => null,
                        'discharged_at' => $row['in_bed'] ? $now : $p->discharged_at,
                        'pending_discharge_at' => null,
                    ]);
                    PatientCareProvider::where('patient_id', $p->id)->where('is_active', true)->update(['is_active' => false]);
                }
            });

            // 2. The demo patients come in, with the screens (unless --no-screens)
            $admit = function () use ($plan, $now, &$number, &$created) {
                foreach ($plan as $row) {
                    $p = $row['patient'];
                    $bed = $row['bed'];

                    if (! $row['twin']) {
                        if ($bed && $bed->patient_id === $p->id) {
                            $bed->update(['patient_id' => null] + ($bed->status === Bed::STATUS_MAINTENANCE ? [] : ['status' => 'available']));
                        }

                        continue;
                    }

                    $code = str_pad((string) $number++, 6, '0', STR_PAD_LEFT);
                    $demo = Patient::create($row['copy'] + [
                        'name' => "demo-{$code}",
                        'mrn' => self::MRN_PREFIX . $code,
                        'rn' => self::MRN_PREFIX . "RN{$code}",
                        'ic_passport' => self::MRN_PREFIX . "IC{$code}",
                        'phone' => '',
                        'is_active' => true,
                        'admitted_at' => $row['in_bed'] ? $now : null,
                        'booked_at' => $row['in_bed'] ? null : $now,
                        'pending_discharge_at' => $row['pending'] ? $now : null,
                    ]);
                    $created['patients'][] = $demo->id;

                    if ($row['in_bed']) {
                        $created['logs'][] = AdmissionLog::create([
                            'patient_id' => $demo->id,
                            'ward_id' => $demo->ward_id,
                            'bed_number' => $demo->bed_number,
                            'action' => 'admit',
                            'patient_name' => $demo->name,
                            'mrn' => $demo->mrn,
                            'consultant_name' => $p->consultant?->name,
                            'gender' => $demo->gender,
                            'age' => $demo->age,
                            'notes' => 'Demo patient (demo:swap)',
                            'admitted_at' => $now,
                            'source' => self::SOURCE,
                        ])->id;
                    }

                    // The doctors on the bed card, as the real patient had them
                    foreach (PatientCareProvider::where('patient_id', $p->id)->whereIn('id', $this->providerIdsBefore[$p->id] ?? [])->get() as $provider) {
                        $created['providers'][] = PatientCareProvider::create([
                            'patient_id' => $demo->id,
                            'role' => $provider->role,
                            'doctor_code' => $provider->doctor_code,
                            'doctor_name' => $provider->doctor_name,
                            'consultant_id' => $provider->consultant_id,
                            'anaesthetist_id' => $provider->anaesthetist_id,
                            'source' => $provider->source,
                            'assigned_at' => $now,
                            'is_active' => true,
                        ])->id;
                    }

                    if ($bed && $bed->patient_id === $p->id) {
                        $bed->update(['patient_id' => $demo->id]);
                    }
                }

                // A bed still pointing at a real patient somewhere else (out of step) is freed
                $realIds = $plan->pluck('patient')->pluck('id')->all();
                foreach (Bed::whereIn('patient_id', $realIds)->get() as $bed) {
                    $bed->update(['patient_id' => null] + ($bed->status === Bed::STATUS_MAINTENANCE ? [] : ['status' => 'available']));
                }
            };
            $this->option('no-screens') ? Model::withoutEvents($admit) : $admit();

            // Inside the transaction: if the record can't be written, nothing is changed
            $record = json_decode((string) file_get_contents($path), true);
            $record['created'] = $created;
            if (file_put_contents($path, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) === false) {
                throw new \RuntimeException("Could not finish the undo record {$path}, so nothing was changed.");
            }
        });

        $relative = $this->relative($path);
        $this->info(sprintf('Discharged %d and cancelled %d real patient booking(s); %d demo patient(s) in.', $inBed, $booked, count($created['patients'])));
        $this->line("Undo record: {$relative}");
        $this->line("To bring the real patients back: php artisan demo:undo {$relative} --dry-run, then without --dry-run.");

        return self::SUCCESS;
    }

    /** @var array<int, list<int>> each real patient's active care providers, before the swap */
    private array $providerIdsBefore = [];

    private function hospital(): ?Hospital
    {
        $value = (string) $this->option('hospital');
        if ($value === '') {
            if (Hospital::count() === 1) {
                return Hospital::first();
            }
            $this->error('There is more than one hospital: pick one with --hospital. Hospitals: '
                . Hospital::orderBy('id')->get()->map(fn ($h) => "#{$h->id} {$h->name}")->implode(', '));

            return null;
        }

        $hospital = ctype_digit($value) ? Hospital::find((int) $value) : Hospital::where('name', $value)->first();
        if ($hospital === null) {
            $this->error("No hospital matches \"{$value}\".");
        }

        return $hospital;
    }

    /** The C+ sync and ADT put real patients back (and discharge demo ones): say when they last did anything. */
    private function warnAboutFeeds(Collection $wards): void
    {
        $lastCplus = $wards->max('cplus_synced_at');
        $lastAdt = rescue(fn () => AdtMessageLog::max('created_at'), null, false);

        foreach (['C+ sync' => $lastCplus, 'ADT message' => $lastAdt] as $feed => $at) {
            if ($at === null) {
                $this->line("  Last {$feed}: never.");

                continue;
            }
            $at = \Illuminate\Support\Carbon::parse($at);
            $recent = $at->gt(now()->subHour());
            $line = "  Last {$feed}: {$at->toDateTimeString()} ({$at->diffForHumans()})";
            $recent
                ? $this->warn("{$line}. It is still running: pause it, or it will undo the demo.")
                : $this->line($line . '.');
        }
    }

    /** @return int|null the mapped beds that get repainted, null when auto-push is off */
    private function screenBeds(Collection $plan): ?int
    {
        $config = EkadConfiguration::getActive();
        if (! $config || ! $config->auto_push_enabled) {
            return null;
        }
        $bedIds = $plan->pluck('bed')->filter()->pluck('id')->unique();

        return EkadBedMapping::whereIn('bed_id', $bedIds)->where('is_active', true)->count();
    }

    private function nextDemoNumber(): int
    {
        $max = Patient::where('mrn', 'like', self::MRN_PREFIX . '%')->pluck('mrn')
            ->map(fn ($mrn) => (int) preg_replace('/\D/', '', $mrn))
            ->max();

        return ($max ?? 0) + 1;
    }

    private function saveRecord(Hospital $hospital, Collection $plan, Collection $beds): string
    {
        $patientIds = $plan->pluck('patient')->pluck('id')->all();
        $providers = PatientCareProvider::whereIn('patient_id', $patientIds)->where('is_active', true)->get(['id', 'patient_id']);
        $this->providerIdsBefore = $providers->groupBy('patient_id')->map(fn ($g) => $g->pluck('id')->all())->all();

        $record = [
            'made_at' => now()->toIso8601String(),
            'hospital_id' => $hospital->id,
            'hospital' => $hospital->name,
            // Whole rows, so demo:undo can put them back exactly
            'patients' => DB::table('patients')->whereIn('id', $patientIds)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            'beds' => $beds->filter(fn (Bed $b) => in_array($b->patient_id, $patientIds, true))
                ->map(fn (Bed $b) => ['id' => $b->id, 'status' => $b->status, 'patient_id' => $b->patient_id])->values()->all(),
            'providers' => $providers->pluck('id')->all(),
            'created' => ['patients' => [], 'logs' => [], 'providers' => []],
        ];

        $dir = storage_path('app/demo');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $stem = $dir . DIRECTORY_SEPARATOR . 'swap-' . now()->format('Ymd-His');
        $path = "{$stem}.json";
        for ($n = 2; file_exists($path); $n++) {
            $path = "{$stem}-{$n}.json";
        }
        if (file_put_contents($path, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) === false) {
            throw new \RuntimeException("Could not save the undo record to {$path}, so nothing was changed.");
        }

        return $path;
    }

    private function relative(string $path): string
    {
        $base = rtrim(str_replace('\\', '/', base_path()), '/') . '/';
        $path = str_replace('\\', '/', $path);

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
