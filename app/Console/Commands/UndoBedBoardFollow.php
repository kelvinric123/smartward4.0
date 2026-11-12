<?php

namespace App\Console\Commands;

use App\Models\Bed;
use App\Models\Ward;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Puts wards and beds back the way an undo record from `bedboard:compare --follow`
 * saw them before it changed anything: each ward's is_active, and each of their
 * beds' is_active and deactivated_with_ward. Nothing else is touched (patients,
 * bed statuses, names); wards and beds deleted since are skipped, ones added since
 * are left alone.
 */
class UndoBedBoardFollow extends Command
{
    protected $signature = 'bedboard:undo
                            {record? : The undo record, e.g. storage/app/bedboard/follow-20261112-153000.json (leave out to list them)}
                            {--dry-run : Show what would be put back and stop}
                            {--force : Put it back without asking}';

    protected $description = 'Undo a bedboard:compare --follow from its undo record';

    public function handle(): int
    {
        if (blank($this->argument('record'))) {
            return $this->listRecords();
        }

        $file = (string) $this->argument('record');
        $path = is_file($file) ? $file : base_path($file);
        $record = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (! is_array($record) || ! is_array($record['wards'] ?? null) || ! is_array($record['beds'] ?? null)) {
            $this->error("{$file} is not a bedboard undo record (or does not exist). Run bedboard:undo without a file to list them.");

            return self::FAILURE;
        }

        $this->info('Undo: ' . ($record['list'] ?? '?') . ', followed at ' . ($record['made_at'] ?? '?'));

        $wards = Ward::whereIn('id', array_column($record['wards'], 'id'))->get()->keyBy('id');
        $wardRows = [];
        $wardChanges = [];
        foreach ($record['wards'] as $was) {
            $ward = $wards[$was['id']] ?? null;
            if ($ward === null) {
                $wardRows[] = [$was['ward_code'], $was['ward_name'], 'deleted since', '-'];

                continue;
            }
            if ((bool) $ward->is_active !== (bool) $was['is_active']) {
                $wardChanges[$ward->id] = (bool) $was['is_active'];
                $wardRows[] = [$ward->ward_code, $ward->ward_name, $ward->is_active ? 'on' : 'off', $was['is_active'] ? '<fg=green>on</>' : '<fg=red>off</>'];
            }
        }

        $hasFlag = Bed::followsWards();
        $beds = Bed::whereIn('id', array_column($record['beds'], 'id'))->get()->keyBy('id');
        $bedChanges = [];
        $missingBeds = 0;
        foreach ($record['beds'] as $was) {
            $bed = $beds[$was['id']] ?? null;
            if ($bed === null) {
                $missingBeds++;

                continue;
            }
            $change = [];
            if ((bool) $bed->is_active !== (bool) $was['is_active']) {
                $change['is_active'] = (bool) $was['is_active'];
            }
            if ($hasFlag && (bool) $bed->deactivated_with_ward !== (bool) $was['deactivated_with_ward']) {
                $change['deactivated_with_ward'] = (bool) $was['deactivated_with_ward'];
            }
            if ($change) {
                $bedChanges[$bed->id] = $change;
            }
        }
        $bedsOn = count(array_filter($bedChanges, fn ($c) => ($c['is_active'] ?? null) === true));
        $bedsOff = count(array_filter($bedChanges, fn ($c) => ($c['is_active'] ?? null) === false));

        if ($wardRows) {
            $this->table(['Ward code', 'Name', 'Now', 'Put back to'], $wardRows);
        }
        if ($missingBeds) {
            $this->warn("  {$missingBeds} bed(s) in the record were deleted since; skipped.");
        }
        if ($wardChanges === [] && $bedChanges === []) {
            $this->line('Every ward and bed in the record is already as it was. Nothing to undo.');

            return self::SUCCESS;
        }
        $this->line(sprintf('  %d ward(s) and %d bed(s) to put back (%d bed(s) back on, %d off).', count($wardChanges), count($bedChanges), $bedsOn, $bedsOff));

        if ($this->option('dry-run')) {
            $this->line('Dry run: nothing changed.');

            return self::SUCCESS;
        }
        if (! $this->option('force') && ! $this->confirm('Put these back?', false)) {
            $this->line('Nothing changed.');

            return self::SUCCESS;
        }

        // Straight to the tables: the exact saved values, without the ward's own
        // beds-follow hook second-guessing them
        DB::transaction(function () use ($wardChanges, $bedChanges) {
            $now = now();
            foreach ($wardChanges as $id => $active) {
                DB::table('wards')->where('id', $id)->update(['is_active' => $active, 'updated_at' => $now]);
            }
            foreach ($bedChanges as $id => $change) {
                DB::table('beds')->where('id', $id)->update($change + ['updated_at' => $now]);
            }
        });

        $this->info(sprintf('Put back %d ward(s) and %d bed(s) as they were at %s.', count($wardChanges), count($bedChanges), $record['made_at'] ?? '?'));

        return self::SUCCESS;
    }

    private function listRecords(): int
    {
        $files = glob(storage_path('app/bedboard/follow-*.json')) ?: [];
        if ($files === []) {
            $this->line('No undo records in storage/app/bedboard: bedboard:compare --follow has not changed anything here.');

            return self::SUCCESS;
        }

        rsort($files);
        $this->table(['Record', 'Followed at', 'List', 'Wards'], array_map(function ($file) {
            $record = json_decode((string) file_get_contents($file), true) ?: [];

            return [
                'storage/app/bedboard/' . basename($file),
                $record['made_at'] ?? '?',
                $record['list'] ?? '?',
                count($record['wards'] ?? []),
            ];
        }, $files));

        return self::SUCCESS;
    }
}
