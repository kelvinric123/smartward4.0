<?php

namespace Tests\Feature;

use App\Models\AdmissionLog;
use App\Models\Bed;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\PatientCareProvider;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * demo:swap discharges the real patients and puts demo-000001... in their beds;
 * demo:undo puts every real row back exactly and removes the demo ones.
 */
class DemoPatientSwapTest extends TestCase
{
    use RefreshDatabase;

    private string $storage;
    private Ward $open;
    private Ward $closed;
    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->storage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'demo-test-' . uniqid();
        mkdir($this->storage);
        $this->app->useStoragePath($this->storage);

        $hospital = Hospital::create(['name' => 'Demo Hospital']);
        $this->open = Ward::create(['hospital_id' => $hospital->id, 'ward_code' => 'WA', 'ward_name' => 'Ward A', 'capacity' => 3, 'is_active' => true]);
        $this->closed = Ward::create(['hospital_id' => $hospital->id, 'ward_code' => 'WB', 'ward_name' => 'Ward B', 'capacity' => 1, 'is_active' => true]);
        foreach (['A1', 'A2', 'A3'] as $number) {
            $this->bed($this->open, $number);
        }
        $this->bed($this->closed, 'B1');

        $p1 = $this->patient('Real One', $this->open, 'A1', Patient::STATUS_ADMITTED, ['age' => 70, 'gender' => 'Male', 'diet_types' => ['soft']]);
        Bed::where('bed_number', 'A1')->update(['patient_id' => $p1->id, 'status' => 'occupied']);
        PatientCareProvider::create(['patient_id' => $p1->id, 'role' => PatientCareProvider::ROLE_ATTENDING, 'doctor_code' => 'DR1',
            'doctor_name' => 'Dr One', 'source' => PatientCareProvider::SOURCE_CPLUS, 'assigned_at' => now(), 'is_active' => true]);

        $p2 = $this->patient('Real Two', $this->open, 'A2', Patient::STATUS_PREBOOK, ['booked_at' => now()->subHour()]);
        Bed::where('bed_number', 'A2')->update(['patient_id' => $p2->id, 'status' => 'reserved']);
        // Waiting for A1 to be free
        $this->patient('Real Three', $this->open, null, Patient::STATUS_PREBOOK_PENDING, ['target_bed_number' => 'A1']);

        $p4 = $this->patient('Real Four', $this->closed, 'B1', Patient::STATUS_ADMITTED);
        Bed::where('bed_number', 'B1')->update(['patient_id' => $p4->id, 'status' => 'occupied']);
        // A ward switched off: its patient goes without a demo one
        $this->closed->update(['is_active' => false]);

        // Booked without a ward yet: swapped too (only one hospital)
        $this->patient('Real Five', null, null, Patient::STATUS_PREBOOK, ['booked_at' => now()->subDay()]);

        // Already gone before the swap: left alone
        $this->patient('Old Discharge', null, null, Patient::STATUS_DISCHARGED, ['is_active' => false]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->storage . '/app/demo/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->storage . '/app/demo');
        @rmdir($this->storage . '/app');
        @rmdir($this->storage);
        parent::tearDown();
    }

    private function bed(Ward $ward, string $number): Bed
    {
        return Bed::create(['ward_id' => $ward->id, 'bed_number' => $number, 'bed_id' => "{$ward->ward_code}-{$number}",
            'bed_display_name' => $number, 'status' => 'available', 'is_active' => true]);
    }

    private function patient(string $name, ?Ward $ward, ?string $bed, string $status, array $extra = []): Patient
    {
        $this->n++;

        return Patient::create($extra + [
            'name' => $name, 'mrn' => "MRN{$this->n}", 'rn' => "RN{$this->n}", 'ic_passport' => "IC{$this->n}",
            'gender' => 'Female', 'phone' => '0123', 'ward_id' => $ward?->id, 'bed_number' => $bed,
            'status' => $status, 'is_active' => true, 'admitted_at' => now()->subDays(3),
        ]);
    }

    /** Every row the swap may touch, to compare before and after the undo. */
    private function state(): array
    {
        return [
            'patients' => DB::table('patients')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            'beds' => DB::table('beds')->orderBy('id')->get(['id', 'status', 'patient_id', 'is_active'])->map(fn ($r) => (array) $r)->all(),
            'providers' => DB::table('patient_care_providers')->orderBy('id')->get(['id', 'patient_id', 'is_active'])->map(fn ($r) => (array) $r)->all(),
            'logs' => DB::table('admission_logs')->orderBy('id')->pluck('id')->all(),
        ];
    }

    private function records(): array
    {
        return glob($this->storage . '/app/demo/swap-*.json') ?: [];
    }

    public function test_dry_run_changes_nothing(): void
    {
        $before = $this->state();

        $code = Artisan::call('demo:swap', ['--dry-run' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('2 real patient(s) to discharge (logged as discharges now), 3 booking(s) to cancel, 4 demo patient(s) to admit or book now.', $output);
        $this->assertStringContainsString('Dry run: nothing changed.', $output);
        $this->assertSame($before, $this->state());
        $this->assertSame([], $this->records());
    }

    public function test_swap_puts_demo_patients_in_and_undo_brings_the_real_ones_back(): void
    {
        $before = $this->state();

        Artisan::call('demo:swap', ['--force' => true]);
        $this->assertStringContainsString('Discharged 2 and cancelled 3 real patient booking(s); 4 demo patient(s) in.', Artisan::output());

        // The real patients: discharged (logged), bookings cancelled, nobody left in a bed
        foreach (['Real One', 'Real Four'] as $name) {
            $p = Patient::where('name', $name)->first();
            $this->assertSame(Patient::STATUS_DISCHARGED, $p->status);
            $this->assertFalse($p->is_active);
            $this->assertNull($p->ward_id);
            $this->assertTrue(AdmissionLog::where('patient_id', $p->id)->where('action', 'discharge')->where('source', 'demo-swap')->exists());
        }
        foreach (['Real Two', 'Real Three', 'Real Five'] as $name) {
            $this->assertSame(Patient::STATUS_CANCELLED, Patient::where('name', $name)->first()->status);
        }
        $this->assertSame(Patient::STATUS_DISCHARGED, Patient::where('name', 'Old Discharge')->first()->status);

        // The demo patients: the same beds and bookings, numbered from demo-000001
        $demo = Patient::where('mrn', 'like', 'DEMO%')->orderBy('name')->get();
        $this->assertSame(["demo-000001", "demo-000002", "demo-000003", "demo-000004"], $demo->pluck('name')->all());
        $inA1 = Patient::where('ward_id', $this->open->id)->where('bed_number', 'A1')->where('is_active', true)->first();
        $this->assertStringStartsWith('demo-', $inA1->name);
        $this->assertSame(Patient::STATUS_ADMITTED, $inA1->status);
        $this->assertSame(70, (int) $inA1->age);
        $this->assertSame('Male', $inA1->gender);
        $this->assertSame(['soft'], $inA1->diet_types);
        $this->assertTrue($inA1->admitted_at->isToday());
        $this->assertSame($inA1->id, Bed::where('bed_number', 'A1')->value('patient_id'));
        $this->assertTrue(AdmissionLog::where('patient_id', $inA1->id)->where('action', 'admit')->exists());
        $this->assertSame('Dr One', PatientCareProvider::where('patient_id', $inA1->id)->where('is_active', true)->value('doctor_name'));

        $bookedA2 = Patient::where('ward_id', $this->open->id)->where('bed_number', 'A2')->where('is_active', true)->first();
        $this->assertSame(Patient::STATUS_PREBOOK, $bookedA2->status);
        $this->assertSame('reserved', Bed::where('bed_number', 'A2')->value('status'));
        $this->assertSame($bookedA2->id, Bed::where('bed_number', 'A2')->value('patient_id'));
        $this->assertSame(1, Patient::where('mrn', 'like', 'DEMO%')->where('status', Patient::STATUS_PREBOOK_PENDING)->where('target_bed_number', 'A1')->count());
        // The ward that is off gets no demo patient, and its bed is free
        $this->assertNull(Bed::where('bed_number', 'B1')->value('patient_id'));
        $this->assertSame(0, Patient::where('ward_id', $this->closed->id)->where('is_active', true)->count());

        // Again: nothing left to swap
        Artisan::call('demo:swap', ['--force' => true]);
        $this->assertStringContainsString('No real patients left: 4 demo patient(s) are in already.', Artisan::output());

        $record = $this->records()[0];
        Artisan::call('demo:undo');
        $this->assertStringContainsString(basename($record), Artisan::output());

        $swapped = $this->state();
        Artisan::call('demo:undo', ['record' => $record, '--dry-run' => true]);
        $this->assertStringContainsString('Dry run: nothing changed.', Artisan::output());
        $this->assertSame($swapped, $this->state());

        Artisan::call('demo:undo', ['record' => $record, '--force' => true]);
        $this->assertStringContainsString('Put back 5 real patient(s)', Artisan::output());
        $this->assertSame($before, $this->state());

        Artisan::call('demo:undo', ['record' => $record, '--force' => true]);
        $this->assertStringContainsString('Nothing to undo.', Artisan::output());
    }
}
