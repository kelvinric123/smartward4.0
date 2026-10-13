<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\ClinicalIndicator;
use App\Models\ClinicalIndicatorScore;
use App\Models\Hospital;
use App\Models\Infusion;
use App\Models\InfusionPump;
use App\Models\IntegrationSetting;
use App\Models\Nurse;
use App\Models\NurseRosterEntry;
use App\Models\OxygenTherapyChange;
use App\Models\Patient;
use App\Models\PatientAcuityRating;
use App\Models\ShiftSetting;
use App\Models\User;
use App\Models\VitalSign;
use App\Models\Ward;
use App\Models\WardDashboardSetting;
use App\Models\WardRosterSetting;
use App\Models\WardType;
use App\Services\InfusionEngineClient;
use App\Services\NurseScheduling\AcuityRatingCheck;
use App\Services\NurseScheduling\AcuitySettings;
use App\Services\NurseScheduling\AcuityStaffing;
use App\Services\NurseScheduling\PatientAcuity;
use App\Services\NurseScheduling\RosterRules;
use App\Services\NurseScheduling\ScheduleSnapshot;
use App\Services\NurseScheduling\WardShifts;
use App\Services\NurseScheduling\WorkloadCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * The AI Nurse Schedule's Patient acuity tab: the level the indicators suggest
 * for each patient, the registered nurses each shift needs for them, nurses'
 * ratings (saved by anyone signed in), the ward's staffing settings and the
 * minimum staff (editors only), and the rating check.
 *
 * Monday 5 Oct 2026, 10:00: the AM shift is on. Ward 6 has beds B1 to B4.
 */
class PatientAcuityTest extends TestCase
{
    use RefreshDatabase;

    private Ward $ward;
    private User $manager;
    private User $nurseUser;
    /** @var array<string, Bed> */
    private array $beds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));

        $hospital = Hospital::create(['name' => 'Test Hospital']);
        $this->ward = Ward::create(['hospital_id' => $hospital->id, 'ward_code' => 'W6', 'ward_name' => 'Ward 6', 'is_active' => true]);
        foreach (ShiftSetting::getDefaults() as $shift) {
            ShiftSetting::create($shift + ['ward_id' => $this->ward->id, 'is_active' => true]);
        }
        foreach (['B1', 'B2', 'B3', 'B4'] as $number) {
            $this->beds[$number] = Bed::create(['ward_id' => $this->ward->id, 'bed_number' => $number, 'bed_id' => 'W6-' . $number, 'bed_display_name' => $number, 'status' => 'occupied', 'is_active' => true]);
        }

        $this->manager = User::factory()->create(['name' => 'Sister Wong']);
        $this->nurseUser = User::factory()->create(['name' => 'Nurse Nadia', 'role' => User::ROLE_NURSE]);
    }

    private function patient(string $bed, array $fields = [], ?Ward $ward = null): Patient
    {
        $ward ??= $this->ward;

        return Patient::create($fields + [
            'name' => 'Patient ' . $ward->ward_code . $bed, 'mrn' => 'MRN-' . $ward->ward_code . $bed, 'rn' => 'RN-' . $ward->ward_code . $bed,
            'ic_passport' => 'IC-' . $ward->ward_code . $bed, 'age' => 60, 'gender' => 'Female', 'phone' => '0',
            'ward_id' => $ward->id, 'bed_number' => $bed, 'status' => 'admitted', 'admitted_at' => now()->subDays(3), 'is_active' => true,
        ]);
    }

    private function nurse(string $name, string $designation = 'STAFF NURSE I'): Nurse
    {
        return Nurse::create([
            'name' => $name, 'registration_number' => 'LJM-' . crc32($name), 'designation' => $designation,
            'ward_id' => $this->ward->id, 'app_username' => strtolower(str_replace(' ', '', $name)), 'app_password' => 'secret123', 'is_active' => true,
        ]);
    }

    private function vitals(Patient $patient, array $values, Carbon $at): VitalSign
    {
        return VitalSign::create(['patient_id' => $patient->id, 'recorded_at' => $at] + $values);
    }

    private function score(Patient $patient, string $code, int $score, Carbon $at): void
    {
        $indicator = ClinicalIndicator::firstOrCreate(['code' => $code], ['name' => $code, 'is_active' => true]);
        ClinicalIndicatorScore::create([
            'patient_id' => $patient->id, 'clinical_indicator_id' => $indicator->id, 'ward_id' => $this->ward->id,
            'score' => $score, 'recorded_at' => $at,
        ]);
    }

    /** The ward's acuity rows keyed by bed number, scored with the default early warning score. */
    private function rows(?Ward $ward = null): Collection
    {
        return PatientAcuity::forWard($ward ?? $this->ward, 'ews_ihh')->keyBy(fn (array $row) => $row['bed']->bed_number);
    }

    private function labels(array $row): array
    {
        return array_column($row['reasons'], 'label');
    }

    /** What makes a bed heavier on the Bed assignment tab's workload score. */
    private function workloadFactors(string $bed): array
    {
        return array_column(WorkloadCalculator::forWard($this->ward)[$this->beds[$bed]->id]['factors'], 'label');
    }

    /** Patients at given levels, for the staffing sums. */
    private function census(array $levels): Collection
    {
        return collect($levels)->map(fn (int $level) => ['patient' => new Patient(), 'level' => $level]);
    }

    private function page(User $user, array $query = [])
    {
        return $this->actingAs($user)->get(route('ward.ai-schedule', ['ward_id' => $this->ward->id, 'week' => '2026-10-05'] + $query));
    }

    // Suggestions

    public function test_a_patient_with_no_indicators_is_suggested_level_1_and_an_empty_bed_has_a_row(): void
    {
        $this->patient('B1');
        $rows = $this->rows();

        $this->assertSame(['B1', 'B2', 'B3', 'B4'], $rows->keys()->all());
        $this->assertSame([1, null, 1, 'unclassified', []], [$rows['B1']['suggested'], $rows['B1']['classified'], $rows['B1']['level'], $rows['B1']['status'], $rows['B1']['reasons']]);
        $this->assertSame([null, null, null, 'empty'], [$rows['B2']['patient'], $rows['B2']['suggested'], $rows['B2']['level'], $rows['B2']['status']]);
    }

    public function test_the_early_warning_score_from_the_latest_vital_signs_suggests_a_level(): void
    {
        $high = $this->patient('B1');
        $medium = $this->patient('B2');
        $low = $this->patient('B3');
        $stale = $this->patient('B4');

        // IHH: pulse > 120, RR > 25, SBP <= 90, SpO2 <= 91 score 2 each; SpO2 92-95, pulse 100-120, RR 21-24 score 1
        $this->vitals($high, ['pulse_rate' => 130, 'respiratory_rate' => 30, 'systolic_bp' => 85, 'spo2' => 90, 'temperature' => 37.0], now()->subHour());
        $this->vitals($medium, ['pulse_rate' => 130, 'respiratory_rate' => 30, 'spo2' => 94], now()->subHour());
        // An older, worse reading does not count: only the latest
        $this->vitals($low, ['pulse_rate' => 130, 'respiratory_rate' => 30, 'systolic_bp' => 85], now()->subHours(5));
        $this->vitals($low, ['pulse_rate' => 110, 'respiratory_rate' => 22, 'spo2' => 94], now()->subHour());
        // Over 24 h old: no score
        $this->vitals($stale, ['pulse_rate' => 130, 'respiratory_rate' => 30, 'systolic_bp' => 85, 'spo2' => 90], now()->subHours(25));

        $rows = $this->rows();
        $this->assertSame([4, ['EWS 8']], [$rows['B1']['suggested'], $this->labels($rows['B1'])]);
        $this->assertSame([3, ['EWS 5']], [$rows['B2']['suggested'], $this->labels($rows['B2'])]);
        $this->assertSame([2, ['EWS 3']], [$rows['B3']['suggested'], $this->labels($rows['B3'])]);
        $this->assertSame([1, []], [$rows['B4']['suggested'], $this->labels($rows['B4'])]);
    }

    public function test_a_newer_partial_reading_can_raise_the_early_warning_score_but_never_hide_a_full_set(): void
    {
        $worse = $this->patient('B1');
        $better = $this->patient('B2');

        // IHH: this morning's full set scores 1 (pulse 105); the monitor's spot reading since,
        // with no RR or temperature, scores 6 (pulse 125, SBP 85, SpO2 90), as the bed box shows
        $this->vitals($worse, ['reading_type' => 'full', 'pulse_rate' => 105, 'respiratory_rate' => 18, 'systolic_bp' => 120, 'diastolic_bp' => 80, 'spo2' => 98, 'temperature' => 37.0], now()->subHours(6));
        $this->vitals($worse, ['pulse_rate' => 125, 'systolic_bp' => 85, 'spo2' => 90], now()->subHour());

        // A full set scoring 5 (pulse 125, RR 22, SBP 95, SpO2 94), then a saturation alone, which scores 0
        $this->vitals($better, ['reading_type' => 'full', 'pulse_rate' => 125, 'respiratory_rate' => 22, 'systolic_bp' => 95, 'diastolic_bp' => 60, 'spo2' => 94, 'temperature' => 37.0], now()->subHours(6));
        $this->vitals($better, ['spo2' => 98], now()->subHour());

        $rows = $this->rows();
        $this->assertSame([3, ['EWS 6']], [$rows['B1']['suggested'], $this->labels($rows['B1'])]);
        $this->assertSame([3, ['EWS 5']], [$rows['B2']['suggested'], $this->labels($rows['B2'])]);
    }

    public function test_the_oxygen_on_now_is_the_newer_of_oxygen_therapy_and_the_vital_signs(): void
    {
        $onCannula = $this->patient('B1');
        $onHighFlow = $this->patient('B2');
        $onRoomAir = $this->patient('B3');

        // Weaned off the ventilator: the later vital signs record a nasal cannula
        OxygenTherapyChange::create(['patient_id' => $onCannula->id, 'ward_id' => $this->ward->id, 'oxygen_delivery' => 'ventilator', 'started_at' => now()->subHours(2)]);
        $this->vitals($onCannula, ['oxygen_delivery' => 'nasal_cannula'], now()->subHour());

        // Stepped up after the last vital signs
        $this->vitals($onHighFlow, ['oxygen_delivery' => 'nasal_cannula'], now()->subHours(3));
        OxygenTherapyChange::create(['patient_id' => $onHighFlow->id, 'ward_id' => $this->ward->id, 'oxygen_delivery' => 'hfnc', 'started_at' => now()->subHour()]);

        // Off oxygen; a voided change is ignored
        OxygenTherapyChange::create(['patient_id' => $onRoomAir->id, 'ward_id' => $this->ward->id, 'oxygen_delivery' => 'simple_mask', 'started_at' => now()->subHours(3)]);
        $this->vitals($onRoomAir, ['oxygen_delivery' => 'room_air'], now()->subHour());
        OxygenTherapyChange::create(['patient_id' => $onRoomAir->id, 'ward_id' => $this->ward->id, 'oxygen_delivery' => 'ventilator', 'started_at' => now()->subMinutes(30), 'voided_at' => now()->subMinutes(20)]);

        $rows = $this->rows();
        $this->assertSame([2, ['Nasal Cannula / Prongs']], [$rows['B1']['suggested'], $this->labels($rows['B1'])]);
        $this->assertSame([3, ['High Flow Nasal Cannula (HFNC)']], [$rows['B2']['suggested'], $this->labels($rows['B2'])]);
        $this->assertSame([1, []], [$rows['B3']['suggested'], $this->labels($rows['B3'])]);
    }

    public function test_the_oxygen_on_now_counts_this_admission_with_no_other_time_limit(): void
    {
        // Admitted six days ago: room air set on day 1, high flow recorded with the vital signs
        // on day 2, then four days of vital signs without the oxygen field (as from a monitor)
        $onHighFlow = $this->patient('B1', ['admitted_at' => now()->subDays(6)]);
        OxygenTherapyChange::create(['patient_id' => $onHighFlow->id, 'ward_id' => $this->ward->id, 'oxygen_delivery' => 'room_air', 'started_at' => now()->subDays(6)->addHour()]);
        $this->vitals($onHighFlow, ['oxygen_delivery' => 'hfnc'], now()->subDays(5));
        foreach ([4, 3, 2, 1] as $days) {
            $this->vitals($onHighFlow, ['pulse_rate' => 80], now()->subDays($days));
        }

        // The other way round: a nasal cannula set, then room air on the vital signs four days ago
        $offOxygen = $this->patient('B2', ['admitted_at' => now()->subDays(6)]);
        OxygenTherapyChange::create(['patient_id' => $offOxygen->id, 'ward_id' => $this->ward->id, 'oxygen_delivery' => 'nasal_cannula', 'started_at' => now()->subDays(5)]);
        $this->vitals($offOxygen, ['oxygen_delivery' => 'room_air'], now()->subDays(4));

        // Oxygen from before this admission does not count, from either source
        $readmitted = $this->patient('B3', ['admitted_at' => now()->subDays(2)]);
        $this->vitals($readmitted, ['oxygen_delivery' => 'ventilator'], now()->subDays(3));
        OxygenTherapyChange::create(['patient_id' => $readmitted->id, 'ward_id' => $this->ward->id, 'oxygen_delivery' => 'bipap', 'started_at' => now()->subDays(2)->subHour()]);

        $rows = $this->rows();
        $this->assertSame([3, ['High Flow Nasal Cannula (HFNC)']], [$rows['B1']['suggested'], $this->labels($rows['B1'])]);
        $this->assertSame([1, []], [$rows['B2']['suggested'], $this->labels($rows['B2'])]);
        $this->assertSame([1, []], [$rows['B3']['suggested'], $this->labels($rows['B3'])]);
    }

    public function test_consciousness_mental_state_and_skin_scores_suggest_levels(): void
    {
        $unconscious = $this->patient('B1');
        $atRisk = $this->patient('B2');
        $pressure = $this->patient('B3');

        $this->score($unconscious, 'GCS', 7, now()->subHours(2));
        $this->score($atRisk, 'CSSRS', 2, now()->subHours(10));
        $this->score($atRisk, 'BRADEN', 13, now()->subHours(10));
        $this->score($pressure, 'BRADEN', 11, now()->subHours(30));
        // A GCS more than 24 h old no longer counts; a Braden counts for 72 h
        $this->score($pressure, 'GCS', 12, now()->subHours(30));

        $rows = $this->rows();
        $this->assertSame([4, ['GCS 7']], [$rows['B1']['suggested'], $this->labels($rows['B1'])]);
        $this->assertSame([3, ['C-SSRS moderate risk', 'Braden 13']], [$rows['B2']['suggested'], $this->labels($rows['B2'])]);
        $this->assertSame([3, ['Braden 11']], [$rows['B3']['suggested'], $this->labels($rows['B3'])]);
        $this->assertSame(['neuro', 4], [$rows['B1']['reasons'][0]['dimension'], $rows['B1']['reasons'][0]['level']]);
    }

    public function test_a_running_infusion_is_read_from_the_infusion_records_without_the_engine(): void
    {
        $onPump = $this->patient('B1');
        $finished = $this->patient('B2');
        Infusion::create(['patient_id' => $onPump->id, 'medication_name' => 'Normal saline', 'status' => Infusion::STATUS_RUNNING]);
        Infusion::create(['patient_id' => $finished->id, 'medication_name' => 'Normal saline', 'status' => Infusion::STATUS_COMPLETED]);
        Http::fake();

        $rows = $this->rows();
        $this->assertSame([2, ['Infusion running']], [$rows['B1']['suggested'], $this->labels($rows['B1'])]);
        $this->assertSame([1, []], [$rows['B2']['suggested'], $this->labels($rows['B2'])]);
        $this->assertContains('Infusion running', $this->workloadFactors('B1'));
        $this->assertNotContains('Infusion running', $this->workloadFactors('B2'));
        Http::assertNothingSent();
    }

    public function test_with_the_infusion_engine_in_use_a_running_infusion_is_read_from_the_engine(): void
    {
        IntegrationSetting::put(InfusionEngineClient::MODE_KEY, InfusionEngineClient::MODE_ENGINE);
        $onPump = $this->patient('B1');
        $idlePump = $this->patient('B2');
        // Engine mode keeps only snapshots locally: a local row is not what is running now
        $snapshotOnly = $this->patient('B3');
        Infusion::create(['patient_id' => $snapshotOnly->id, 'medication_name' => 'Normal saline', 'status' => Infusion::STATUS_RUNNING]);

        // The pumps bound to patients on the ward dashboard, and what the engine says they are doing
        InfusionPump::create(['device_id' => 'PUMP-1', 'patient_id' => $onPump->id, 'is_active' => true]);
        InfusionPump::create(['device_id' => 'PUMP-2', 'patient_id' => $idlePump->id, 'is_active' => true]);
        Http::fake(['*/api/pumps' => Http::response(['pumps' => [
            ['device_id' => 'PUMP-1', 'pump_label' => 'I1001', 'station_id' => 'ST-1', 'pump_status' => 'infusing', 'drug_name' => 'Heparin'],
            ['device_id' => 'PUMP-2', 'pump_label' => 'I1002', 'station_id' => 'ST-1', 'pump_status' => 'standby'],
        ]])]);

        $rows = $this->rows();
        $this->assertSame([2, ['Infusion running']], [$rows['B1']['suggested'], $this->labels($rows['B1'])]);
        $this->assertSame([1, []], [$rows['B2']['suggested'], $this->labels($rows['B2'])]);
        $this->assertSame([1, []], [$rows['B3']['suggested'], $this->labels($rows['B3'])]);

        // The workload score reads the same, and the engine's answer is kept for a minute
        $this->assertContains('Infusion running', $this->workloadFactors('B1'));
        $this->assertNotContains('Infusion running', $this->workloadFactors('B3'));
        Http::assertSentCount(1);
    }

    public function test_when_the_infusion_engine_cannot_be_reached_the_infusion_records_are_used(): void
    {
        IntegrationSetting::put(InfusionEngineClient::MODE_KEY, InfusionEngineClient::MODE_ENGINE);
        $onPump = $this->patient('B1');
        Infusion::create(['patient_id' => $onPump->id, 'medication_name' => 'Normal saline', 'status' => Infusion::STATUS_ALARMING]);
        Http::fake(['*' => Http::response('Service unavailable', 503)]);
        Log::spy();

        $rows = $this->rows();
        $this->assertSame([2, ['Infusion running']], [$rows['B1']['suggested'], $this->labels($rows['B1'])]);
        $this->assertContains('Infusion running', $this->workloadFactors('B1'));

        // The failure is kept for a minute too, so a dead engine is tried once a minute, not on every page
        Http::assertSentCount(1);
        Log::shouldHaveReceived('warning')->once();
        $this->travel(61)->seconds();
        $this->rows();
        Http::assertSentCount(2);
    }

    public function test_three_areas_of_care_at_the_same_level_raise_the_suggestion_by_one(): void
    {
        // Isolation, a high fall risk and a new admission: three areas at Level 2
        $this->patient('B1', ['isolation_type' => 'contact', 'fall_risk' => 'high', 'admitted_at' => now()->subHours(2)]);
        // Two areas only
        $this->patient('B2', ['isolation_type' => 'contact', 'fall_risk' => '1']);

        $rows = $this->rows();
        $this->assertSame(3, $rows['B1']['suggested']);
        $this->assertSame(['3 areas of care at once', 'High fall risk', 'Isolation', 'New admission'], [$this->labels($rows['B1'])[0], ...collect($this->labels($rows['B1']))->slice(1)->sort()->values()->all()]);
        $this->assertSame([2, ['High fall risk', 'Isolation']], [$rows['B2']['suggested'], $this->labels($rows['B2'])]);
    }

    public function test_every_patient_on_a_critical_care_ward_is_at_least_level_3(): void
    {
        $type = WardType::create(['code' => 'ICU', 'name' => 'ICU', 'is_critical_care' => true, 'is_active' => true]);
        $this->ward->update(['ward_type_id' => $type->id]);
        $this->patient('B1');
        $this->patient('B2', ['nursing_level' => 'level_4']);

        $rows = $this->rows($this->ward->fresh());
        $this->assertSame([3, ['Critical care ward']], [$rows['B1']['suggested'], $this->labels($rows['B1'])]);
        $this->assertSame([3, 4, 'review_down'], [$rows['B2']['suggested'], $rows['B2']['level'], $rows['B2']['status']]);
    }

    public function test_the_rating_is_checked_against_the_suggestion(): void
    {
        $this->patient('B1', ['nursing_level' => 'level_2']);
        $under = $this->patient('B2', ['nursing_level' => 'level_1']);
        $this->vitals($under, ['pulse_rate' => 130, 'respiratory_rate' => 30, 'spo2' => 94], now()->subHour());
        $this->patient('B3', ['nursing_level' => 'level_1']);
        $this->patient('B4', ['nursing_level' => 'none', 'status' => Patient::STATUS_PENDING_DISCHARGE]);

        $rows = $this->rows();
        $this->assertSame(
            ['B1' => 'review_down', 'B2' => 'review_up', 'B3' => 'agrees', 'B4' => 'unclassified'],
            $rows->map(fn (array $row) => $row['status'])->all()
        );
        // The nurse's rating counts for staffing; unclassified counts as suggested
        $this->assertSame(['B1' => 2, 'B2' => 1, 'B3' => 1, 'B4' => 1], $rows->map(fn (array $row) => $row['level'])->all());
        $this->assertSame(['Discharge planned'], $rows['B4']['notes']);

        $summary = PatientAcuity::summary($rows);
        $this->assertSame([4, 4, [1 => 3, 2 => 1, 3 => 0, 4 => 0], 1, 1, 1, 1, 0, 0], [
            $summary['patients'], $summary['beds'], $summary['by_level'], $summary['unclassified'],
            $summary['review_up'], $summary['review_down'], $summary['agrees'], $summary['rated_recently'], $summary['high'],
        ]);
    }

    // Staffing

    public function test_nurses_per_shift_are_care_hours_times_the_share_over_the_shift_length(): void
    {
        $shifts = WardShifts::forWard($this->ward->id);
        $this->assertSame([7.0, 9.0, 8.0], [$shifts['AM']['hours'], $shifts['PM']['hours'], $shifts['ON']['hours']]);

        // 4 x 5 h + 2 x 7.5 h = 35 h a day: AM 40% = 14 h / 7 h = 2 nurses; PM 35% = 12.25 h / 9 h = 1.36;
        // ON 25% = 8.75 h / 8 h = 1.09
        $staffing = AcuityStaffing::forShifts($this->census([2, 2, 2, 2, 3, 3]), $shifts, AcuitySettings::defaults());
        $this->assertSame([6, [1 => 0, 2 => 4, 3 => 2, 4 => 0], 35.0, 5.8], [$staffing['patients'], $staffing['by_level'], $staffing['care_hours'], $staffing['hours_per_patient']]);
        $this->assertSame(['care_hours' => 14.0, 'shift_hours' => 7.0, 'exact' => 2.0, 'recommended' => 2, 'patients_per_nurse' => 3.0], $staffing['shifts']['AM']);
        $this->assertSame([1.36, 2, 3.0], [$staffing['shifts']['PM']['exact'], $staffing['shifts']['PM']['recommended'], $staffing['shifts']['PM']['patients_per_nurse']]);
        $this->assertSame([1.09, 2], [$staffing['shifts']['ON']['exact'], $staffing['shifts']['ON']['recommended']]);
    }

    public function test_a_sliver_of_a_nurse_is_not_rounded_up_and_no_patients_need_no_nurses(): void
    {
        $shifts = WardShifts::forWard($this->ward->id);
        $ten = array_fill(0, 10, 1);

        // 10 x 3.57 h = 35.7 h; AM: 14.28 h / 7 h = 2.04 nurses, within the rounding slack of 2
        $sliver = AcuityStaffing::forShifts($this->census($ten), $shifts, AcuitySettings::fromRules(['acuity' => ['hours_level_1' => 3.57]]));
        $this->assertSame([2.04, 2], [$sliver['shifts']['AM']['exact'], $sliver['shifts']['AM']['recommended']]);

        // 10 x 3.605 h: 2.06 nurses is more than a sliver
        $over = AcuityStaffing::forShifts($this->census($ten), $shifts, AcuitySettings::fromRules(['acuity' => ['hours_level_1' => 3.605]]));
        $this->assertSame([2.06, 3], [$over['shifts']['AM']['exact'], $over['shifts']['AM']['recommended']]);

        // One patient still needs a nurse on every shift
        $one = AcuityStaffing::forShifts($this->census([1]), $shifts, AcuitySettings::defaults());
        $this->assertSame([1, 1, 1], array_column($one['shifts'], 'recommended'));

        $none = AcuityStaffing::forShifts(collect([['patient' => null, 'level' => null]]), $shifts, AcuitySettings::defaults());
        $this->assertSame([0, 0.0, 0.0], [$none['patients'], $none['care_hours'], $none['hours_per_patient']]);
        foreach (WardShifts::CODES as $code) {
            $this->assertSame([0, null, 0.0], [$none['shifts'][$code]['recommended'], $none['shifts'][$code]['patients_per_nurse'], $none['shifts'][$code]['exact']]);
        }
    }

    public function test_only_registered_nurses_count_toward_the_recommendation(): void
    {
        foreach (['Student nurse', 'Health Care Assistant', 'patient care assistant', ' STUDENT NURSE '] as $designation) {
            $this->assertFalse(AcuityStaffing::isRegisteredNurse(new Nurse(['designation' => $designation])), $designation);
        }
        foreach (['STAFF NURSE I', 'GRADUATE NURSE', 'Nurse Manager', ''] as $designation) {
            $this->assertTrue(AcuityStaffing::isRegisteredNurse(new Nurse(['designation' => $designation])), $designation);
        }

        // Four Level 2 patients: 20 h a day; AM 8 h / 7 h = 1.14 nurses, so 2; PM and ON 1
        foreach (['B1', 'B2', 'B3', 'B4'] as $bed) {
            $this->patient($bed, ['nursing_level' => 'level_2']);
        }
        $roster = fn (Nurse $nurse, string $shift) => NurseRosterEntry::create(['ward_id' => $this->ward->id, 'nurse_id' => $nurse->id, 'roster_date' => '2026-10-05', 'shift' => $shift, 'source' => NurseRosterEntry::SOURCE_MANUAL]);
        $roster($this->nurse('Aisyah Rahman'), 'AM');
        $roster($this->nurse('Siti Student', 'Student nurse'), 'AM');
        $roster($this->nurse('Hana Assistant', 'Health Care Assistant'), 'AM');
        $roster($this->nurse('Mei Ling'), 'PM');

        $week = $this->page($this->manager, ['tab' => 'acuity'])->assertOk()->viewData('acuityWeek');
        $this->assertSame(['rostered' => 3, 'registered' => 1, 'min' => 1, 'recommended' => 2, 'gap' => -1, 'status' => 'short'], $week['2026-10-05']['AM']);
        $this->assertSame([1, 1, 'met'], [$week['2026-10-05']['PM']['registered'], $week['2026-10-05']['PM']['recommended'], $week['2026-10-05']['PM']['status']]);
        // Every other shift of the week has nobody yet
        $this->assertSame(20, AcuityStaffing::shortShifts($week));
    }

    // The tab

    public function test_the_page_and_the_tab_render_for_a_manager(): void
    {
        // Rated above the indicators, rated below them, not classified; B4 is empty
        $this->patient('B1', ['nursing_level' => 'level_3', 'isolation_type' => 'contact']);
        $this->patient('B2', ['nursing_level' => 'level_1', 'admitted_at' => now()->subHours(2)]);
        $this->patient('B3');

        $this->page($this->manager)->assertOk()->assertViewHas('tab', 'roster');

        $response = $this->page($this->manager, ['tab' => 'acuity'])
            ->assertOk()
            ->assertSee('Method: patient classification and acuity-based staffing (ANA, 2017).')
            ->assertSee('Staffing by patient acuity')
            ->assertSee('Use as minimum staff')
            ->assertSee('Save settings')
            ->assertSee('Patient classification')
            ->assertSee('Patient W6B1')
            ->assertSee('MRN-W6B1')
            ->assertSee('Review: indicators suggest Level 2', false)
            ->assertSee('Rated above indicators')
            ->assertSee('Not classified')
            ->assertSee('Empty')
            ->assertSee('Fill suggested where not classified')
            ->assertSee('Confirm current ratings')
            ->assertSee('Save ratings')
            ->assertSee('No ratings in the last 30 days yet.')
            ->assertSee('How the levels are suggested and staffed')
            ->assertSee('id="acuity-references"', false)
            ->assertSee('American Nurses Association. (2017).');

        $this->assertSame(3, $response->viewData('acuitySummary')['patients']);
        $this->assertSame('acuity', $response->viewData('tab'));
        $this->assertSame(0, $response->viewData('acuityCheck')['ratings']);
        // 7.5 h + 3 h + 3 h = 13.5 h a day: every shift needs one nurse
        $this->assertSame([1, 1, 1], array_column($response->viewData('acuityStaffing')['shifts'], 'recommended'));
    }

    public function test_a_nurse_sees_the_tab_and_can_rate_but_not_change_the_settings_or_minimum(): void
    {
        $this->patient('B1');

        $this->page($this->nurseUser)->assertOk();
        $this->page($this->nurseUser, ['tab' => 'acuity'])
            ->assertOk()
            ->assertSee('Staffing by patient acuity')
            ->assertSee('Save ratings')
            ->assertSee('Nurse managers and admins can change these.')
            ->assertDontSee('Use as minimum staff')
            ->assertDontSee('Save settings')
            ->assertDontSee('reset-acuity-settings', false);
    }

    // Saving ratings

    public function test_a_nurse_saves_ratings_that_set_the_nursing_level_and_keep_the_indicators(): void
    {
        $changed = $this->patient('B1', ['isolation_type' => 'contact']);
        $confirmed = $this->patient('B2', ['nursing_level' => 'level_2']);
        $untouched = $this->patient('B3', ['nursing_level' => 'level_1']);
        $otherWard = Ward::create(['hospital_id' => $this->ward->hospital_id, 'ward_code' => 'W7', 'ward_name' => 'Ward 7', 'is_active' => true]);
        Bed::create(['ward_id' => $otherWard->id, 'bed_number' => 'B1', 'bed_id' => 'W7-B1', 'bed_display_name' => 'B1', 'status' => 'occupied', 'is_active' => true]);
        $elsewhere = $this->patient('B1', ['nursing_level' => 'level_1'], $otherWard);

        $this->actingAs($this->nurseUser)
            ->post(route('ward.ai-schedule.acuity'), [
                'ward_id' => $this->ward->id,
                'week' => '2026-10-05',
                'levels' => [$changed->id => '3', $confirmed->id => '2', $untouched->id => '', $elsewhere->id => '4'],
                'notes' => [$changed->id => '  Turns 2-hourly, family at the bedside  ', $confirmed->id => '   '],
            ])
            ->assertRedirect(route('ward.ai-schedule', ['ward_id' => $this->ward->id, 'week' => '2026-10-05', 'tab' => 'acuity']))
            ->assertSessionHas('success', 'Saved 2 ratings: 1 changed, 1 confirmed. Skipped 1 no longer in a bed on Ward 6.');

        $this->assertSame(['level_3', 'level_2', 'level_1', 'level_1'], [
            $changed->fresh()->nursing_level, $confirmed->fresh()->nursing_level, $untouched->fresh()->nursing_level, $elsewhere->fresh()->nursing_level,
        ]);

        $rating = PatientAcuityRating::where('patient_id', $changed->id)->sole();
        $this->assertSame([3, 2, null, 'B1', 'Turns 2-hourly, family at the bedside', '2026-10-05', 'AM', $this->nurseUser->id, 'Nurse Nadia', $this->ward->id], [
            $rating->level, $rating->suggested_level, $rating->previous_level, $rating->bed_number, $rating->note,
            $rating->shift_date->toDateString(), $rating->shift, $rating->rated_by, $rating->rated_by_name, $rating->ward_id,
        ]);
        // JSON columns keep their own key order
        $this->assertEquals([['dimension' => 'infection', 'label' => 'Isolation', 'level' => 2]], $rating->reasons);
        $this->assertSame('above', $rating->agreement());

        $kept = PatientAcuityRating::where('patient_id', $confirmed->id)->sole();
        $this->assertSame([2, 2, 1, null, 'above'], [$kept->level, $kept->previous_level, $kept->suggested_level, $kept->note, $kept->agreement()]);
        $this->assertSame(2, PatientAcuityRating::count());

        // The tab shows who rated, and the rating check counts them
        $response = $this->page($this->manager, ['tab' => 'acuity'])
            ->assertSee('ago by Nurse Nadia')
            ->assertDontSee('No ratings in the last 30 days yet.');
        $this->assertSame([2, 2, 2], [
            $response->viewData('acuitySummary')['rated_recently'], $response->viewData('acuityCheck')['ratings'], $response->viewData('acuityCheck')['above'] + $response->viewData('acuityCheck')['below'],
        ]);
    }

    public function test_saving_with_nothing_chosen_or_an_unknown_level_saves_nothing(): void
    {
        $patient = $this->patient('B1');
        $post = fn (array $levels) => $this->actingAs($this->nurseUser)
            ->post(route('ward.ai-schedule.acuity'), ['ward_id' => $this->ward->id, 'week' => '2026-10-05', 'levels' => $levels]);

        $post([$patient->id => ''])->assertSessionHas('error', 'Choose a new rating for at least one patient, then save.');
        $post([$patient->id => '5'])->assertSessionHasErrors('levels.' . $patient->id);

        $this->assertSame(0, PatientAcuityRating::count());
        $this->assertSame('none', $patient->fresh()->nursing_level);
    }

    // Settings and minimum staff

    public function test_staffing_settings_must_share_out_the_whole_day_and_can_be_reset(): void
    {
        WardRosterSetting::create(['ward_id' => $this->ward->id, 'rules' => ['min_staff' => ['AM' => 4, 'PM' => 3, 'ON' => 2]]]);
        $settings = fn (array $values) => ['ward_id' => $this->ward->id, 'week' => '2026-10-05', 'acuity' => $values + [
            'hours_level_1' => 2.5, 'hours_level_2' => 5, 'hours_level_3' => 8, 'hours_level_4' => 12, 'share_AM' => 45, 'share_PM' => 35, 'share_ON' => 20,
        ]];
        $back = route('ward.ai-schedule', ['ward_id' => $this->ward->id, 'week' => '2026-10-05', 'tab' => 'acuity', 'acuity_settings' => 1]);
        $stored = fn () => WardRosterSetting::where('ward_id', $this->ward->id)->value('rules');

        $this->actingAs($this->manager)->post(route('ward.ai-schedule.acuity.settings'), $settings(['share_AM' => 55]))
            ->assertRedirect($back)
            ->assertSessionHas('error', 'The shares of the day\'s care on each shift must add up to 100%. They add up to 110%.');
        $this->assertArrayNotHasKey('acuity', $stored());

        $this->actingAs($this->manager)->post(route('ward.ai-schedule.acuity.settings'), $settings(['hours_level_4' => 30]))
            ->assertSessionHasErrors('acuity.hours_level_4');

        $this->actingAs($this->manager)->post(route('ward.ai-schedule.acuity.settings'), $settings([]))
            ->assertRedirect($back)
            ->assertSessionHas('success');
        $ward = AcuitySettings::forWard($this->ward);
        $this->assertSame([2.5, 8.0, 45.0, 20.0, 4], [$ward->hours(1), $ward->hours(3), $ward->share('AM'), $ward->share('ON'), $ward->changedCount()]);
        $this->assertEquals(['AM' => 4, 'PM' => 3, 'ON' => 2], $stored()['min_staff'], 'the other rules are kept');

        // The panel opens again after saving
        $this->actingAs($this->manager)->get($back)->assertOk()->assertSee('4 changed from the defaults');

        $this->actingAs($this->manager)->post(route('ward.ai-schedule.acuity.settings.reset'), ['ward_id' => $this->ward->id, 'week' => '2026-10-05'])
            ->assertRedirect($back)
            ->assertSessionHas('success', 'Acuity staffing settings reset to the defaults.');
        $this->assertArrayNotHasKey('acuity', $stored());
        $this->assertEquals(['AM' => 4, 'PM' => 3, 'ON' => 2], $stored()['min_staff']);
        $this->assertSame(0, AcuitySettings::forWard($this->ward)->changedCount());
    }

    public function test_nurses_cannot_change_the_staffing_settings(): void
    {
        $this->actingAs($this->nurseUser)
            ->post(route('ward.ai-schedule.acuity.settings'), ['ward_id' => $this->ward->id, 'week' => '2026-10-05', 'acuity' => AcuitySettings::DEFAULTS])
            ->assertForbidden();
        $this->actingAs($this->nurseUser)
            ->post(route('ward.ai-schedule.acuity.settings.reset'), ['ward_id' => $this->ward->id, 'week' => '2026-10-05'])
            ->assertForbidden();
        $this->assertNull(WardRosterSetting::where('ward_id', $this->ward->id)->value('rules'));
    }

    public function test_the_acuity_recommendation_can_become_the_minimum_staff(): void
    {
        $post = fn (User $user) => $this->actingAs($user)
            ->post(route('ward.ai-schedule.acuity.min-staff'), ['ward_id' => $this->ward->id, 'week' => '2026-10-05']);

        $post($this->manager)->assertSessionHas('error', 'There are no patients on Ward 6 now, so there is no acuity to set the minimum staff from.');

        // 2 x Level 4 + 2 x Level 2 = 34 h: AM 13.6 h / 7 h = 1.94, so 2; PM 11.9 h / 9 h = 1.32, so 2; ON 8.5 h / 8 h = 1.06, so 2
        $this->patient('B1', ['nursing_level' => 'level_4']);
        $this->patient('B2', ['nursing_level' => 'level_4']);
        $this->patient('B3', ['nursing_level' => 'level_2']);
        $this->patient('B4', ['nursing_level' => 'level_2']);
        WardRosterSetting::create(['ward_id' => $this->ward->id, 'rules' => ['target_shifts_per_week' => 4, 'min_staff' => ['AM' => 1, 'PM' => 1, 'ON' => 1]]]);

        $post($this->nurseUser)->assertForbidden();
        $this->assertSame(['AM' => 1, 'PM' => 1, 'ON' => 1], RosterRules::forWard($this->ward)->minStaff);

        $post($this->manager)
            ->assertRedirect(route('ward.ai-schedule', ['ward_id' => $this->ward->id, 'week' => '2026-10-05', 'tab' => 'acuity']))
            // Honest about what the minimum is: anyone on the shift, while the recommendation is registered nurses
            ->assertSessionHas('success', 'Minimum staff set from patient acuity: AM 2, PM 2, ON 2. The minimum counts everyone on a shift,'
                . ' students and care assistants too, while the recommendation is registered nurses: generate the roster again,'
                . ' then check the registered nurses against it on this tab.');

        $rules = RosterRules::forWard($this->ward);
        $this->assertSame([['AM' => 2, 'PM' => 2, 'ON' => 2], 4], [$rules->minStaff, $rules->targetShiftsPerWeek]);

        // The button's confirmation says the same before anything is saved
        $this->page($this->manager, ['tab' => 'acuity'])
            ->assertSee('The minimum counts everyone on a shift, students and care assistants too, while the recommendation is registered nurses.');
    }

    public function test_the_ward_sets_the_early_warning_score_system_for_everyone(): void
    {
        // IHH scores pulse 115, RR 22 and SpO2 93 one each: EWS 3, Level 2. NEWS2 scores them two each: EWS 6, Level 3
        $patient = $this->patient('B1');
        $this->vitals($patient, ['pulse_rate' => 115, 'respiratory_rate' => 22, 'spo2' => 93, 'systolic_bp' => 120, 'temperature' => 37.0], now()->subHour());
        // The manager's own ward dashboard scores NEWS2; the acuity tab does not follow a user's setting
        WardDashboardSetting::create(['user_id' => $this->manager->id, 'clinical_settings' => ['ews_system' => 'news2']]);
        $suggested = fn (User $user) => $this->page($user, ['tab' => 'acuity'])->assertOk()->viewData('acuity')->first()['suggested'];

        $this->assertSame([2, 2], [$suggested($this->manager), $suggested($this->nurseUser)]);

        $settings = fn (array $acuity = []) => ['ward_id' => $this->ward->id, 'week' => '2026-10-05', 'acuity' => $acuity + AcuitySettings::DEFAULTS];
        $this->actingAs($this->manager)->post(route('ward.ai-schedule.acuity.settings'), $settings(['ews_system' => 'custom']))
            ->assertSessionHasErrors('acuity.ews_system');
        $this->actingAs($this->manager)->post(route('ward.ai-schedule.acuity.settings'), $settings(['ews_system' => 'news2']))
            ->assertSessionHas('success');
        $this->assertSame(['news2', 1], [AcuitySettings::forWard($this->ward)->ewsSystem(), AcuitySettings::forWard($this->ward)->changedCount()]);
        // Saving the numbers without the system keeps the ward's system
        $this->actingAs($this->manager)->post(route('ward.ai-schedule.acuity.settings'), $settings())->assertSessionHas('success');
        $this->assertSame('news2', AcuitySettings::forWard($this->ward)->ewsSystem());

        // Everyone now sees NEWS2, the nurse with no dashboard setting included
        $this->assertSame([3, 3], [$suggested($this->manager), $suggested($this->nurseUser)]);
        $this->page($this->nurseUser, ['tab' => 'acuity'])
            ->assertSee('Early warning scores are worked out with NEWS2 (National Early Warning Score 2), the ward\'s setting.', false)
            ->assertSee('<option value="news2" selected>', false);

        // A rating is checked against the ward's scale, and so is a printout or a shared link
        $this->actingAs($this->nurseUser)->post(route('ward.ai-schedule.acuity'), ['ward_id' => $this->ward->id, 'week' => '2026-10-05', 'levels' => [$patient->id => '3']]);
        $rating = PatientAcuityRating::sole();
        $this->assertSame([3, 3, 'agrees'], [$rating->level, $rating->suggested_level, $rating->agreement()]);
        $snapshot = ScheduleSnapshot::build($this->ward, Carbon::parse('2026-10-05'), ['public' => true]);
        $this->assertSame(3, collect($snapshot['acuity']['rows'])->firstWhere('bed', 'B1')['suggested']);

        $this->actingAs($this->manager)->post(route('ward.ai-schedule.acuity.settings.reset'), ['ward_id' => $this->ward->id, 'week' => '2026-10-05'])
            ->assertSessionHas('success');
        $this->assertSame('ews_ihh', AcuitySettings::forWard($this->ward)->ewsSystem());
    }

    // Rating check

    public function test_the_rating_check_measures_agreement_with_the_indicators_by_nurse(): void
    {
        $patient = $this->patient('B1');
        $otherWard = Ward::create(['hospital_id' => $this->ward->hospital_id, 'ward_code' => 'W7', 'ward_name' => 'Ward 7', 'is_active' => true]);
        $rate = fn (string $name, int $level, ?int $suggested, Carbon $at, ?Ward $ward = null) => PatientAcuityRating::create([
            'patient_id' => $patient->id, 'ward_id' => ($ward ?? $this->ward)->id, 'bed_number' => 'B1', 'level' => $level,
            'suggested_level' => $suggested, 'rated_by_name' => $name, 'rated_at' => $at,
        ]);

        $rate('Aisyah Rahman', 2, 2, now()->subDays(2));
        $rate('Aisyah Rahman', 3, 3, now()->subDay());
        $rate('Aisyah Rahman', 3, 2, now()->subHours(3));
        $rate('Mei Ling', 1, 2, now()->subHours(5));
        // No suggestion at the time: counted, but not toward agreement
        $rate('Mei Ling', 2, null, now()->subHours(4));
        // Too long ago, or another ward
        $rate('Mei Ling', 4, 1, now()->subDays(31));
        $rate('Farah Idris', 1, 1, now()->subHour(), $otherWard);

        $check = AcuityRatingCheck::forWard($this->ward);
        $this->assertSame([30, 5, 2, 1, 1, 50], [$check['days'], $check['ratings'], $check['agrees'], $check['above'], $check['below'], $check['agreement']]);
        $this->assertSame([
            ['name' => 'Aisyah Rahman', 'ratings' => 3, 'agrees' => 2, 'above' => 1, 'below' => 0, 'agreement' => 67],
            ['name' => 'Mei Ling', 'ratings' => 2, 'agrees' => 0, 'above' => 0, 'below' => 1, 'agreement' => 0],
        ], $check['by_rater']);
        $this->assertSame(now()->subHours(3)->toDateTimeString(), $check['last_at']->toDateTimeString());

        // Nothing in the window: no agreement to show
        $none = AcuityRatingCheck::forWard($otherWard, 0);
        $this->assertSame([0, null, [], null], [$none['ratings'], $none['agreement'], $none['by_rater'], $none['last_at']]);

        $this->page($this->manager, ['tab' => 'acuity'])
            ->assertOk()
            ->assertSee('Aisyah Rahman')
            ->assertSee('67%')
            ->assertSee('(ANA, 2017, p. 14)', false);
    }
}
