<?php

namespace App\Console\Commands;

use App\Models\Bed;
use App\Models\Patient;
use App\Models\Ward;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Puts wards and beds back the way an undo record from `bedboard:compare --follow`
 * saw them before it changed anything: each ward's is_active, and each of their
 * beds' is_active and deactivated_with_ward. Wards and beds --follow added are
 * removed, or only switched off if a patient has been in them since. Nothing else
 * is touched (patients, bed statuses, names); wards and beds deleted since are
 * skipped, ones added by other means are left alone.
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

        // What --follow made goes again; anything a patient has been put in since is
        // only switched off, so their record keeps its ward and bed
        [$remove, $switchOff] = $this->createdToUndo($record);

        if ($wardRows) {
            $this->table(['Ward code', 'Name', 'Now', 'Put back to'], $wardRows);
        }
        if ($missingBeds) {
            $this->warn("  {$missingBeds} bed(s) in the record were deleted since; skipped.");
        }
        $createdRows = array_merge(
            array_map(fn ($r) => [$r['kind'], $r['label'], '<fg=red>remove</> (added by --follow)'], $remove),
            array_map(fn ($r) => [$r['kind'], $r['label'], '<fg=yellow>switch off</> (added by --follow, has had patients since)'], $switchOff),
        );
        if ($createdRows !== []) {
            $this->table(['Added', 'Ward / bed', 'Undo'], $createdRows);
        }
        if ($wardChanges === [] && $bedChanges === [] && $createdRows === []) {
            $this->line('Every ward and bed in the record is already as it was. Nothing to undo.');

            return self::SUCCESS;
        }
        $this->line(sprintf('  %d ward(s) and %d bed(s) to put back (%d bed(s) back on, %d off); %d added ward(s)/bed(s) to remove, %d to switch off.',
            count($wardChanges), count($bedChanges), $bedsOn, $bedsOff, count($remove), count($switchOff)));

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
        $removed = 0;
        DB::transaction(function () use ($wardChanges, $bedChanges, $remove, &$switchOff, &$removed) {
            $now = now();
            foreach ($wardChanges as $id => $active) {
                DB::table('wards')->where('id', $id)->update(['is_active' => $active, 'updated_at' => $now]);
            }
            foreach ($bedChanges as $id => $change) {
                DB::table('beds')->where('id', $id)->update($change + ['updated_at' => $now]);
            }

            // Beds first, then wards (a ward's beds go with it: the foreign key cascades)
            foreach (array_merge(array_filter($remove, fn ($r) => $r['kind'] === 'bed'), array_filter($remove, fn ($r) => $r['kind'] === 'ward')) as $row) {
                try {
                    DB::table($row['kind'] === 'bed' ? 'beds' : 'wards')->where('id', $row['id'])->delete();
                    $removed++;
                } catch (QueryException $e) {
                    // Something else points at it now (a dashboard setting, a gateway): switch it off instead
                    $this->warn("  Could not remove {$row['label']} ({$e->getCode()}), switching it off instead.");
                    $switchOff[] = $row;
                }
            }
            foreach ($switchOff as $row) {
                if ($row['kind'] === 'bed') {
                    DB::table('beds')->where('id', $row['id'])->update(['is_active' => false, 'deactivated_with_ward' => false, 'updated_at' => $now]);
                } else {
                    DB::table('wards')->where('id', $row['id'])->update(['is_active' => false, 'updated_at' => $now]);
                    Bed::followWard(Ward::find($row['id']));
                }
            }
        });

        $this->info(sprintf('Put back %d ward(s) and %d bed(s) as they were at %s; removed %d and switched off %d of the wards/beds --follow added.',
            count($wardChanges), count($bedChanges), $record['made_at'] ?? '?', $removed, count($switchOff)));

        return self::SUCCESS;
    }

    /**
     * The wards and beds --follow added, split into those to remove and those to
     * only switch off because a patient has been in them since.
     *
     * @return array{0: list<array>, 1: list<array>}
     */
    private function createdToUndo(array $record): array
    {
        $remove = [];
        $switchOff = [];
        $newWardIds = array_column($record['created_wards'] ?? [], 'id');

        foreach ($record['created_wards'] ?? [] as $was) {
            $ward = Ward::find($was['id']);
            if ($ward === null) {
                continue;
            }
            $row = ['kind' => 'ward', 'id' => $ward->id, 'label' => "{$ward->ward_code} {$ward->ward_name}"];
            if (! Patient::where('ward_id', $ward->id)->exists()) {
                $remove[] = $row;
            } elseif ($ward->is_active) {
                $switchOff[] = $row;
            }
        }

        foreach ($record['created_beds'] ?? [] as $was) {
            $bed = Bed::find($was['id']);
            // A new ward's beds go, or go off, with it
            if ($bed === null || in_array($bed->ward_id, $newWardIds, true)) {
                continue;
            }
            $row = ['kind' => 'bed', 'id' => $bed->id, 'label' => ($bed->ward?->ward_code ?? '?') . " {$bed->bed_number}"];
            if (! Patient::where('ward_id', $bed->ward_id)->where('bed_number', $bed->bed_number)->exists()) {
                $remove[] = $row;
            } elseif ($bed->is_active) {
                $switchOff[] = $row;
            }
        }

        return [$remove, $switchOff];
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
