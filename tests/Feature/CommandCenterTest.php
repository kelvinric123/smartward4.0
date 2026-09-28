<?php

namespace Tests\Feature;

use App\Models\AdmissionLog;
use App\Models\Bed;
use App\Models\ClinicalIndicator;
use App\Models\ClinicalIndicatorScore;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Models\VitalSign;
use App\Models\Ward;
use App\Models\WardNotification;
use App\Models\WardType;
use App\Support\ClinicalIndicatorReadings;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Command Center: its live board of what needs attention across the
 * wards right now, and the period analytics beside it.
 */
class CommandCenterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Hospital $hospital;
    private Ward $icu;
    private Ward $medical;
    private array $patients = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-09-26 14:00:00'));

        $this->admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $this->hospital = Hospital::create(['name' => 'Test Hospital']);

        // GCS is monitored: due after an hour, overdue after two
        $gcs = ClinicalIndicator::where('code', 'GCS')->firstOrFail();
        $gcs->update(['monitoring_enabled' => true, 'monitoring_suggested_minutes' => 60, 'monitoring_warning_minutes' => 120]);

        $icuType = WardType::create(['code' => 'ICU', 'name' => 'ICU', 'is_critical_care' => true, 'is_active' => true]);
        $icuType->clinicalIndicators()->sync(ClinicalIndicator::whereIn('code', ['VENT', 'HEMO', 'GCS'])->pluck('id'));
        $medicalType = WardType::create(['code' => 'MED', 'name' => 'Medical', 'is_active' => true]);

        $this->icu = $this->ward('ICU1', 'Intensive Care Unit', $icuType, 2);
        $this->medical = $this->ward('MW1', 'Medical Ward 1', $medicalType, 4);

        $this->patients = [
            'ventilated' => $this->admit($this->icu, 'ICU01', 'Lim Ah Kow'),
            'unassessed' => $this->admit($this->icu, 'ICU02', 'Tan Mei Ling'),
            'calling' => $this->admit($this->medical, 'MW01', 'Siti Aminah'),
            'old_ews' => $this->admit($this->medical, 'MW02', 'Rajesh Kumar'),
            'going_home' => $this->admit($this->medical, 'MW03', 'Wong Kar Wai', Patient::STATUS_PENDING_DISCHARGE),
        ];
        Patient::create($this->patientFields($this->medical, null, 'Nur Aisyah') + ['status' => Patient::STATUS_PREBOOK, 'admitted_at' => null]);

        // Deteriorating and escalating on the ventilator, GCS scored just now
        $this->vitals('ventilated', ['pulse_rate' => 130, 'respiratory_rate' => 28, 'systolic_bp' => 85], now()->subMinutes(20));
        $this->readings('ventilated', 'VENT', [28, 70, 12, 38, 400], now()->subHour());
        $this->score('ventilated', 'GCS', [3, 4, 5], now()->subMinutes(10));
        // Hemodynamics in range, but GCS never scored since admission
        $this->vitals('unassessed', [], now()->subHour());
        $this->readings('unassessed', 'HEMO', [120, 70, 87, 6, 5.0, 2.8], now()->subMinutes(30));
        // Well, but has called for help
        $this->vitals('calling', [], now()->subHour());
        WardNotification::create([
            'ward_id' => $this->medical->id, 'patient_id' => $this->patients['calling']->id, 'bed_number' => 'MW01',
            'type' => WardNotification::TYPE_PATIENT_REQUEST, 'severity' => WardNotification::SEVERITY_URGENT,
            'message' => 'Call bell', 'status' => WardNotification::STATUS_PENDING,
        ]);
        // A raised EWS, but from vital signs two days old
        $this->vitals('old_ews', ['pulse_rate' => 110, 'respiratory_rate' => 22, 'spo2' => 94], now()->subDays(2));
    }

    private function ward(string $code, string $name, WardType $type, int $beds): Ward
    {
        $ward = Ward::create([
            'hospital_id' => $this->hospital->id, 'ward_code' => $code, 'ward_name' => $name,
            'ward_type_id' => $type->id, 'is_active' => true,
        ]);

        foreach (range(1, $beds) as $i) {
            $number = ($code === 'ICU1' ? 'ICU' : 'MW') . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            Bed::create([
                'ward_id' => $ward->id, 'bed_number' => $number, 'bed_id' => $code . '-' . $number,
                'bed_display_name' => $number, 'status' => 'available', 'is_active' => true,
            ]);
        }

        return $ward;
    }

    private function patientFields(Ward $ward, ?string $bed, string $name): array
    {
        return [
            'name' => $name, 'mrn' => 'MRN' . (Patient::count() + 1), 'rn' => 'RN' . (Patient::count() + 1),
            'ic_passport' => sprintf('600101-10-%04d', Patient::count() + 1), 'age' => 60, 'gender' => 'Male',
            'phone' => '012-0000000', 'ward_id' => $ward->id, 'bed_number' => $bed, 'is_active' => true,
        ];
    }

    private function admit(Ward $ward, string $bed, string $name, string $status = Patient::STATUS_ADMITTED): Patient
    {
        Bed::where('ward_id', $ward->id)->where('bed_number', $bed)->update(['status' => 'occupied']);

        return Patient::create($this->patientFields($ward, $bed, $name) + ['status' => $status, 'admitted_at' => now()->subDays(3)]);
    }

    private function vitals(string $patient, array $values, $at): void
    {
        VitalSign::create($values + [
            'patient_id' => $this->patients[$patient]->id, 'systolic_bp' => 120, 'diastolic_bp' => 75, 'pulse_rate' => 80,
            'temperature' => 36.8, 'spo2' => 98, 'respiratory_rate' => 16, 'reading_type' => 'full', 'recorded_at' => $at,
        ]);
    }

    private function readings(string $patient, string $code, array $values, $at): void
    {
        $indicator = ClinicalIndicator::where('code', $code)->firstOrFail();
        $evaluated = ClinicalIndicatorReadings::evaluate($indicator->definition(), $values);
        $this->record($patient, $indicator, $evaluated['score'], $evaluated['items'], $at);
    }

    private function score(string $patient, string $code, array $picks, $at): void
    {
        $indicator = ClinicalIndicator::where('code', $code)->firstOrFail();
        $items = collect($indicator->definition()['items'])
            ->map(fn ($item, $i) => ['name' => $item['name'], 'label' => '', 'value' => $picks[$i], 'abbr' => $item['abbr'] ?? null])
            ->all();
        $this->record($patient, $indicator, array_sum($picks), $items, $at);
    }

    private function record(string $patient, ClinicalIndicator $indicator, int $score, array $items, $at): void
    {
        $record = new ClinicalIndicatorScore([
            'patient_id' => $this->patients[$patient]->id, 'clinical_indicator_id' => $indicator->id,
            'ward_id' => $this->patients[$patient]->ward_id, 'score' => $score, 'item_scores' => $items,
            'recorded_by' => $this->admin->id, 'recorded_at' => $at,
        ]);
        $record->applyBand($indicator->code)->save();
    }

    public function test_only_admins_see_the_command_center(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_NURSE]))
            ->get(route('command-center.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_HOSPITAL_ADMIN]))
            ->get(route('command-center.index'))
            ->assertOk();
    }

    public function test_the_live_board_opens_first_with_the_whole_hospital_right_now(): void
    {
        $response = $this->actingAs($this->admin)->get(route('command-center.index'))
            ->assertOk()
            ->assertSee("tab: 'live'", false)
            ->assertSee('Needs attention now')
            ->assertSee('Wards right now');

        $this->assertEquals([
            'inpatients' => 5, 'pending_discharge' => 1, 'incoming' => 1,
            'cc_wards' => 1, 'cc_beds' => 2, 'cc_occupied' => 2, 'ventilated' => 1,
            'ews_urgent' => 1, 'ews_warning' => 0, 'escalations' => 1,
            'overdue_care' => 1, 'assessments_overdue' => 1, 'doses_overdue' => 0, 'fluid_alerts' => 0,
            'transfusing' => 0, 'alerts' => 1, 'alerts_urgent' => 1,
        ], $response->viewData('live')['stats']);
    }

    public function test_the_attention_list_puts_the_most_urgent_first_and_says_why(): void
    {
        $live = $this->actingAs($this->admin)->get(route('command-center.index'))->viewData('live');
        $attention = collect($live['attention'])->keyBy('name');

        // Deteriorating and escalating, then an overdue scale, then a call for help; the well stay off
        $this->assertSame(['Lim Ah Kow', 'Tan Mei Ling', 'Siti Aminah'], $attention->keys()->all());
        $this->assertSame(3, $live['attention_total']);

        $ventilated = $attention['Lim Ah Kow'];
        $this->assertSame(['score' => 6, 'severity' => 'urgent'], array_intersect_key($ventilated['ews'], ['score' => 0, 'severity' => 0]));
        $this->assertTrue($ventilated['ventilated']);
        $this->assertTrue($ventilated['critical_care']);
        $this->assertSame('VENT', $ventilated['escalations'][0]['code']);
        $this->assertSame('EtCO2 28 mmHg ↓↓ · FiO2 70 % ↑↑ · PIP 38 cmH₂O ↑↑', $ventilated['escalations'][0]['readings']);
        $this->assertSame([], $ventilated['assessments_overdue']);

        $this->assertSame(['GCS'], array_column($attention['Tan Mei Ling']['assessments_overdue'], 'code'));
        $this->assertSame([], $attention['Tan Mei Ling']['escalations']);
        $this->assertSame(1, $attention['Siti Aminah']['alerts_urgent']);

        // The raised EWS from two days ago is not counted as current
        $this->assertArrayNotHasKey('Rajesh Kumar', $attention->all());

        $this->actingAs($this->admin)->get(route('command-center.index'))
            ->assertSeeInOrder(['Lim Ah Kow', 'Tan Mei Ling', 'Siti Aminah'])
            ->assertSee('GCS</span> overdue', false)
            // An escalation opens the patient's readings for that scale
            ->assertSee(route('ward.patient-details', ['patient_id' => $this->patients['ventilated']->id, 'open_tab' => 'indicator-' . ClinicalIndicator::where('code', 'VENT')->value('id')]));
    }

    public function test_each_ward_has_its_line_and_opens_its_own_dashboard(): void
    {
        $wards = collect($this->actingAs($this->admin)->get(route('command-center.index'))->viewData('live')['wards'])->keyBy('code');

        $this->assertTrue($wards['ICU1']['critical_care']);
        $this->assertSame(route('critical-care.dashboard', ['ward_id' => $this->icu->id]), $wards['ICU1']['dashboard_url']);
        $this->assertSame(['beds' => 2, 'occupied' => 2, 'occupancy' => 100, 'ews_urgent' => 1, 'escalations' => 1, 'ventilated' => 1, 'overdue_care' => 1],
            array_intersect_key($wards['ICU1'], array_flip(['beds', 'occupied', 'occupancy', 'ews_urgent', 'escalations', 'ventilated', 'overdue_care'])));

        $this->assertFalse($wards['MW1']['critical_care']);
        $this->assertSame(route('ward.dashboard', ['ward_id' => $this->medical->id]), $wards['MW1']['dashboard_url']);
        $this->assertSame(['beds' => 4, 'occupied' => 3, 'occupancy' => 75, 'pending_discharge' => 1, 'incoming' => 1, 'ews_warning' => 0, 'alerts' => 1, 'alerts_urgent' => 1],
            array_intersect_key($wards['MW1'], array_flip(['beds', 'occupied', 'occupancy', 'pending_discharge', 'incoming', 'ews_warning', 'alerts', 'alerts_urgent'])));
    }

    public function test_choosing_a_ward_narrows_the_live_board_to_it(): void
    {
        $live = $this->actingAs($this->admin)
            ->get(route('command-center.index', ['ward_id' => $this->medical->id, 'tab' => 'live']))
            ->viewData('live');

        $this->assertSame(['MW1'], array_column($live['wards'], 'code'));
        $this->assertSame(3, $live['stats']['inpatients']);
        $this->assertSame(0, $live['stats']['cc_wards']);
        $this->assertSame(['Siti Aminah'], array_column($live['attention'], 'name'));
    }

    public function test_the_analytics_tabs_count_the_period_as_before(): void
    {
        $log = fn (Ward $ward, string $at) => AdmissionLog::create([
            'patient_id' => $this->patients['calling']->id, 'ward_id' => $ward->id, 'user_id' => $this->admin->id,
            'bed_number' => 'X', 'action' => 'admit', 'patient_name' => 'X', 'mrn' => 'X', 'source' => 'manual', 'admitted_at' => $at,
        ]);
        $log($this->icu, '2026-02-10 09:00:00');
        $log($this->icu, '2026-02-28 23:59:00');
        $log($this->medical, '2026-03-01 00:00:00');
        $log($this->icu, '2026-09-02 08:00:00');
        $log($this->medical, '2025-12-31 23:00:00');
        Patient::create($this->patientFields($this->medical, null, 'Discharged') + [
            'status' => 'discharged', 'is_active' => false, 'admitted_at' => '2026-09-01 10:00:00', 'discharged_at' => '2026-09-03 12:00:00',
        ]);

        $response = $this->actingAs($this->admin)->get(route('command-center.index', ['tab' => 'ward']))
            ->assertOk()
            ->assertSee("tab: 'ward'", false);

        $monthly = $response->viewData('monthly');
        $this->assertSame([0, 2, 1, 0, 0, 0, 0, 0, 1, 0, 0, 0], $monthly['admissions']);
        $this->assertSame([0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0], $monthly['discharges']);
        $this->assertSame('Jan', $monthly['labels'][0]);
        // The four sets of vital signs from setUp, all taken in September
        $this->assertSame([0, 0, 0, 0, 0, 0, 0, 0, 4, 0, 0, 0], $monthly['vital_signs']);

        // This month's flow per ward
        $breakdown = collect($response->viewData('wardBreakdown'))->keyBy('ward_code');
        $this->assertSame(1, $breakdown['ICU1']['admissions']);
        $this->assertSame(0, $breakdown['ICU1']['discharges']);
        $this->assertSame(0, $breakdown['MW1']['admissions']);
        $this->assertSame(1, $breakdown['MW1']['discharges']);

        // One ward's months
        $icuOnly = $this->actingAs($this->admin)->get(route('command-center.index', ['ward_id' => $this->icu->id]))->viewData('monthly');
        $this->assertSame([0, 2, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0], $icuOnly['admissions']);
    }
}
