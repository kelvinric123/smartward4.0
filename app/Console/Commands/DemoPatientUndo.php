<?php

namespace App\Console\Commands;

use App\Models\Bed;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Reverses a demo:swap from its undo record: the real patients' rows go back
 * exactly as they were (column for column), their beds point at them again and
 * their care providers are active again; the demo patients, their care providers
 * and the admission log entries the swap wrote are deleted. A demo patient that
 * something else now points at (vital signs taken during the showcase) is
 * discharged instead of deleted. The beds are repainted on the e-ink screens with
 * the real patients, unless --no-screens.
 */
class DemoPatientUndo extends Command
{
    protected $signature = 'demo:undo
                            {record? : The undo record, e.g. storage/app/demo/swap-20261112-160000.json (leave out to list them)}
                            {--dry-run : Show what would be put back and stop}
                            {--no-screens : Put the data back without pushing to the e-ink screens}
                            {--force : Put it back without asking}';

    protected $description = 'Bring back the real patients a demo:swap discharged, and remove the demo ones';

    public function handle(): int
    {
        if (blank($this->argument('record'))) {
            return $this->listRecords();
        }

        $file = (string) $this->argument('record');
        $path = is_file($file) ? $file : base_path($file);
        $record = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (! is_array($record) || ! is_array($record['patients'] ?? null) || ! is_array($record['created'] ?? null)) {
            $this->error("{$file} is not a demo:swap undo record (or does not exist). Run demo:undo without a file to list them.");

            return self::FAILURE;
        }

        $created = $record['created'] + ['patients' => [], 'logs' => [], 'providers' => []];
        $patients = collect($record['patients'])->keyBy('id');
        $current = DB::table('patients')->whereIn('id', $patients->keys())->get()->keyBy('id');

        $restore = $patients->filter(fn ($was, $id) => isset($current[$id]) && (array) $current[$id] != $was);
        $gone = $patients->keys()->diff($current->keys());
        $demoLeft = DB::table('patients')->whereIn('id', $created['patients'])->count();
        $logsLeft = DB::table('admission_logs')->whereIn('id', $created['logs'])->count();
        $providersLeft = DB::table('patient_care_providers')->whereIn('id', $created['providers'])->count();
        $providersOff = DB::table('patient_care_providers')->whereIn('id', $record['providers'] ?? [])->where('is_active', false)->count();
        $beds = collect($record['beds'] ?? []);
        $bedsToFix = Bed::whereIn('id', $beds->pluck('id'))->get()->filter(function (Bed $bed) use ($beds) {
            $was = $beds->firstWhere('id', $bed->id);

            return $bed->status != $was['status'] || $bed->patient_id != $was['patient_id'];
        });

        $this->info("Undo demo swap of {$record['made_at']} (hospital #{$record['hospital_id']} " . ($record['hospital'] ?? '') . ')');
        $this->table(['What', 'Count'], [
            ['Real patients to put back as they were', $restore->count()],
            ['Beds to point at them again', $bedsToFix->count()],
            ['Their care providers to switch on again', $providersOff],
            ['Demo patients to remove', $demoLeft],
            ['Demo care providers to remove', $providersLeft],
            ['Admission log entries of the swap to remove', $logsLeft],
        ]);
        if ($gone->isNotEmpty()) {
            $this->warn("  {$gone->count()} real patient(s) in the record were deleted since; skipped.");
        }
        if ($restore->isEmpty() && $bedsToFix->isEmpty() && $demoLeft + $logsLeft + $providersLeft + $providersOff === 0) {
            $this->line('Everything is already as it was before the swap. Nothing to undo.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->line('Dry run: nothing changed.');

            return self::SUCCESS;
        }
        if (! $this->option('force') && ! $this->confirm('Bring the real patients back and remove the demo ones?', false)) {
            $this->line('Nothing changed.');

            return self::SUCCESS;
        }

        $kept = 0;
        DB::transaction(function () use ($restore, $bedsToFix, $beds, $record, $created, &$kept) {
            // The rows exactly as they were, timestamps included
            foreach ($restore as $id => $was) {
                DB::table('patients')->where('id', $id)->update(collect($was)->except('id')->all());
            }
            DB::table('patient_care_providers')->whereIn('id', $record['providers'] ?? [])->update(['is_active' => true]);

            // The beds through the model, so the e-ink screens show the real patients again
            $fixBeds = function () use ($bedsToFix, $beds) {
                foreach ($bedsToFix as $bed) {
                    $was = $beds->firstWhere('id', $bed->id);
                    $bed->update(['status' => $was['status'], 'patient_id' => $was['patient_id']]);
                }
            };
            $this->option('no-screens') ? Model::withoutEvents($fixBeds) : $fixBeds();

            DB::table('admission_logs')->whereIn('id', $created['logs'])->delete();
            DB::table('patient_care_providers')->whereIn('id', $created['providers'])->delete();
            foreach ($created['patients'] as $id) {
                try {
                    DB::table('patients')->where('id', $id)->delete();
                } catch (QueryException $e) {
                    // Something now points at this demo patient: discharge it instead
                    DB::table('patients')->where('id', $id)->update([
                        'status' => 'discharged', 'is_active' => false, 'ward_id' => null, 'bed_number' => null,
                        'target_bed_number' => null, 'discharged_at' => now(), 'updated_at' => now(),
                    ]);
                    $kept++;
                }
            }
        });

        $this->info(sprintf('Put back %d real patient(s) and %d bed(s); removed %d demo patient(s)%s and %d log entries.',
            $restore->count(), $bedsToFix->count(), count($created['patients']) - $kept,
            $kept ? " ({$kept} more discharged instead: other records point at them)" : '', count($created['logs'])));

        return self::SUCCESS;
    }

    private function listRecords(): int
    {
        $files = glob(storage_path('app/demo/swap-*.json')) ?: [];
        if ($files === []) {
            $this->line('No demo:swap undo records in storage/app/demo.');

            return self::SUCCESS;
        }

        rsort($files);
        $this->table(['Record', 'Swapped at', 'Hospital', 'Real patients', 'Demo patients'], array_map(function ($file) {
            $record = json_decode((string) file_get_contents($file), true) ?: [];

            return [
                'storage/app/demo/' . basename($file),
                $record['made_at'] ?? '?',
                ($record['hospital_id'] ?? '?') . ' ' . ($record['hospital'] ?? ''),
                count($record['patients'] ?? []),
                count($record['created']['patients'] ?? []),
            ];
        }, $files));

        return self::SUCCESS;
    }
}
