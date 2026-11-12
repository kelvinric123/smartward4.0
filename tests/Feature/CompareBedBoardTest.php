<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * bedboard:compare pairs each bed board ward with a SmartWard ward and reports
 * bed counts and bed numbers that differ, allowing for rows the printout hid.
 */
class CompareBedBoardTest extends TestCase
{
    use RefreshDatabase;

    private Hospital $hospital;
    private string $file;
    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        // Undo records go to a throwaway storage folder, not the app's
        $this->storage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bedboard-test-' . uniqid();
        mkdir($this->storage);
        $this->app->useStoragePath($this->storage);

        $this->hospital = Hospital::create(['name' => 'Board Hospital']);
        $other = Hospital::create(['name' => 'Other Hospital']);

        // Ward C4: 3 beds, the board printed 2 of its 3 rows
        $this->ward($this->hospital, 'CP-1', 'WARD C4', ['C401-A' => 'occupied', 'C401-B' => 'available', 'C402-A' => 'available']);
        // A demo ICU beside the real one, whose bed numbers differ from the board's
        $this->ward($this->hospital, 'ICU', 'ICU', ['X1' => 'available']);
        $this->ward($this->hospital, 'CP-2', 'INTENSIVE CARE UNIT', ['ICU-1' => 'occupied', 'ICU-3' => 'available']);
        // No name in common with the board: paired by its bed numbers
        $this->ward($this->hospital, 'CP-3', 'Medical', ['B1' => 'occupied', 'B2' => 'occupied']);
        $this->ward($this->hospital, 'CP-9', 'NURSERY', ['N1' => 'available']);
        // Another hospital's ward with the board's code
        $this->ward($other, 'WC4', 'Elsewhere C4', ['E1' => 'available']);

        $this->file = tempnam(sys_get_temp_dir(), 'board');
        file_put_contents($this->file, json_encode([
            'label' => 'Test board',
            'totals' => ['Licensed' => 8],
            'wards' => [
                ['code' => 'WC4', 'group' => 'Licensed', 'match' => ['WARD C4'], 'total' => 3, 'counts' => ['OS' => 1],
                    'beds' => ['C401-A' => 'M', 'C401 B' => 'OS']],
                ['code' => 'ICU', 'group' => 'Licensed', 'match' => ['INTENSIVE CARE UNIT'], 'total' => 2,
                    'beds' => ['ICU-1' => 'M', 'ICU-2' => 'A']],
                ['code' => 'MWD', 'group' => 'Licensed', 'total' => 2, 'beds' => ['B1' => 'F', 'B2' => 'F']],
                ['code' => 'GONE', 'group' => 'Licensed', 'total' => 1, 'beds' => ['Z1' => 'A']],
            ],
        ]));
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        foreach (glob($this->storage . '/app/bedboard/*') ?: [] as $record) {
            @unlink($record);
        }
        @rmdir($this->storage . '/app/bedboard');
        @rmdir($this->storage . '/app');
        @rmdir($this->storage);
        parent::tearDown();
    }

    /** @return array<string, mixed> every ward's and bed's switch, to compare before and after */
    private function switches(): array
    {
        return [
            'wards' => Ward::orderBy('id')->pluck('is_active', 'ward_code')->map(fn ($v) => (bool) $v)->all(),
            'beds' => Bed::orderBy('id')->get()->mapWithKeys(fn (Bed $b) => [
                $b->bed_id => [(bool) $b->is_active, (bool) $b->deactivated_with_ward],
            ])->all(),
        ];
    }

    private function records(): array
    {
        return glob($this->storage . '/app/bedboard/follow-*.json') ?: [];
    }

    private function ward(Hospital $hospital, string $code, string $name, array $beds): Ward
    {
        $ward = Ward::create(['hospital_id' => $hospital->id, 'ward_code' => $code, 'ward_name' => $name, 'capacity' => count($beds), 'is_active' => true]);
        foreach ($beds as $number => $status) {
            Bed::create(['ward_id' => $ward->id, 'bed_number' => $number, 'bed_id' => "{$code}-{$number}", 'bed_display_name' => $number,
                'status' => $status, 'is_active' => true]);
        }

        return $ward;
    }

    private function compare(array $options = []): array
    {
        $code = Artisan::call('bedboard:compare', ['file' => $this->file, '--hospital' => (string) $this->hospital->id] + $options);

        return [$code, Artisan::output()];
    }

    private function row(string $output, string $boardWard): string
    {
        preg_match('/^\| ' . preg_quote($boardWard, '/') . ' .*$/m', $output, $m);

        return preg_replace('/\s*\|\s*/', '|', $m[0] ?? '');
    }

    public function test_pairs_wards_and_compares_their_beds(): void
    {
        [$code, $output] = $this->compare();

        $this->assertSame(0, $code);
        // By "match" name; the hidden row explains C402-A; "C401 B" is C401-B
        $this->assertSame('|WC4|Licensed|CP-1 WARD C4|3|3|0|1|0|2/2|OK|', $this->row($output, 'WC4'));
        // The real ICU, not the demo ward named like the board code
        $this->assertSame('|ICU|Licensed|CP-2 INTENSIVE CARE UNIT|2|2|0|0|0|1/2|check|', $this->row($output, 'ICU'));
        // By bed numbers
        $this->assertSame('|MWD|Licensed|CP-3 Medical|2|2|0|0|0|2/2|OK|', $this->row($output, 'MWD'));
        $this->assertSame('|GONE|Licensed|not found|1|-|-|0|-|-|missing|', $this->row($output, 'GONE'));
        // The wards add up to the board's own total, so no note about it
        $this->assertMatchesRegularExpression('/Licensed\s+board\s+8, SmartWard\s+7\R/', $output);
        $this->assertStringNotContainsString('the board states', $output);
        $this->assertMatchesRegularExpression('/Total\s+board\s+8, SmartWard\s+7 \(-1\)/', $output);

        // The other hospital's WC4 is out of scope; the demo ICU and the nursery are left over
        $this->assertStringNotContainsString('Elsewhere C4', $output);
        $this->assertMatchesRegularExpression('/\| ICU\s+\| ICU\s+\|/', $output);
        $this->assertStringContainsString('NURSERY', $output);

        $this->assertStringContainsString('On the board, not in SmartWard (1): ICU-2', $output);
        $this->assertStringContainsString('In SmartWard, not on the printout (1; the board lists every bed, so these are extra): ICU-3', $output);
        $this->assertStringNotContainsString('C402-A', $output);
    }

    public function test_a_census_list_compares_counts_only(): void
    {
        file_put_contents($this->file, json_encode([
            'totals' => ['Licensed' => 5],
            'wards' => [
                ['code' => 'C4', 'group' => 'Licensed', 'match' => ['WARD C4'], 'total' => 3],
                ['code' => 'SCN', 'group' => 'Licensed', 'total' => 2],
            ],
        ]));

        [$code, $output] = $this->compare();

        $this->assertSame(0, $code);
        $this->assertSame('|C4|Licensed|CP-1 WARD C4|3|3|0|-|0|-|OK|', $this->row($output, 'C4'));
        $this->assertSame('|SCN|Licensed|not found|2|-|-|-|-|-|missing|', $this->row($output, 'SCN'));
        $this->assertStringContainsString('The list gives no bed numbers, so only the counts above are compared.', $output);
    }

    public function test_follow_switches_the_other_wards_off_and_the_list_wards_on(): void
    {
        Ward::where('ward_code', 'CP-3')->first()->update(['is_active' => false]);

        [$code, $output] = $this->compare(['--follow' => true, '--force' => true]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Not in SmartWard, so nothing to switch on (needs the ward and its bed numbers): GONE', $output);
        $this->assertStringContainsString('Switched off 2 ward(s) and 2 bed(s) with them; switched on 1 ward(s) and 2 bed(s).', $output);

        $active = fn (string $code) => Ward::where('ward_code', $code)->first();
        foreach (['ICU', 'CP-9'] as $off) {
            $this->assertFalse($active($off)->is_active, $off);
            $this->assertSame(0, $active($off)->beds()->where('is_active', true)->count(), $off);
        }
        foreach (['CP-1', 'CP-2', 'CP-3'] as $on) {
            $this->assertTrue($active($on)->is_active, $on);
            $this->assertSame($active($on)->beds()->count(), $active($on)->beds()->where('is_active', true)->count(), $on);
        }
        // Another hospital's ward is out of scope
        $this->assertTrue($active('WC4')->is_active);

        [, $output] = $this->compare(['--follow' => true, '--force' => true]);
        $this->assertStringContainsString("SmartWard's wards already follow the list.", $output);
    }

    public function test_follow_dry_run_changes_nothing(): void
    {
        $before = $this->switches();

        [$code, $output] = $this->compare(['--follow' => true, '--dry-run' => true]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('switch off', $output);
        $this->assertStringContainsString('Dry run: nothing changed.', $output);
        $this->assertSame($before, $this->switches());
        $this->assertSame([], $this->records());
    }

    public function test_undo_puts_every_ward_and_bed_back(): void
    {
        // Switched off before, on its own and with its ward: both must come back exactly so
        Bed::where('bed_number', 'N1')->first()->update(['is_active' => false]);
        Ward::where('ward_code', 'CP-3')->first()->update(['is_active' => false]);
        $before = $this->switches();

        [, $output] = $this->compare(['--follow' => true, '--force' => true]);
        $this->assertCount(1, $this->records());
        $record = $this->records()[0];
        $this->assertStringContainsString('Undo record: ', $output);
        $this->assertNotSame($before, $this->switches());
        $after = $this->switches();

        Artisan::call('bedboard:undo');
        $this->assertStringContainsString(basename($record), Artisan::output());

        Artisan::call('bedboard:undo', ['record' => $record, '--dry-run' => true]);
        $this->assertStringContainsString('Dry run: nothing changed.', Artisan::output());
        $this->assertSame($after, $this->switches());

        Artisan::call('bedboard:undo', ['record' => $record, '--force' => true]);
        $this->assertStringContainsString('Put back 3 ward(s)', Artisan::output());
        $this->assertSame($before, $this->switches());

        Artisan::call('bedboard:undo', ['record' => $record, '--force' => true]);
        $this->assertStringContainsString('Nothing to undo.', Artisan::output());
    }

    private function patientIn(Ward $ward, string $bed): Patient
    {
        static $n = 0;
        $n++;

        return Patient::create([
            'name' => "Patient {$n}", 'mrn' => "MRN-BB{$n}", 'rn' => "RN-BB{$n}", 'ic_passport' => "IC-BB{$n}",
            'gender' => 'Female', 'phone' => '', 'ward_id' => $ward->id, 'bed_number' => $bed,
            'status' => Patient::STATUS_ADMITTED, 'is_active' => true, 'admitted_at' => now(),
        ]);
    }

    public function test_follow_makes_the_bed_changes_the_list_names_and_undo_takes_them_back(): void
    {
        $c4 = Ward::where('ward_code', 'CP-1')->first();
        $this->patientIn($c4, 'C401-A');
        Bed::where('bed_number', 'ICU-3')->first()->update(['is_active' => false]);
        file_put_contents($this->file, json_encode([
            'wards' => [
                ['code' => 'WC4', 'match' => ['WARD C4'], 'total' => 3, 'beds_add' => ['C403-A'], 'beds_off' => ['C401-A', 'C402-A', 'ZZ9']],
                ['code' => 'ICU', 'match' => ['INTENSIVE CARE UNIT'], 'total' => 2, 'beds_add' => ['ICU-3']],
                ['code' => 'SCN', 'total' => 2, 'create' => ['ward_code' => 'SCN', 'ward_name' => 'SPECIAL CARE NURSERY'], 'beds_add' => ['SCN-1', 'SCN-2']],
            ],
        ]));
        $before = $this->switches();

        [, $output] = $this->compare(['--follow' => true, '--dry-run' => true]);
        $this->assertStringContainsString("After the changes: 7 beds in the list's wards, the list 7 (match)", $output);
        $this->assertSame($before, $this->switches());

        [, $output] = $this->compare(['--follow' => true, '--force' => true]);
        // A bed with a patient in it stays on; one the ward doesn't have is reported
        $this->assertStringContainsString('bed C401-A has a patient or a booking in it, so it stays on', $output);
        $this->assertStringContainsString('bed ZZ9, to switch off, is not in CP-1 WARD C4', $output);
        $this->assertStringContainsString('Beds: 3 added, 1 switched on, 1 switched off; 1 new ward(s).', $output);

        $bed = fn (string $number) => Bed::where('bed_number', $number)->first();
        $this->assertTrue($bed('C401-A')->is_active);
        $this->assertFalse($bed('C402-A')->is_active);
        $this->assertTrue($bed('C403-A')->is_active);
        $this->assertSame($c4->id, $bed('C403-A')->ward_id);
        $this->assertTrue($bed('ICU-3')->is_active);
        $scn = Ward::where('ward_code', 'SCN')->first();
        $this->assertSame($this->hospital->id, $scn->hospital_id);
        $this->assertSame(['SCN-1', 'SCN-2'], $scn->beds()->where('is_active', true)->orderBy('bed_number')->pluck('bed_number')->all());

        // Running it again changes nothing: the list's new ward now pairs by its code
        [, $again] = $this->compare(['--follow' => true, '--dry-run' => true]);
        $this->assertStringContainsString("SmartWard's wards already follow the list.", $again);

        // Somebody was put in the new C403-A since: undo only switches that bed off
        $this->patientIn($c4, 'C403-A');
        $record = $this->records()[0];
        Artisan::call('bedboard:undo', ['record' => $record, '--force' => true]);
        $undo = Artisan::output();
        $this->assertStringContainsString('removed 1 and switched off 1 of the wards/beds --follow added', $undo);

        $this->assertNull(Ward::where('ward_code', 'SCN')->first());
        $this->assertSame(0, Bed::whereIn('bed_number', ['SCN-1', 'SCN-2'])->count());
        $this->assertFalse($bed('C403-A')->is_active);
        $after = $this->switches();
        unset($after['beds']['LIST-CP-1-C403-A']);
        $this->assertSame($before, $after);
    }

    public function test_follow_leaves_kept_wards_alone(): void
    {
        [, $output] = $this->compare(['--follow' => true, '--force' => true, '--keep' => ['nursery']]);

        $this->assertStringContainsString('Kept as they are (--keep): CP-9 NURSERY', $output);
        $this->assertTrue(Ward::where('ward_code', 'CP-9')->first()->is_active);
        $this->assertFalse(Ward::where('ward_code', 'ICU')->first()->is_active);
    }

    public function test_follow_needs_a_hospital_when_there_are_several(): void
    {
        $code = Artisan::call('bedboard:compare', ['file' => $this->file, '--follow' => true, '--force' => true]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('There is more than one hospital', Artisan::output());
        $this->assertTrue(Ward::where('ward_code', 'WC4')->first()->is_active);
    }

    public function test_map_pins_a_ward_and_rejects_an_unknown_one(): void
    {
        [, $output] = $this->compare(['--map' => ['ICU=ICU']]);
        $this->assertSame('|ICU|Licensed|ICU ICU|2|1|-1|0|0|0/2|check|', $this->row($output, 'ICU'));

        [$code, $output] = $this->compare(['--map' => ['ICU=NOPE']]);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('no SmartWard ward has that code or name', $output);
    }

    public function test_status_lists_beds_whose_state_differs(): void
    {
        [, $output] = $this->compare(['--status' => true]);

        // ICU-2 isn't in SmartWard, so 5 of the 6 printed beds are compared
        $this->assertStringContainsString('Status differences (1 of 5 printed beds', $output);
        $this->assertMatchesRegularExpression('/\| WC4\s+\| C401 B\s+\| OS \(out of service\)\s+\| available \(status available\)/', $output);
        $this->assertDoesNotMatchRegularExpression('/\| MWD\s+\| B[12] /', $output);
        $this->assertDoesNotMatchRegularExpression('/\| WC4\s+\| C401-A /', $output);
    }
}
