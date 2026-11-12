<?php

namespace App\Console\Commands;

use App\Models\Bed;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\Ward;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Compares a hospital bed board or bed census list, typed into a JSON file (see
 * database/bedboards), with the wards and beds in SmartWard. It only reads,
 * unless --follow makes the list the master (switching other wards off).
 *
 * The printout shows only the rows each ward's panel had on screen, so a ward's
 * "total" is complete but its "beds" list may be short. SmartWard beds missing from
 * the list are reported against the number of rows the print hid.
 */
class CompareBedBoard extends Command
{
    protected $signature = 'bedboard:compare
                            {file : The bed board JSON, e.g. database/bedboards/phmk-dms-2026-11-12.json}
                            {--hospital= : Only this hospital\'s wards (id or name)}
                            {--map=* : Pin a board ward to a SmartWard ward, as BOARD=WARD_CODE_OR_NAME (repeatable)}
                            {--status : Also list beds whose status differs (the board is a snapshot, so some drift is normal)}
                            {--follow : Make the list the master: switch its wards on and every other ward in scope off, and make the bed changes it names (asks first)}
                            {--keep=* : With --follow, leave this ward as it is (ward code or name, repeatable; "ED" keeps every Emergency ward)}
                            {--dry-run : With --follow, show the changes and stop}
                            {--force : With --follow, apply without asking}';

    protected $description = 'Compare a bed board or bed census list (JSON) with the wards and beds in SmartWard; --follow makes SmartWard\'s wards follow it';

    private const BOARD_STATES = [
        'M' => 'occupied',
        'F' => 'occupied',
        'A' => 'available',
        'AB' => 'booked',
        'D' => 'discharged',
        'DB' => 'discharged',
        'OS' => 'out of service',
    ];

    public function handle(): int
    {
        $board = $this->loadBoard((string) $this->argument('file'));
        if ($board === null) {
            return self::FAILURE;
        }

        $hospital = null;
        if (filled($this->option('hospital'))) {
            $hospital = $this->findHospital((string) $this->option('hospital'));
            if ($hospital === null) {
                return self::FAILURE;
            }
        }

        $wards = Ward::query()
            ->when($hospital, fn ($q) => $q->where('hospital_id', $hospital->id))
            ->orderBy('ward_code')
            ->get();
        $beds = Bed::whereIn('ward_id', $wards->pluck('id'))->get(['id', 'ward_id', 'bed_number', 'status', 'is_active'])
            ->groupBy('ward_id');

        $this->info($board['label'] ?? 'Bed board');
        $this->line('SmartWard: ' . ($hospital ? "hospital #{$hospital->id} {$hospital->name}" : 'all hospitals')
            . ", {$wards->count()} wards, " . $beds->flatten()->count() . ' beds');
        $this->newLine();

        $matches = $this->matchWards($board['wards'], $wards, $beds);
        if ($matches === null) {
            return self::FAILURE;
        }

        $this->wardTable($board, $matches, $beds);
        $this->unmatchedWards($wards, $matches, $beds);
        $this->bedDifferences($board['wards'], $matches, $beds);

        if ($this->option('status')) {
            $this->statusDifferences($board['wards'], $matches, $beds);
        }

        if ($this->option('follow')) {
            return $this->follow($board, $matches, $wards, $hospital);
        }

        return self::SUCCESS;
    }

    /**
     * Switches the list's wards on and every other ward in scope off. A ward's beds
     * follow it (Bed::followWard), so the beds of a ward switched off drop out of
     * every count, and come back if it is switched on again. Patients are left as
     * they are. A list ward can also name beds to add ("beds_add"), beds to switch
     * off ("beds_off"; never one with a patient or a booking) and, for a ward
     * SmartWard doesn't have, how to create it ("create": ward_code, ward_name).
     *
     * Before changing anything it saves the wards' and their beds' state to an undo
     * record in storage/app/bedboard, which bedboard:undo puts back.
     */
    private function follow(array $board, array $matches, Collection $wards, ?Hospital $hospital): int
    {
        $boardWards = $board['wards'];
        $this->line('<options=bold>Follow the list</>');

        if ($hospital === null && Hospital::count() > 1) {
            $this->error('There is more than one hospital: pick the one the list is for with --hospital, so no other hospital\'s wards are switched off. Hospitals: '
                . Hospital::orderBy('id')->get()->map(fn ($h) => "#{$h->id} {$h->name}")->implode(', '));

            return self::FAILURE;
        }
        if (! Bed::followsWards()) {
            $this->error('This database has no beds.deactivated_with_ward, so a ward\'s beds would stay in use after it is switched off. '
                . 'Run the migration 2026_11_12_000003_add_deactivated_with_ward_to_beds_table first.');

            return self::FAILURE;
        }

        $onList = collect($matches)->filter()->keyBy('id');
        $switchOn = $onList->reject->is_active;
        $keep = collect((array) $this->option('keep'))->map(fn ($k) => self::norm($k));
        $kept = fn (Ward $w) => $keep->contains(self::norm($w->ward_code)) || $keep->contains(self::norm($w->ward_name))
            || ($keep->contains('ED') && $w->isEmergency());
        $switchOff = $wards->filter(fn (Ward $w) => $w->is_active && ! $onList->has($w->id) && ! $kept($w));
        $keptWards = $wards->filter(fn (Ward $w) => $w->is_active && ! $onList->has($w->id) && $kept($w));
        if ($keptWards->isNotEmpty()) {
            $this->line('  Kept as they are (--keep): ' . $keptWards->map(fn (Ward $w) => "{$w->ward_code} {$w->ward_name}")->implode(', '));
        }

        $hospital ??= Hospital::first();
        $plan = $this->planBeds($boardWards, $matches, $hospital);
        if ($plan === null) {
            return self::FAILURE;
        }

        $unmatched = collect($boardWards)->filter(fn ($w) => $matches[$w['code']] === null && empty($w['create']))->pluck('code');
        if ($unmatched->isNotEmpty()) {
            $this->line('  Not in SmartWard, so nothing to switch on (needs the ward and its bed numbers): ' . $unmatched->implode(', '));
        }
        foreach ($plan['notes'] as $note) {
            $this->warn("  {$note}");
        }

        $bedChanges = count($plan['add']) + count($plan['on']) + count($plan['off']);
        if ($switchOn->isEmpty() && $switchOff->isEmpty() && $plan['newWards'] === [] && $bedChanges === 0) {
            $this->line('  SmartWard\'s wards already follow the list.');

            return self::SUCCESS;
        }

        $inBed = Patient::whereIn('ward_id', $switchOff->pluck('id'))
            ->where('is_active', true)
            ->whereIn('status', [Patient::STATUS_ADMITTED, Patient::STATUS_PENDING_DISCHARGE])
            ->selectRaw('ward_id, count(*) as n')
            ->groupBy('ward_id')
            ->pluck('n', 'ward_id');

        $wardRows = $switchOn->map(fn (Ward $w) => [
            '<fg=green>switch on</>', $w->ward_code, $w->ward_name, Bed::where('ward_id', $w->id)->where('deactivated_with_ward', true)->count() . ' come back', '-',
        ])->concat($switchOff->map(fn (Ward $w) => [
            '<fg=red>switch off</>', $w->ward_code, $w->ward_name, Bed::where('ward_id', $w->id)->where('is_active', true)->count(), $inBed[$w->id] ?? 0,
        ]))->concat(collect($plan['newWards'])->map(fn ($n) => [
            '<fg=green>create</>', $n['ward_code'], $n['ward_name'], count($n['beds']) . ' new', '-',
        ]))->values()->all();
        if ($wardRows !== []) {
            $this->table(['Change', 'Ward code', 'Name', 'Active beds', 'Patients in beds'], $wardRows);
        }
        if ($inBed->sum() > 0) {
            $this->warn("  {$inBed->sum()} patient(s) stay recorded in the wards switched off, but drop off the dashboards and the Command Center while those wards are off.");
        }

        $bedRows = collect($plan['add'])->map(fn ($a) => [$a['ward']->ward_code, $a['number'], '<fg=green>add</> (new bed, available)'])
            ->concat(collect($plan['on'])->map(fn ($o) => [$o['ward']->ward_code, $o['bed']->bed_number, '<fg=green>switch on</>']))
            ->concat(collect($plan['off'])->map(fn ($o) => [$o['ward']->ward_code, $o['bed']->bed_number, '<fg=red>switch off</>']))
            ->concat(collect($plan['newWards'])->flatMap(fn ($n) => array_map(fn ($b) => [$n['ward_code'], $b, '<fg=green>add</> (new ward)'], $n['beds'])))
            ->values()->all();
        if ($bedRows !== []) {
            $this->table(['Ward', 'Bed', 'Change'], $bedRows);
        }
        $this->projection($boardWards, $matches, $switchOn, $plan);

        if ($this->option('dry-run')) {
            $this->line('Dry run: nothing changed.');

            return self::SUCCESS;
        }
        if (! $this->option('force') && ! $this->confirm('Apply these changes?', false)) {
            $this->line('Nothing changed.');

            return self::SUCCESS;
        }

        $record = $this->saveUndoRecord($board, $hospital, $switchOn->concat($switchOff)
            ->concat(collect($plan['on'])->pluck('ward'))->concat(collect($plan['off'])->pluck('ward')));

        $bedsOff = 0;
        $bedsOn = 0;
        DB::transaction(function () use ($switchOn, $switchOff, $plan, $hospital, $record, &$bedsOff, &$bedsOn) {
            foreach ($switchOff as $ward) {
                $before = Bed::where('ward_id', $ward->id)->where('is_active', true)->count();
                $ward->update(['is_active' => false]);
                Bed::followWard($ward); // a no-op where the ward's own hook already did it
                $bedsOff += $before - Bed::where('ward_id', $ward->id)->where('is_active', true)->count();
            }
            foreach ($switchOn as $ward) {
                $before = Bed::where('ward_id', $ward->id)->where('is_active', true)->count();
                $ward->update(['is_active' => true]);
                Bed::followWard($ward);
                $bedsOn += Bed::where('ward_id', $ward->id)->where('is_active', true)->count() - $before;
            }

            // Beds switched by hand: they no longer follow their ward's own switch
            $now = now();
            foreach ($plan['off'] as $off) {
                DB::table('beds')->where('id', $off['bed']->id)->update(['is_active' => false, 'deactivated_with_ward' => false, 'updated_at' => $now]);
            }
            foreach ($plan['on'] as $on) {
                DB::table('beds')->where('id', $on['bed']->id)->update(['is_active' => true, 'deactivated_with_ward' => false, 'updated_at' => $now]);
            }

            $created = ['wards' => [], 'beds' => []];
            foreach ($plan['add'] as $add) {
                $created['beds'][] = $this->createBed($add['ward'], $add['number']);
            }
            foreach ($plan['newWards'] as $new) {
                $ward = Ward::create([
                    'hospital_id' => $hospital->id,
                    'ward_code' => $new['ward_code'],
                    'ward_name' => $new['ward_name'],
                    'capacity' => count($new['beds']),
                    'description' => $new['description'] ?: 'Added by bedboard:compare --follow from ' . basename((string) $this->argument('file')),
                    'is_active' => true,
                ]);
                $created['wards'][] = ['id' => $ward->id, 'ward_code' => $ward->ward_code, 'ward_name' => $ward->ward_name];
                foreach ($new['beds'] as $number) {
                    $created['beds'][] = $this->createBed($ward, $number);
                }
            }

            // Inside the transaction: if the record can't be written, nothing is changed
            $this->recordCreated($record, $created);
        });

        $this->info("Switched off {$switchOff->count()} ward(s) and {$bedsOff} bed(s) with them; switched on {$switchOn->count()} ward(s) and {$bedsOn} bed(s).");
        if ($bedChanges > 0 || $plan['newWards'] !== []) {
            $newBeds = count($plan['add']) + collect($plan['newWards'])->sum(fn ($n) => count($n['beds']));
            $this->info(sprintf('Beds: %d added, %d switched on, %d switched off; %d new ward(s).', $newBeds, count($plan['on']), count($plan['off']), count($plan['newWards'])));
        }
        $this->line('Undo record: ' . $this->relative($record));
        $this->line('To put every ward and bed back as it was: php artisan bedboard:undo ' . $this->relative($record) . ' --dry-run, then without --dry-run.');
        $this->line('Switching one ward on again on the Wards page also brings back the beds that went off with it.');

        return self::SUCCESS;
    }

    /**
     * The bed changes a list asks for: per ward, "beds_add" beds made (or switched on
     * if there but off) and "beds_off" beds switched off, except one with a patient
     * or a booking in it; a ward SmartWard doesn't have is made from its "create".
     *
     * @return array{newWards: list<array>, add: list<array>, on: list<array>, off: list<array>, notes: list<string>}|null
     */
    private function planBeds(array $boardWards, array $matches, ?Hospital $hospital): ?array
    {
        $plan = ['newWards' => [], 'add' => [], 'on' => [], 'off' => [], 'notes' => []];

        foreach ($boardWards as $boardWard) {
            $code = $boardWard['code'];
            $ward = $matches[$code];
            $add = $boardWard['beds_add'];

            if ($ward === null) {
                if (empty($boardWard['create'])) {
                    continue;
                }
                $wardCode = trim((string) ($boardWard['create']['ward_code'] ?? ''));
                $wardName = trim((string) ($boardWard['create']['ward_name'] ?? ''));
                if ($wardCode === '' || $wardName === '' || $add === []) {
                    $this->error("{$code}: \"create\" needs a ward_code and a ward_name, and \"beds_add\" its beds.");

                    return null;
                }
                if (Ward::where('ward_code', $wardCode)->exists()) {
                    $this->error("{$code}: a ward with the code {$wardCode} already exists. Pin the list ward to it with --map {$code}={$wardCode}, or change the code in the list.");

                    return null;
                }
                if ($hospital === null) {
                    $this->error("{$code}: there is no hospital to create the ward in.");

                    return null;
                }
                $plan['newWards'][] = [
                    'board' => $code, 'ward_code' => $wardCode, 'ward_name' => $wardName,
                    'description' => $boardWard['create']['description'] ?? null,
                    'beds' => array_values(array_unique($add)),
                ];

                continue;
            }

            $existing = Bed::where('ward_id', $ward->id)->get()->keyBy(fn (Bed $b) => self::norm($b->bed_number));
            foreach ($add as $number) {
                $bed = $existing[self::norm($number)] ?? null;
                if ($bed === null) {
                    $plan['add'][] = ['ward' => $ward, 'number' => $number];
                } elseif (! $bed->is_active) {
                    $plan['on'][] = ['ward' => $ward, 'bed' => $bed];
                }
            }

            foreach ($boardWard['beds_off'] as $number) {
                $bed = $existing[self::norm($number)] ?? null;
                if ($bed === null) {
                    $plan['notes'][] = "{$code}: bed {$number}, to switch off, is not in {$ward->ward_code} {$ward->ward_name}.";

                    continue;
                }
                if (! $bed->is_active) {
                    continue;
                }
                $taken = Patient::where('ward_id', $ward->id)
                    ->where('bed_number', $bed->bed_number)
                    ->where('is_active', true)
                    ->whereIn('status', [Patient::STATUS_ADMITTED, Patient::STATUS_PENDING_DISCHARGE, Patient::STATUS_PREBOOK, Patient::STATUS_PREBOOK_PENDING])
                    ->exists();
                if ($taken) {
                    $plan['notes'][] = "{$code}: bed {$bed->bed_number} has a patient or a booking in it, so it stays on. Name another bed in the list, or run again once it is free.";

                    continue;
                }
                $plan['off'][] = ['ward' => $ward, 'bed' => $bed];
            }
        }

        return $plan;
    }

    /** SmartWard's beds in the list's wards once the changes are made, against the list. */
    private function projection(array $boardWards, array $matches, Collection $switchOn, array $plan): void
    {
        $count = fn (array $items, Ward $ward) => count(array_filter($items, fn ($i) => $i['ward']->id === $ward->id));
        $after = 0;
        $list = 0;
        $differ = [];
        foreach ($boardWards as $boardWard) {
            $ward = $matches[$boardWard['code']];
            if ($ward) {
                $n = Bed::where('ward_id', $ward->id)->where('is_active', true)->count()
                    + ($switchOn->has($ward->id) ? Bed::where('ward_id', $ward->id)->where('deactivated_with_ward', true)->count() : 0)
                    + $count($plan['add'], $ward) + $count($plan['on'], $ward) - $count($plan['off'], $ward);
            } else {
                $new = collect($plan['newWards'])->firstWhere('board', $boardWard['code']);
                $n = $new ? count($new['beds']) : 0;
            }
            if ($n !== $boardWard['total']) {
                $differ[] = "{$boardWard['code']} {$n} (list {$boardWard['total']})";
            }
            $after += $n;
            $list += $boardWard['total'];
        }

        $this->line(sprintf('  After the changes: %d beds in the list\'s wards, the list %d%s', $after, $list,
            $after === $list ? ' <fg=green>(match)</>' : sprintf(' <fg=red>(%+d)</>', $after - $list)));
        if ($differ !== []) {
            $this->line('  Still different: ' . implode(', ', $differ));
        }
    }

    /** @return array{id: int, ward_id: int, bed_number: string} */
    private function createBed(Ward $ward, string $number): array
    {
        $stem = "LIST-{$ward->ward_code}-{$number}";
        $bedId = $stem;
        for ($n = 2; Bed::where('bed_id', $bedId)->exists(); $n++) {
            $bedId = "{$stem}-{$n}";
        }

        $bed = Bed::create([
            'ward_id' => $ward->id,
            'bed_number' => $number,
            'bed_id' => $bedId,
            'bed_display_name' => $number,
            'status' => 'available',
            'is_active' => true,
        ]);

        return ['id' => $bed->id, 'ward_id' => $ward->id, 'bed_number' => $number];
    }

    private function recordCreated(string $path, array $created): void
    {
        $record = json_decode((string) file_get_contents($path), true);
        $record['created_wards'] = $created['wards'];
        $record['created_beds'] = $created['beds'];
        if (file_put_contents($path, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) === false) {
            throw new \RuntimeException("Could not add the new wards and beds to the undo record {$path}, so nothing was changed.");
        }
    }

    /** @return string the record's path */
    private function saveUndoRecord(array $board, ?Hospital $hospital, Collection $affected): string
    {
        $ids = $affected->pluck('id')->unique()->values();
        $record = [
            'made_at' => now()->toIso8601String(),
            'list' => $board['label'] ?? (string) $this->argument('file'),
            'file' => (string) $this->argument('file'),
            'hospital_id' => $hospital?->id,
            'wards' => Ward::whereIn('id', $ids)->orderBy('id')->get(['id', 'ward_code', 'ward_name', 'is_active'])
                ->map(fn (Ward $w) => ['id' => $w->id, 'ward_code' => $w->ward_code, 'ward_name' => $w->ward_name, 'is_active' => (bool) $w->is_active])
                ->all(),
            'beds' => Bed::whereIn('ward_id', $ids)->orderBy('id')->get(['id', 'ward_id', 'bed_number', 'is_active', 'deactivated_with_ward'])
                ->map(fn (Bed $b) => [
                    'id' => $b->id, 'ward_id' => $b->ward_id, 'bed_number' => $b->bed_number,
                    'is_active' => (bool) $b->is_active, 'deactivated_with_ward' => (bool) $b->deactivated_with_ward,
                ])
                ->all(),
        ];

        $dir = storage_path('app/bedboard');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $stem = $dir . DIRECTORY_SEPARATOR . 'follow-' . now()->format('Ymd-His');
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
        $path = str_replace('\\', '/', $path); // Windows paths come out mixed

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }

    private function loadBoard(string $file): ?array
    {
        $path = is_file($file) ? $file : base_path($file);
        if (! is_file($path)) {
            $this->error("No such file: {$file}");

            return null;
        }

        $board = json_decode((string) file_get_contents($path), true);
        if (! is_array($board) || ! is_array($board['wards'] ?? null)) {
            $this->error("{$file} is not a bed board file: expected JSON with a \"wards\" list (" . json_last_error_msg() . ')');

            return null;
        }

        foreach ($board['wards'] as $i => $ward) {
            if (blank($ward['code'] ?? null) || ! is_numeric($ward['total'] ?? null)) {
                $this->error("Ward #" . ($i + 1) . ' in the file needs a "code" and a "total".');

                return null;
            }
            $board['wards'][$i]['beds'] = (array) ($ward['beds'] ?? []);
            $board['wards'][$i]['beds_add'] = array_values(array_map('strval', (array) ($ward['beds_add'] ?? [])));
            $board['wards'][$i]['beds_off'] = array_values(array_map('strval', (array) ($ward['beds_off'] ?? [])));
            $board['wards'][$i]['total'] = (int) $ward['total'];
        }

        return $board;
    }

    private function findHospital(string $value): ?Hospital
    {
        $hospital = ctype_digit($value)
            ? Hospital::find((int) $value)
            : (Hospital::where('name', $value)->first() ?? Hospital::where('name', 'like', "%{$value}%")->first());

        if ($hospital === null) {
            $this->error("No hospital matches \"{$value}\". Hospitals: "
                . Hospital::orderBy('id')->get()->map(fn ($h) => "#{$h->id} {$h->name}")->implode(', '));
        }

        return $hospital;
    }

    /**
     * Pairs each board ward with a SmartWard ward: a --map pin first, then the
     * ward code or a "match" name from the file, then the ward holding most of
     * its printed bed numbers. Each SmartWard ward pairs with one board ward.
     *
     * @return array<string, Ward|null>|null board code => ward
     */
    private function matchWards(array $boardWards, Collection $wards, Collection $beds): ?array
    {
        $pins = [];
        foreach ((array) $this->option('map') as $pin) {
            if (! str_contains($pin, '=')) {
                $this->error("--map takes BOARD=WARD_CODE_OR_NAME, got \"{$pin}\"");

                return null;
            }
            [$code, $target] = array_map('trim', explode('=', $pin, 2));
            $pins[self::norm($code)] = $target;
        }

        $byName = fn (string $name) => $wards->first(fn (Ward $w) => self::norm($w->ward_code) === self::norm($name)
            || self::norm($w->ward_name) === self::norm($name));

        $matches = [];
        $taken = [];
        foreach ($boardWards as $boardWard) {
            $code = $boardWard['code'];
            if (isset($pins[self::norm($code)])) {
                $ward = $byName($pins[self::norm($code)]);
                if ($ward === null) {
                    $this->error("--map {$code}={$pins[self::norm($code)]}: no SmartWard ward has that code or name.");

                    return null;
                }
            } else {
                // Several wards can answer to the names (a demo "ICU" beside the real one): take the
                // one holding most printed beds, then the one closest in size
                $printed = array_map([self::class, 'norm'], array_keys($boardWard['beds']));
                $ward = collect(array_merge((array) ($boardWard['match'] ?? []), [$code]))
                    ->map($byName)
                    ->filter(fn (?Ward $w) => $w && ! isset($taken[$w->id]))
                    ->unique('id')
                    ->sortBy(function (Ward $w) use ($beds, $printed, $boardWard) {
                        $wardBeds = $beds[$w->id] ?? collect();
                        $hits = count(array_intersect($printed, $wardBeds->map(fn ($b) => self::norm($b->bed_number))->all()));

                        return [-$hits, abs($wardBeds->where('is_active', true)->count() - $boardWard['total'])];
                    })
                    ->first();
            }
            $matches[$code] = $ward;
            if ($ward) {
                $taken[$ward->id] = true;
            }
        }

        // Still unpaired: the ward with most of the printed bed numbers, if it has at least half
        foreach ($boardWards as $boardWard) {
            if ($matches[$boardWard['code']] !== null || $boardWard['beds'] === []) {
                continue;
            }
            $printed = array_map([self::class, 'norm'], array_keys($boardWard['beds']));
            $best = null;
            $bestHits = 0;
            foreach ($wards as $ward) {
                if (isset($taken[$ward->id])) {
                    continue;
                }
                $numbers = ($beds[$ward->id] ?? collect())->map(fn ($b) => self::norm($b->bed_number))->all();
                $hits = count(array_intersect($printed, $numbers));
                if ($hits > $bestHits) {
                    [$best, $bestHits] = [$ward, $hits];
                }
            }
            if ($best && $bestHits * 2 >= count($printed)) {
                $matches[$boardWard['code']] = $best;
                $taken[$best->id] = true;
            }
        }

        return $matches;
    }

    private function wardTable(array $board, array $matches, Collection $beds): void
    {
        $rows = [];
        $sums = [];
        foreach ($board['wards'] as $boardWard) {
            $code = $boardWard['code'];
            $group = $boardWard['group'] ?? '-';
            $ward = $matches[$code];
            $sums[$group]['board'] = ($sums[$group]['board'] ?? 0) + $boardWard['total'];
            // A list of counts only (a bed census) has no statuses or bed numbers to compare
            $boardOs = $boardWard['counts']['OS'] ?? ($boardWard['beds'] === [] ? '-' : count(array_keys($boardWard['beds'], 'OS', true)));

            if ($ward === null) {
                $rows[] = [$code, $group, '<fg=red>not found</>', $boardWard['total'], '-', '-', $boardOs, '-', '-', '<fg=red>missing</>'];

                continue;
            }

            $wardBeds = $beds[$ward->id] ?? collect();
            $active = $wardBeds->where('is_active', true);
            $inactive = $wardBeds->count() - $active->count();
            $sums[$group]['sw'] = ($sums[$group]['sw'] ?? 0) + $active->count();

            $numbers = $wardBeds->map(fn ($b) => self::norm($b->bed_number))->all();
            $printed = array_keys($boardWard['beds']);
            $found = count(array_filter($printed, fn ($n) => in_array(self::norm($n), $numbers, true)));
            $diff = $active->count() - $boardWard['total'];

            // Beds the list itself switches off are meant to be inactive
            $listedOff = array_map([self::class, 'norm'], $boardWard['beds_off']);
            $unexpectedOff = $wardBeds->where('is_active', false)->reject(fn ($b) => in_array(self::norm($b->bed_number), $listedOff, true))->count();
            $ok = $diff === 0 && $found === count($printed) && $unexpectedOff === 0 && $ward->is_active;
            $rows[] = [
                $code,
                $group,
                "{$ward->ward_code} {$ward->ward_name}" . ($ward->is_active ? '' : ' <fg=yellow>(inactive)</>'),
                $boardWard['total'],
                $active->count() . ($inactive ? " <fg=yellow>(+{$inactive} inactive)</>" : ''),
                $diff === 0 ? '0' : sprintf('<fg=red>%+d</>', $diff),
                $boardOs,
                $active->where('status', Bed::STATUS_MAINTENANCE)->count(),
                $printed === [] ? '-' : "{$found}/" . count($printed),
                $ok ? '<fg=green>OK</>' : '<fg=yellow>check</>',
            ];
        }

        $this->table(
            ['Board ward', 'Group', 'SmartWard ward', 'Board beds', 'SW beds', 'Diff', 'Board OS', 'SW maint.', 'Printed beds found', 'Result'],
            $rows
        );

        $boardTotal = 0;
        $swTotal = 0;
        foreach ($sums as $group => $sum) {
            $stated = $board['totals'][$group] ?? null;
            $this->line(sprintf('  %-12s board %3d%s, SmartWard %3d',
                $group,
                $sum['board'],
                $stated !== null && (int) $stated !== $sum['board'] ? " <fg=red>(the board states {$stated})</>" : '',
                $sum['sw'] ?? 0));
            $boardTotal += $sum['board'];
            $swTotal += $sum['sw'] ?? 0;
        }
        $this->line(sprintf('  %-12s board %3d, SmartWard %3d%s', 'Total', $boardTotal, $swTotal,
            $boardTotal === $swTotal ? '' : sprintf(' <fg=red>(%+d)</>', $swTotal - $boardTotal)));
        $this->line('  (SW beds = active beds; SW maint. = active beds under maintenance; Board OS = out of service on the board)');
        $this->newLine();
    }

    private function unmatchedWards(Collection $wards, array $matches, Collection $beds): void
    {
        $matchedIds = collect($matches)->filter()->map->id->all();
        $others = $wards->reject(fn (Ward $w) => in_array($w->id, $matchedIds, true));
        if ($others->isEmpty()) {
            $this->line('Every SmartWard ward is on the board.');
            $this->newLine();

            return;
        }

        $this->line("<options=bold>SmartWard wards not on the board ({$others->count()}):</>");
        $this->table(['Ward code', 'Name', 'Hospital', 'Active beds', 'Ward active'], $others->map(fn (Ward $w) => [
            $w->ward_code,
            $w->ward_name,
            $w->hospital_id,
            ($beds[$w->id] ?? collect())->where('is_active', true)->count(),
            $w->is_active ? 'yes' : 'no',
        ])->all());
    }

    private function bedDifferences(array $boardWards, array $matches, Collection $beds): void
    {
        $this->line('<options=bold>Bed differences</>');
        if (collect($boardWards)->every(fn ($w) => $w['beds'] === [])) {
            $this->line('  The list gives no bed numbers, so only the counts above are compared.');
            $this->newLine();

            return;
        }
        $any = false;

        foreach ($boardWards as $boardWard) {
            $ward = $matches[$boardWard['code']];
            if ($ward === null || $boardWard['beds'] === []) {
                continue;
            }

            $wardBeds = ($beds[$ward->id] ?? collect())->keyBy(fn ($b) => self::norm($b->bed_number));
            $printed = collect(array_keys($boardWard['beds']))->keyBy(fn ($n) => self::norm($n));
            $hidden = max(0, $boardWard['total'] - $printed->count());

            $missing = $printed->reject(fn ($n, $key) => $wardBeds->has($key));
            $inactive = $printed->filter(fn ($n, $key) => $wardBeds->has($key) && ! $wardBeds[$key]->is_active);
            $notPrinted = $wardBeds->filter(fn ($b, $key) => $b->is_active && ! $printed->has($key))
                ->map->bed_number;

            if ($missing->isEmpty() && $inactive->isEmpty() && $notPrinted->count() === $hidden) {
                continue;
            }

            $any = true;
            $this->line("  <fg=cyan>{$boardWard['code']}</> = {$ward->ward_code} {$ward->ward_name}");
            if ($missing->isNotEmpty()) {
                $this->line("    On the board, not in SmartWard ({$missing->count()}): " . $missing->implode(', '));
            }
            if ($inactive->isNotEmpty()) {
                $this->line("    On the board, inactive in SmartWard ({$inactive->count()}): " . $inactive->implode(', '));
            }
            if ($notPrinted->isNotEmpty()) {
                $extra = max(0, $notPrinted->count() - $hidden);
                $why = $hidden === 0
                    ? 'the board lists every bed, so these are extra'
                    : ($extra === 0
                        ? "the print hid {$hidden} row(s), so they may be those"
                        : "the print hid {$hidden} row(s), so at least {$extra} are extra");
                $this->line("    In SmartWard, not on the printout ({$notPrinted->count()}; {$why}): " . $notPrinted->sort(SORT_NATURAL)->implode(', '));
            } elseif ($hidden > 0) {
                $this->line("    The print hid {$hidden} row(s) and SmartWard has no beds beyond the printed ones.");
            }
        }

        if (! $any) {
            $this->line('  None: every printed bed is in SmartWard, and SmartWard has no beds beyond the rows the print hid.');
        }
        $this->newLine();
    }

    private function statusDifferences(array $boardWards, array $matches, Collection $beds): void
    {
        $wardIds = collect($matches)->filter()->map->id->values();
        $patients = Patient::whereIn('ward_id', $wardIds)
            ->where('is_active', true)
            ->whereIn('status', [Patient::STATUS_ADMITTED, Patient::STATUS_PENDING_DISCHARGE, Patient::STATUS_PREBOOK, Patient::STATUS_PREBOOK_PENDING])
            ->get(['ward_id', 'bed_number', 'status']);
        $inBed = [];
        $booked = [];
        foreach ($patients as $patient) {
            $key = $patient->ward_id . '|' . self::norm((string) $patient->bed_number);
            in_array($patient->status, [Patient::STATUS_ADMITTED, Patient::STATUS_PENDING_DISCHARGE], true)
                ? $inBed[$key] = true
                : $booked[$key] = true;
        }

        $rows = [];
        $compared = 0;
        foreach ($boardWards as $boardWard) {
            $ward = $matches[$boardWard['code']];
            if ($ward === null) {
                continue;
            }
            $wardBeds = ($beds[$ward->id] ?? collect())->keyBy(fn ($b) => self::norm($b->bed_number));

            foreach ($boardWard['beds'] as $number => $code) {
                $bed = $wardBeds[self::norm($number)] ?? null;
                if ($bed === null) {
                    continue;
                }
                $compared++;
                $key = $ward->id . '|' . self::norm($number);
                $smartward = match (true) {
                    ! $bed->is_active => 'inactive',
                    $bed->status === Bed::STATUS_MAINTENANCE => 'out of service',
                    isset($inBed[$key]) || $bed->status === 'occupied' => 'occupied',
                    isset($booked[$key]) || $bed->status === 'reserved' => 'booked',
                    default => 'available',
                };
                $onBoard = self::BOARD_STATES[strtoupper((string) $code)] ?? strtolower((string) $code);

                if (! self::sameState($onBoard, $smartward)) {
                    $rows[] = [$boardWard['code'], $number, "{$code} ({$onBoard})", $smartward . " (status {$bed->status})"];
                }
            }
        }

        $this->line('<options=bold>Status differences</> (' . count($rows) . " of {$compared} printed beds; the board is a snapshot, SmartWard is now)");
        if ($rows !== []) {
            $this->table(['Board ward', 'Bed', 'Board', 'SmartWard'], $rows);
        }
    }

    private static function sameState(string $board, string $smartward): bool
    {
        return match ($board) {
            // A discharged patient may still be in the bed, or it may already be free
            'discharged' => in_array($smartward, ['occupied', 'available', 'booked'], true),
            'available', 'booked' => in_array($smartward, ['available', 'booked'], true),
            'out of service' => in_array($smartward, ['out of service', 'inactive'], true),
            default => $board === $smartward,
        };
    }

    /** Upper-case letters and digits only, so "B441-C", "b441c" and "B441 C" compare equal. */
    private static function norm(?string $value): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $value));
    }
}
