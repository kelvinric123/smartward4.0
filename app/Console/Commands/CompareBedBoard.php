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
                            {--follow : Make the list the master: switch its wards on and every other ward in scope off (asks first)}
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
     * they are. Bed numbers are not touched: the list's bed counts that still differ
     * are in the table above.
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

        $unmatched = collect($boardWards)->filter(fn ($w) => $matches[$w['code']] === null)->pluck('code');
        if ($unmatched->isNotEmpty()) {
            $this->line('  Not in SmartWard, so nothing to switch on (needs the ward and its bed numbers): ' . $unmatched->implode(', '));
        }

        if ($switchOn->isEmpty() && $switchOff->isEmpty()) {
            $this->line('  SmartWard\'s wards already follow the list.');

            return self::SUCCESS;
        }

        $inBed = Patient::whereIn('ward_id', $switchOff->pluck('id'))
            ->where('is_active', true)
            ->whereIn('status', [Patient::STATUS_ADMITTED, Patient::STATUS_PENDING_DISCHARGE])
            ->selectRaw('ward_id, count(*) as n')
            ->groupBy('ward_id')
            ->pluck('n', 'ward_id');

        $this->table(['Change', 'Ward code', 'Name', 'Active beds', 'Patients in beds'], $switchOn->map(fn (Ward $w) => [
            '<fg=green>switch on</>', $w->ward_code, $w->ward_name, Bed::where('ward_id', $w->id)->where('deactivated_with_ward', true)->count() . ' come back', '-',
        ])->concat($switchOff->map(fn (Ward $w) => [
            '<fg=red>switch off</>', $w->ward_code, $w->ward_name, Bed::where('ward_id', $w->id)->where('is_active', true)->count(), $inBed[$w->id] ?? 0,
        ]))->values()->all());
        if ($inBed->sum() > 0) {
            $this->warn("  {$inBed->sum()} patient(s) stay recorded in the wards switched off, but drop off the dashboards and the Command Center while those wards are off.");
        }

        if ($this->option('dry-run')) {
            $this->line('Dry run: nothing changed.');

            return self::SUCCESS;
        }
        if (! $this->option('force') && ! $this->confirm('Apply these changes?', false)) {
            $this->line('Nothing changed.');

            return self::SUCCESS;
        }

        $record = $this->saveUndoRecord($board, $hospital, $switchOn->concat($switchOff));

        $bedsOff = 0;
        $bedsOn = 0;
        DB::transaction(function () use ($switchOn, $switchOff, &$bedsOff, &$bedsOn) {
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
        });

        $this->info("Switched off {$switchOff->count()} ward(s) and {$bedsOff} bed(s) with them; switched on {$switchOn->count()} ward(s) and {$bedsOn} bed(s).");
        $this->line('Undo record: ' . $this->relative($record));
        $this->line('To put every ward and bed back as it was: php artisan bedboard:undo ' . $this->relative($record) . ' --dry-run, then without --dry-run.');
        $this->line('Switching one ward on again on the Wards page also brings back the beds that went off with it.');

        return self::SUCCESS;
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

            $ok = $diff === 0 && $found === count($printed) && $inactive === 0 && $ward->is_active;
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
