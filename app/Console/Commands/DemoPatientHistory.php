<?php

namespace App\Console\Commands;

use App\Models\Consultant;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\Ward;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes a demo:swap look like a working hospital, for a showcase:
 * - the demo inpatients' admissions are spread back over realistic stays for
 *   their ward (an ICU stay runs longer than a labour room's), and bookings over
 *   the last day and a half
 * - DAYS of demo discharges are made up, ward by ward, at the rate the ward's
 *   census turns over, with admission and discharge entries at the hours they
 *   usually happen (about a third of discharges before noon)
 * - the demo inpatients get the last 24 hours of vital signs, mostly normal,
 *   a few with an EWS of 3 to 4 and one or two of 5 or more
 * - the real admission log entries of those DAYS are set aside (the swap's own
 *   discharges, and the C+ sync's), so the figures are the demo's alone
 *
 * Everything goes in an undo record in storage/app/demo, which demo:undo puts
 * back exactly. The data is written straight to the tables, so nothing is pushed
 * to the e-ink screens (no screen shows these dates or readings).
 */
class DemoPatientHistory extends Command
{
    public const SOURCE = 'demo-history';
    public const NOTE = 'demo:history';

    protected $signature = 'demo:history
                            {--hospital= : The hospital (id or name); needed when there are several}
                            {--days=60 : How many days of discharges to make up (the 30-day length of stay compares two 30-day spells)}
                            {--seed= : Make the same data again (any number)}
                            {--dry-run : Show what would change and stop}
                            {--force : Apply without asking}';

    protected $description = 'Give the demo patients of a demo:swap realistic stays, discharge history and vital signs; demo:undo reverses it';

    private const IN_BED = [Patient::STATUS_ADMITTED, Patient::STATUS_PENDING_DISCHARGE];
    private const BOOKED = [Patient::STATUS_PREBOOK, Patient::STATUS_PREBOOK_PENDING];

    /** Relative weight of each hour of the day (0-23) for admissions and for discharges. */
    private const ADMIT_HOURS = [1, 1, 1, 1, 1, 1, 2, 3, 5, 7, 9, 9, 8, 8, 9, 9, 8, 7, 6, 5, 4, 3, 2, 1];
    private const DISCHARGE_HOURS = [0, 0, 0, 0, 0, 0, 0, 1, 3, 6, 10, 11, 10, 9, 9, 8, 6, 4, 3, 2, 1, 1, 0, 0];

    public function handle(): int
    {
        if ($this->option('seed') !== null) {
            mt_srand((int) $this->option('seed'));
        }
        $days = max(1, min(120, (int) $this->option('days')));

        $hospital = $this->hospital();
        if ($hospital === null) {
            return self::FAILURE;
        }
        if ($active = $this->activeRecord()) {
            $this->error("A demo:history is already in ({$active}). Undo it first: php artisan demo:undo {$active}");

            return self::FAILURE;
        }

        $wards = Ward::with('wardType')->where('hospital_id', $hospital->id)->get()->keyBy('id');
        $demo = Patient::whereIn('ward_id', $wards->keys())
            ->where('is_active', true)
            ->where('mrn', 'like', DemoPatientSwap::MRN_PREFIX . '%')
            ->whereIn('status', array_merge(self::IN_BED, self::BOOKED))
            ->get();
        $inBed = $demo->whereIn('status', self::IN_BED);
        if ($inBed->isEmpty()) {
            $this->error('There are no demo patients in beds: run demo:swap first.');

            return self::FAILURE;
        }

        $now = now();
        $from = $now->copy()->subDays($days)->startOfDay();
        $setAside = DB::table('admission_logs')
            ->whereIn('ward_id', $wards->keys())
            ->where(fn ($q) => $q->where('mrn', 'not like', DemoPatientSwap::MRN_PREFIX . '%')->orWhereNull('mrn'))
            ->where(fn ($q) => $q->where('admitted_at', '>=', $from)->orWhere('discharged_at', '>=', $from)
                ->orWhere('booked_at', '>=', $from)->orWhere('created_at', '>=', $from))
            ->orderBy('id')->get();

        // Per ward: the stays it sees and how fast its census turns over
        $plan = $wards->filter(fn (Ward $w) => $w->is_active)->map(function (Ward $ward) use ($inBed, $days) {
            [$los, $sigma] = self::stayFor($ward);
            $census = $inBed->where('ward_id', $ward->id)->count();
            $beds = DB::table('beds')->where('ward_id', $ward->id)->where('is_active', true)->pluck('bed_number')->all();
            $perDay = $ward->isEmergency() || $beds === [] ? 0.0 : max($census, 1) / $los;

            return compact('ward', 'los', 'sigma', 'census', 'beds', 'perDay');
        });

        $this->info("Demo history for hospital #{$hospital->id} {$hospital->name}, over the last {$days} days");
        $this->table(['Ward', 'Demo in beds', 'Typical stay', 'Discharges a day', "Made-up stays ({$days} d)"], $plan->map(fn ($p) => [
            "{$p['ward']->ward_code} {$p['ward']->ward_name}",
            $p['census'],
            self::days($p['los']),
            $p['perDay'] ? number_format($p['perDay'], 1) : '- (ED: own board)',
            $p['perDay'] ? '~' . (int) round($p['perDay'] * $days) : '-',
        ])->values()->all());

        $stays = (int) round($plan->sum('perDay') * $days);
        $bookings = $demo->count() - $inBed->count();
        $this->line("  {$inBed->count()} demo inpatient(s) and {$bookings} booking(s) get realistic admission times.");
        $this->line("  About {$stays} past demo stays are made up (patients plus an admit and a discharge entry each).");
        $this->line("  {$inBed->count()} demo inpatient(s) get the last 24 h of vital signs (about 1 in 10 is left due, as on a real ward).");
        $this->line("  {$setAside->count()} real admission log entr(ies) of these days are set aside (the swap's discharges and C+'s), to come back on undo.");

        if ($this->option('dry-run')) {
            $this->line('Dry run: nothing changed.');

            return self::SUCCESS;
        }
        if (! $this->option('force') && ! $this->confirm('Make the demo history?', false)) {
            $this->line('Nothing changed.');

            return self::SUCCESS;
        }

        $record = [
            'type' => 'history',
            'made_at' => $now->toIso8601String(),
            'hospital_id' => $hospital->id,
            'hospital' => $hospital->name,
            'days' => $days,
            'set_aside_logs' => $setAside->map(fn ($r) => (array) $r)->all(),
            'patients_before' => DB::table('patients')->whereIn('id', $demo->modelKeys())
                ->get(['id', 'admitted_at', 'booked_at', 'pending_discharge_at', 'created_at'])->map(fn ($r) => (array) $r)->all(),
            'logs_before' => DB::table('admission_logs')->whereIn('patient_id', $demo->modelKeys())->where('action', 'admit')
                ->get(['id', 'admitted_at', 'created_at'])->map(fn ($r) => (array) $r)->all(),
            'created' => ['patients' => [], 'logs' => [], 'vitals' => []],
        ];
        $path = $this->save($record);

        DB::transaction(function () use (&$record, $path, $plan, $demo, $setAside, $now, $from, $wards) {
            DB::table('admission_logs')->whereIn('id', $setAside->pluck('id'))->delete();

            $this->spreadAdmissions($demo, $plan, $wards, $now);
            $record['created']['patients'] = $this->pastStays($plan, $now, $from);
            $record['created']['logs'] = DB::table('admission_logs')->where('source', self::SOURCE)
                ->whereIn('patient_id', $record['created']['patients'])->pluck('id')->all();
            $record['created']['vitals'] = $this->vitals($demo->whereIn('status', self::IN_BED), $wards, $now);

            // Inside the transaction: if the record can't be written, nothing is changed
            $this->save($record, $path);
        });

        $relative = $this->relative($path);
        $this->info(sprintf('Made %d past demo stays, %d vital sign readings, spread %d admission(s); set aside %d real log entries.',
            count($record['created']['patients']), count($record['created']['vitals']), $demo->count(), $setAside->count()));
        $this->line("Undo record: {$relative}");
        $this->line("To take it out again: php artisan demo:undo {$relative} --dry-run, then without --dry-run (before undoing the swap).");

        return self::SUCCESS;
    }

    /** A ward's typical stay in days, and how widely stays vary around it (log-normal sigma). */
    private static function stayFor(Ward $ward): array
    {
        $name = strtoupper($ward->ward_code . ' ' . $ward->ward_name);

        return match (true) {
            $ward->isEmergency() => [0.15, 0.6],
            str_contains($name, 'NEONATAL') || str_contains($name, 'NICU') => [9.0, 0.6],
            str_contains($name, 'SPECIAL CARE') || str_contains($name, 'SCN') => [6.0, 0.6],
            str_contains($name, 'LABOUR') => [0.4, 0.5],
            str_contains($name, 'NURSERY') => [2.5, 0.4],
            str_contains($name, 'INTENSIVE') || str_contains($name, 'ICU') || str_contains($name, 'HIGH DEPENDENCY') => [4.5, 0.7],
            str_contains($name, 'OBSTETRIC') || str_contains($name, 'O&G') || str_contains($name, 'GYNAE') => [2.5, 0.45],
            str_contains($name, 'MEDICAL') => [4.5, 0.65],
            str_contains($name, 'SURGICAL') => [3.5, 0.6],
            default => [3.5, 0.6],
        };
    }

    /** The demo inpatients came in over the stays their ward sees; bookings over the last day and a half. */
    private function spreadAdmissions(Collection $demo, Collection $plan, Collection $wards, Carbon $now): void
    {
        foreach ($demo as $patient) {
            [$los, $sigma] = self::stayFor($wards[$patient->ward_id]);

            if (in_array($patient->status, self::BOOKED, true)) {
                DB::table('patients')->where('id', $patient->id)->update(['booked_at' => $now->copy()->subMinutes(mt_rand(30, 36 * 60))]);

                continue;
            }

            // An inpatient is somewhere through their stay
            $elapsed = self::stay($los, $sigma) * mt_rand(2, 98) / 100;
            $admitted = $this->at($now->copy()->subSeconds((int) ($elapsed * 86400)), self::ADMIT_HOURS, $now);
            $changes = ['admitted_at' => $admitted, 'created_at' => $admitted];
            if ($patient->status === Patient::STATUS_PENDING_DISCHARGE) {
                $changes['pending_discharge_at'] = $admitted->max($now->copy()->subHours(mt_rand(1, 6)));
            }
            DB::table('patients')->where('id', $patient->id)->update($changes);
            DB::table('admission_logs')->where('patient_id', $patient->id)->where('action', 'admit')
                ->update(['admitted_at' => $admitted, 'created_at' => $admitted]);
        }
    }

    /** @return list<int> the made-up patients */
    private function pastStays(Collection $plan, Carbon $now, Carbon $from): array
    {
        $number = $this->nextDemoNumber();
        $consultants = Consultant::pluck('name', 'id');
        $mrns = [];

        foreach ($plan as $p) {
            if (! $p['perDay']) {
                continue;
            }
            $ward = $p['ward'];
            $doctors = Patient::where('ward_id', $ward->id)->whereNotNull('consultant_id')->pluck('consultant_id')->unique()->values()->all();

            $patients = [];
            $events = [];
            for ($day = $from->copy(); $day->lte($now); $day->addDay()) {
                $weekday = match ($day->dayOfWeek) { 0 => 0.7, 6 => 0.85, default => 1.05 };
                for ($i = self::poisson($p['perDay'] * $weekday); $i > 0; $i--) {
                    $discharged = $this->at($day->copy()->setTime(0, 0), self::DISCHARGE_HOURS, null);
                    if ($discharged->gt($now)) {
                        continue;
                    }
                    $admitted = $this->at($discharged->copy()->subSeconds((int) (self::stay($p['los'], $p['sigma']) * 86400)), self::ADMIT_HOURS, $discharged);

                    $code = str_pad((string) $number++, 6, '0', STR_PAD_LEFT);
                    [$gender, $age] = self::person($ward);
                    $consultantId = $doctors ? $doctors[mt_rand(0, count($doctors) - 1)] : null;
                    $bed = $p['beds'][mt_rand(0, count($p['beds']) - 1)];
                    $mrn = DemoPatientSwap::MRN_PREFIX . $code;
                    $mrns[] = $mrn;

                    $patients[] = [
                        'name' => "demo-{$code}", 'mrn' => $mrn, 'rn' => DemoPatientSwap::MRN_PREFIX . "RN{$code}",
                        'ic_passport' => DemoPatientSwap::MRN_PREFIX . "IC{$code}", 'gender' => $gender, 'age' => $age, 'phone' => '',
                        'is_active' => false, 'ward_id' => null, 'bed_number' => null, 'consultant_id' => $consultantId,
                        'status' => Patient::STATUS_DISCHARGED, 'admitted_at' => $admitted, 'discharged_at' => $discharged,
                        'created_at' => $admitted, 'updated_at' => $discharged,
                    ];
                    $events[$mrn] = compact('admitted', 'discharged', 'bed', 'gender', 'age') + [
                        'name' => "demo-{$code}", 'consultant' => $consultantId ? ($consultants[$consultantId] ?? null) : null,
                    ];
                }
            }

            foreach (array_chunk($patients, 500) as $chunk) {
                DB::table('patients')->insert($chunk);
            }
            $ids = DB::table('patients')->whereIn('mrn', array_keys($events))->pluck('id', 'mrn');
            $logs = [];
            foreach ($events as $mrn => $e) {
                $base = ['patient_id' => $ids[$mrn], 'ward_id' => $ward->id, 'bed_number' => $e['bed'], 'patient_name' => $e['name'],
                    'mrn' => $mrn, 'consultant_name' => $e['consultant'], 'gender' => $e['gender'], 'age' => $e['age'],
                    'notes' => 'Demo stay (demo:history)', 'source' => self::SOURCE];
                $logs[] = $base + ['action' => 'admit', 'admitted_at' => $e['admitted'], 'discharged_at' => null,
                    'created_at' => $e['admitted'], 'updated_at' => $e['admitted']];
                $logs[] = $base + ['action' => 'discharge', 'admitted_at' => null, 'discharged_at' => $e['discharged'],
                    'created_at' => $e['discharged'], 'updated_at' => $e['discharged']];
            }
            foreach (array_chunk($logs, 500) as $chunk) {
                DB::table('admission_logs')->insert($chunk);
            }
        }

        return $mrns === [] ? [] : collect(array_chunk($mrns, 1000))
            ->flatMap(fn ($chunk) => DB::table('patients')->whereIn('mrn', $chunk)->pluck('id'))->values()->all();
    }

    /** @return list<int> the readings made */
    private function vitals(Collection $inBed, Collection $wards, Carbon $now): array
    {
        $hasOxygen = Schema::hasColumn('vital_signs', 'oxygen_delivery');
        $rows = [];
        $urgent = 0;

        foreach ($inBed as $patient) {
            $ward = $wards[$patient->ward_id];
            if ($ward->isEmergency()) {
                continue;
            }
            $admitted = Carbon::parse(DB::table('patients')->where('id', $patient->id)->value('admitted_at'));
            // About one in ten is due a set: their last one is over a day old, or they have none yet
            $missed = mt_rand(1, 100) <= 10;
            $start = $admitted->max($now->copy()->subHours(24))->addMinutes(mt_rand(10, 60));
            $end = $missed ? $now->copy()->subHours(25) : $now;
            if ($missed && $admitted->lt($now->copy()->subHours(30))) {
                $start = $admitted->max($now->copy()->subHours(34));
            }

            // A few are unwell: EWS 3-4, and one or two 5 or more
            $roll = mt_rand(1, 100);
            $state = $roll <= 2 && $urgent < 2 ? 'urgent' : ($roll <= 9 ? 'warning' : 'well');
            if ($state === 'urgent') {
                $urgent++;
            }
            // By the ward, not the age: C+ patients often have none on record
            $paediatric = self::babyWard($ward);

            for ($t = $start->copy(); $t->lte($end); $t->addMinutes(mt_rand(210, 330))) {
                $last = $t->copy()->addMinutes(240)->gt($end);
                $row = self::reading($last ? $state : 'well', $paediatric) + [
                    'patient_id' => $patient->id, 'reading_type' => 'full', 'notes' => self::NOTE,
                    'recorded_at' => $t->copy(), 'created_at' => $t->copy(), 'updated_at' => $t->copy(),
                ];
                if ($hasOxygen) {
                    $row['oxygen_delivery'] = $state === 'urgent' && $last ? 'nasal_cannula' : 'room_air';
                }
                $rows[] = $row;
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('vital_signs')->insert($chunk);
        }

        return DB::table('vital_signs')->where('notes', self::NOTE)->whereIn('patient_id', $inBed->modelKeys())->pluck('id')->all();
    }

    private static function reading(string $state, bool $baby): array
    {
        $r = fn (float $a, float $b, int $dp = 0) => round($a + (mt_rand() / mt_getrandmax()) * ($b - $a), $dp);

        // Babies: temperature and SpO2 only, as a nursery charts them (the adult EWS the
        // Command Center scores with would flag a newborn's normal pulse and breathing)
        if ($baby) {
            return ['pulse_rate' => null, 'respiratory_rate' => null, 'spo2' => (int) $r(96, 100),
                'temperature' => $r(36.5, 37.4, 1), 'systolic_bp' => null, 'diastolic_bp' => null];
        }

        return match ($state) {
            'urgent' => ['pulse_rate' => (int) $r(122, 135), 'respiratory_rate' => (int) $r(25, 29), 'spo2' => (int) $r(89, 92),
                'temperature' => $r(38.4, 39.2, 1), 'systolic_bp' => (int) $r(88, 96), 'diastolic_bp' => (int) $r(50, 60)],
            // EWS 3: a raised pulse and breathing rate, SpO2 a little low
            'warning' => ['pulse_rate' => (int) $r(101, 112), 'respiratory_rate' => (int) $r(21, 23), 'spo2' => (int) $r(94, 95),
                'temperature' => $r(37.4, 37.9, 1), 'systolic_bp' => (int) $r(112, 128), 'diastolic_bp' => (int) $r(65, 78)],
            default => ['pulse_rate' => (int) $r(64, 94), 'respiratory_rate' => (int) $r(14, 19), 'spo2' => (int) $r(96, 99),
                'temperature' => $r(36.3, 37.3, 1), 'systolic_bp' => (int) $r(108, 138), 'diastolic_bp' => (int) $r(65, 85)],
        };
    }

    private static function babyWard(Ward $ward): bool
    {
        $name = strtoupper($ward->ward_code . ' ' . $ward->ward_name);

        return str_contains($name, 'NURSERY') || str_contains($name, 'NEONATAL') || str_contains($name, 'NICU') || str_contains($name, 'SCN');
    }

    /** Gender and age for a made-up patient of this ward. */
    private static function person(Ward $ward): array
    {
        $name = strtoupper($ward->ward_name);
        if (self::babyWard($ward)) {
            return [mt_rand(0, 1) ? 'Male' : 'Female', 0];
        }
        if (str_contains($name, 'OBSTETRIC') || str_contains($name, 'LABOUR')) {
            return ['Female', mt_rand(21, 42)];
        }

        return [mt_rand(0, 1) ? 'Male' : 'Female', min(95, max(18, (int) round(55 + self::normal() * 17)))];
    }

    /** A moment on that day at an hour the weights favour, never after $notAfter. */
    private function at(Carbon $moment, array $weights, ?Carbon $notAfter): Carbon
    {
        for ($try = 0; $try < 20; $try++) {
            $pick = mt_rand(1, array_sum($weights));
            foreach ($weights as $hour => $weight) {
                if (($pick -= $weight) <= 0) {
                    break;
                }
            }
            $at = $moment->copy()->setTime($hour, mt_rand(0, 59), mt_rand(0, 59));
            if ($notAfter === null || $at->lte($notAfter)) {
                return $at;
            }
        }

        return $notAfter->copy()->subMinutes(mt_rand(5, 90));
    }

    private static function stay(float $mean, float $sigma): float
    {
        return exp(log($mean) - $sigma * $sigma / 2 + $sigma * self::normal());
    }

    private static function normal(): float
    {
        $u = max(1e-9, mt_rand() / mt_getrandmax());

        return sqrt(-2 * log($u)) * cos(2 * M_PI * (mt_rand() / mt_getrandmax()));
    }

    private static function poisson(float $lambda): int
    {
        if ($lambda > 30) {
            return max(0, (int) round($lambda + sqrt($lambda) * self::normal()));
        }
        $limit = exp(-$lambda);
        $k = 0;
        $p = 1.0;
        do {
            $k++;
            $p *= mt_rand() / mt_getrandmax();
        } while ($p > $limit);

        return $k - 1;
    }

    private static function days(float $days): string
    {
        return $days < 1 ? round($days * 24) . ' h' : number_format($days, 1) . ' d';
    }

    private function nextDemoNumber(): int
    {
        $max = DB::table('patients')->where('mrn', 'like', DemoPatientSwap::MRN_PREFIX . '%')->pluck('mrn')
            ->map(fn ($mrn) => (int) preg_replace('/\D/', '', $mrn))->max();

        return ($max ?? 0) + 1;
    }

    /** The demo:history record not undone yet, if any. */
    private function activeRecord(): ?string
    {
        foreach (glob(storage_path('app/demo/history-*.json')) ?: [] as $file) {
            $record = json_decode((string) file_get_contents($file), true);
            if (is_array($record) && empty($record['undone_at'])) {
                return 'storage/app/demo/' . basename($file);
            }
        }

        return null;
    }

    private function hospital(): ?Hospital
    {
        $value = (string) $this->option('hospital');
        if ($value === '') {
            if (Hospital::count() === 1) {
                return Hospital::first();
            }
            $this->error('There is more than one hospital: pick one with --hospital.');

            return null;
        }
        $hospital = ctype_digit($value) ? Hospital::find((int) $value) : Hospital::where('name', $value)->first();
        if ($hospital === null) {
            $this->error("No hospital matches \"{$value}\".");
        }

        return $hospital;
    }

    private function save(array $record, ?string $path = null): string
    {
        $dir = storage_path('app/demo');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if ($path === null) {
            $stem = $dir . DIRECTORY_SEPARATOR . 'history-' . now()->format('Ymd-His');
            $path = "{$stem}.json";
            for ($n = 2; file_exists($path); $n++) {
                $path = "{$stem}-{$n}.json";
            }
        }
        if (file_put_contents($path, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) === false) {
            throw new \RuntimeException("Could not save the undo record {$path}, so nothing was changed.");
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
