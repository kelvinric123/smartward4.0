<?php

namespace Tests\Feature;

use App\Models\AdmissionLog;
use App\Models\Bed;
use App\Models\Hospital;
use App\Models\Nurse;
use App\Models\NurseRosterEntry;
use App\Models\Patient;
use App\Models\User;
use App\Models\VitalSign;
use App\Models\Ward;
use App\Models\WardNotification;
use App\Models\WardRosterSetting;
use App\Models\WardScheduleAssignment;
use App\Models\WardType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Command Center V2: the executive summary of every ward for management,
 * with its figures refreshed from the data route.
 */
class CommandCenterV2Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Hospital $hospital;
    private Ward $icu;
    private Ward $medical;
    private Ward $surgical;
    private array $patients = [];

    protected function setUp(): void
    {
        parent::setUp();

        // A Saturday afternoon: the PM shift on the default shift times
        $this->travelTo(Carbon::parse('2026-09-26 14:30:00'));

        $this->admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $this->hospital = Hospital::create(['name' => 'Test Hospital']);

        $icuType = WardType::create(['code' => 'ICU', 'name' => 'ICU', 'is_critical_care' => true, 'is_active' => true]);
        $generalType = WardType::create(['code' => 'GEN', 'name' => 'General', 'is_active' => true]);

        $this->icu = $this->ward('ICU1', 'Intensive Care Unit', $icuType, ['ICU01' => 'available', 'ICU02' => 'available']);
        $this->medical = $this->ward('MW1', 'Medical Ward 1', $generalType, [
            'MW01' => 'available', 'MW02' => 'available', 'MW03' => 'available', 'MW04' => 'available', 'MW05' => Bed::STATUS_MAINTENANCE,
        ]);
        $this->surgical = $this->ward('SW1', 'Surgical Ward 1', $generalType, [
            'SW01' => 'available', 'SW02' => 'available', 'SW03' => 'available', 'SW04' => 'reserved',
        ]);

        $this->patients = [
            'deteriorating' => $this->admit($this->icu, 'ICU01', 'Lim Ah Kow', now()->subDays(3), ['nursing_level' => 'level_4', 'fall_risk' => 'high']),
            'stable' => $this->admit($this->icu, 'ICU02', 'Tan Mei Ling', now()->subDays(2), ['nursing_level' => 'level_3']),
            'calling' => $this->admit($this->medical, 'MW01', 'Siti Aminah', now()->setTime(9, 0)),
            'going_home' => $this->admit($this->medical, 'MW02', 'Rajesh Kumar', now()->subDays(10), ['status' => Patient::STATUS_PENDING_DISCHARGE]),
            'just_arrived' => $this->admit($this->medical, 'MW03', 'Wong Kar Wai', now()->subMinutes(20)),
            'isolated' => $this->admit($this->surgical, 'SW01', 'Nur Aisyah', now()->subDays(5), ['isolation_type' => 'contact']),
        ];
        Patient::create($this->patientFields($this->icu, null, 'Ahmad Faiz') + ['status' => Patient::STATUS_PREBOOK, 'admitted_at' => null]);

        // EWS 6 twenty minutes ago; the rest well, but some not observed within the day
        $this->vitals('deteriorating', ['pulse_rate' => 130, 'respiratory_rate' => 28, 'systolic_bp' => 85], now()->subMinutes(20));
        $this->vitals('stable', [], now()->subHour());
        $this->vitals('calling', [], now()->subHours(2));
        $this->vitals('going_home', [], now()->subDays(2));
        $this->vitals('isolated', [], now()->subHours(30));
    }

    private function ward(string $code, string $name, WardType $type, array $beds): Ward
    {
        $ward = Ward::create([
            'hospital_id' => $this->hospital->id, 'ward_code' => $code, 'ward_name' => $name,
            'ward_type_id' => $type->id, 'is_active' => true,
        ]);

        foreach ($beds as $number => $status) {
            Bed::create([
                'ward_id' => $ward->id, 'bed_number' => $number, 'bed_id' => $code . '-' . $number,
                'bed_display_name' => $number, 'status' => $status, 'is_active' => true,
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

    private function admit(Ward $ward, string $bed, string $name, $admittedAt, array $extra = []): Patient
    {
        Bed::where('ward_id', $ward->id)->where('bed_number', $bed)->update(['status' => 'occupied']);

        return Patient::create($extra + $this->patientFields($ward, $bed, $name) + ['status' => Patient::STATUS_ADMITTED, 'admitted_at' => $admittedAt]);
    }

    private function vitals(string $patient, array $values, $at): void
    {
        VitalSign::create($values + [
            'patient_id' => $this->patients[$patient]->id, 'systolic_bp' => 120, 'diastolic_bp' => 75, 'pulse_rate' => 80,
            'temperature' => 36.8, 'spo2' => 98, 'respiratory_rate' => 16, 'reading_type' => 'full', 'recorded_at' => $at,
        ]);
    }

    private function log(string $action, Ward $ward, string $at, ?Patient $patient = null): void
    {
        AdmissionLog::create([
            'patient_id' => $patient?->id ?? $this->patients['calling']->id, 'ward_id' => $ward->id, 'user_id' => $this->admin->id,
            'bed_number' => 'X', 'action' => $action, 'patient_name' => 'X', 'mrn' => 'X', 'source' => 'manual',
            ($action === 'discharge' ? 'discharged_at' : 'admitted_at') => $at,
        ]);
    }

    /** A patient who has gone home, with the stay's admission logged or only on their record. */
    private function discharged(Ward $ward, string $admittedAt, string $dischargedAt, bool $logged = true): void
    {
        $patient = Patient::create($this->patientFields($ward, null, 'Discharged ' . Patient::count()) + [
            'status' => Patient::STATUS_DISCHARGED, 'is_active' => false, 'admitted_at' => $admittedAt, 'discharged_at' => $dischargedAt,
        ]);
        if ($logged) {
            $this->log('admit', $ward, $admittedAt, $patient);
        }
        $this->log('discharge', $ward, $dischargedAt, $patient);
    }

    private function alert(Ward $ward, string $patient, string $severity, $createdAt, $respondedAt = null, ?User $by = null): void
    {
        $alert = WardNotification::create([
            'ward_id' => $ward->id, 'patient_id' => $this->patients[$patient]->id, 'bed_number' => $this->patients[$patient]->bed_number,
            'type' => WardNotification::TYPE_PATIENT_REQUEST, 'severity' => $severity, 'message' => 'Call bell',
            'status' => $respondedAt ? WardNotification::STATUS_RESPONDED : WardNotification::STATUS_PENDING,
            'responded_at' => $respondedAt, 'responded_by' => $by?->id,
        ]);
        $alert->created_at = $createdAt;
        $alert->save();
    }

    private function nurse(string $name): Nurse
    {
        return Nurse::create(['name' => $name, 'registration_number' => 'RN-' . crc32($name), 'is_active' => true]);
    }

    private function roster(Nurse $nurse, Ward $ward, string $date, string $shift): void
    {
        NurseRosterEntry::create(['ward_id' => $ward->id, 'nurse_id' => $nurse->id, 'roster_date' => $date, 'shift' => $shift, 'source' => NurseRosterEntry::SOURCE_MANUAL]);
    }

    private function holdsBed(Nurse $nurse, Ward $ward, string $bed, string $date, string $shift): void
    {
        WardScheduleAssignment::create([
            'ward_id' => $ward->id, 'bed_id' => Bed::where('ward_id', $ward->id)->where('bed_number', $bed)->value('id'),
            'nurse_id' => $nurse->id, 'scheduled_date' => $date, 'shift' => $shift,
        ]);
    }

    private function summary(): array
    {
        return $this->actingAs($this->admin)->getJson(route('command-center-v2.data'))->assertOk()->json();
    }

    public function test_only_command_center_viewers_see_it(): void
    {
        $nurse = User::factory()->create(['role' => User::ROLE_NURSE]);
        $this->actingAs($nurse)->get(route('command-center-v2.index'))->assertForbidden();
        $this->actingAs($nurse)->getJson(route('command-center-v2.data'))->assertForbidden();

        foreach ([User::ROLE_HOSPITAL_ADMIN, User::ROLE_IT_ADMIN] as $role) {
            $viewer = User::factory()->create(['role' => $role]);
            $this->actingAs($viewer)->get(route('command-center-v2.index'))->assertOk();
            $this->actingAs($viewer)->getJson(route('command-center-v2.data'))->assertOk();
        }
    }

    public function test_signed_out_screens_are_told_so_rather_than_sent_the_login_page(): void
    {
        $this->getJson(route('command-center-v2.data'))->assertUnauthorized();
    }

    public function test_the_page_opens_with_the_figures_and_asks_its_data_route_for_more(): void
    {
        $response = $this->actingAs($this->admin)->get(route('command-center-v2.index'))
            ->assertOk()
            ->assertSee('Command Center V2')
            ->assertSee('id="ccv2-snapshot"', false)
            ->assertSee('data-url="' . route('command-center-v2.data') . '"', false)
            ->assertSee(route('command-center.index'));

        $this->assertSame(6, $response->viewData('snapshot')['quality']['inpatients']);
        $this->assertSame(30, $response->viewData('snapshot')['refresh_seconds']);
    }

    public function test_no_patient_is_named(): void
    {
        $page = $this->actingAs($this->admin)->get(route('command-center-v2.index'))->getContent();
        $data = $this->actingAs($this->admin)->getJson(route('command-center-v2.data'))->getContent();

        foreach (['Lim Ah Kow', 'Tan Mei Ling', 'Siti Aminah', 'Rajesh Kumar', 'Wong Kar Wai', 'Nur Aisyah', 'Ahmad Faiz'] as $name) {
            $this->assertStringNotContainsString($name, $page);
            $this->assertStringNotContainsString($name, $data);
        }
    }

    public function test_capacity_counts_beds_by_status_against_the_target(): void
    {
        $capacity = $this->summary()['capacity'];

        $this->assertSame([
            'wards' => 3, 'beds' => 11, 'occupied' => 6, 'free' => 3, 'reserved' => 1, 'maintenance' => 1,
            'occupancy' => 54.5, 'status' => 'good', 'incoming' => 1, 'pending_discharge' => 1,
            'critical_care' => ['wards' => 1, 'beds' => 2, 'occupied' => 2, 'free' => 0, 'ventilated' => 0],
        ], $capacity);

        $wards = collect($this->summary()['wards'])->keyBy('code');
        $this->assertEquals(['occupancy' => 100.0, 'occupancy_status' => 'critical', 'free' => 0], array_intersect_key($wards['ICU1'], array_flip(['occupancy', 'occupancy_status', 'free'])));
        $this->assertEquals(['occupancy' => 60.0, 'occupancy_status' => 'good', 'free' => 1, 'maintenance' => 1], array_intersect_key($wards['MW1'], array_flip(['occupancy', 'occupancy_status', 'free', 'maintenance'])));
        $this->assertSame(route('critical-care.dashboard', ['ward_id' => $this->icu->id]), $wards['ICU1']['url']);
        $this->assertSame(route('ward.dashboard', ['ward_id' => $this->medical->id]), $wards['MW1']['url']);
    }

    public function test_the_hospital_filling_up_is_raised_above_target_and_again_near_full(): void
    {
        Bed::whereIn('status', ['available', 'reserved'])->update(['status' => 'occupied']);
        $attention = collect($this->summary()['attention'])->keyBy('key');

        $this->assertSame('warning', $attention['hospital:occupancy']['severity']);
        $this->assertSame('Hospital occupancy above target', $attention['hospital:occupancy']['title']);
        $this->assertSame('90.9% against the 85% target', $attention['hospital:occupancy']['detail']);

        Bed::query()->update(['status' => 'occupied']);
        Patient::create($this->patientFields($this->medical, null, 'Another Prebook') + ['status' => Patient::STATUS_PREBOOK, 'admitted_at' => null]);
        $attention = collect($this->summary()['attention'])->keyBy('key');

        $this->assertSame(['severity' => 'critical', 'title' => 'Hospital occupancy at 100%', 'detail' => '0 free beds across 3 wards'],
            array_intersect_key($attention['hospital:occupancy'], array_flip(['severity', 'title', 'detail'])));
        $this->assertSame('2 prebooked · 0 free · 1 pending discharge', $attention['hospital:bed-gap']['detail']);
        $this->assertSame('critical', $attention["ward:{$this->medical->id}:full"]['severity']);
    }

    public function test_flow_counts_today_against_the_same_time_yesterday_with_length_of_stay(): void
    {
        $this->log('admit', $this->surgical, '2026-09-20 11:00:00');
        $this->log('admit', $this->icu, '2026-09-25 10:00:00');
        $this->log('admit', $this->medical, '2026-09-25 16:00:00');
        $this->log('admit', $this->medical, '2026-09-26 09:00:00');
        $this->log('admit', $this->medical, '2026-09-26 14:10:00');
        // Four days, and by noon; two days, the admission only on the record; six days, yesterday morning
        $this->discharged($this->medical, '2026-09-22 10:30:00', '2026-09-26 10:30:00');
        $this->discharged($this->icu, '2026-09-24 13:00:00', '2026-09-26 13:00:00', logged: false);
        $this->discharged($this->surgical, '2026-09-19 09:00:00', '2026-09-25 09:00:00');
        // Ten days, in the thirty days before
        $this->discharged($this->medical, '2026-08-07 12:00:00', '2026-08-17 12:00:00');

        $summary = $this->summary();

        $this->assertEquals([
            'admissions_today' => 2, 'admissions_same_time_yesterday' => 1,
            'discharges_today' => 2, 'discharges_same_time_yesterday' => 1,
            'discharged_by_noon' => 1, 'discharged_by_noon_pct' => 50.0,
            'discharged_by_noon_pct_period' => 66.7, 'discharges_period' => 3,
            'alos' => 4.0, 'alos_previous' => 10.0, 'alos_stays' => 3,
        ], $summary['flow']);

        $wards = collect($summary['wards'])->keyBy('code');
        $this->assertSame([2, 1], [$wards['MW1']['admissions_today'], $wards['MW1']['discharges_today']]);
        $this->assertSame([0, 1], [$wards['ICU1']['admissions_today'], $wards['ICU1']['discharges_today']]);

        // Fourteen days to today; the midnight census worked back from the six inpatients now
        $trend = $summary['trend'];
        $this->assertSame('2026-09-13', $trend['dates'][0]);
        $this->assertSame('Sat 26 Sep', $trend['days'][13]);
        $this->assertSame([0, 0, 0, 0, 0, 0, 1, 1, 0, 1, 0, 0, 2, 2], $trend['admissions']);
        $this->assertSame([0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 2], $trend['discharges']);
        $this->assertSame([2, 2, 2, 2, 2, 2, 3, 4, 4, 5, 5, 5, 6, 6], $trend['census']);
    }

    public function test_quality_takes_the_ward_dashboards_flags_vital_signs_and_alert_response(): void
    {
        $nurseUser = User::factory()->create(['role' => User::ROLE_NURSE]);
        // Waiting 20 minutes and urgent; a newer one not yet overdue
        $this->alert($this->medical, 'calling', WardNotification::SEVERITY_URGENT, now()->subMinutes(20));
        $this->alert($this->icu, 'stable', WardNotification::SEVERITY_WARNING, now()->subMinutes(5));
        // Answered today in 4 and 10 minutes; closed by the system; answered in 2 minutes three days ago
        $this->alert($this->medical, 'calling', WardNotification::SEVERITY_URGENT, now()->setTime(10, 0), now()->setTime(10, 4), $nurseUser);
        $this->alert($this->icu, 'stable', WardNotification::SEVERITY_WARNING, now()->setTime(11, 0), now()->setTime(11, 10), $nurseUser);
        $this->alert($this->icu, 'stable', WardNotification::SEVERITY_WARNING, now()->setTime(12, 0), now()->setTime(13, 0));
        $this->alert($this->medical, 'calling', WardNotification::SEVERITY_URGENT, now()->subDays(3)->setTime(10, 0), now()->subDays(3)->setTime(10, 2), $nurseUser);

        $quality = $this->summary()['quality'];

        $this->assertSame(['inpatients' => 6, 'needs_attention' => 2, 'ews_urgent' => 1, 'ews_warning' => 0, 'escalations' => 0, 'overdue_care' => 0],
            array_intersect_key($quality, array_flip(['inpatients', 'needs_attention', 'ews_urgent', 'ews_warning', 'escalations', 'overdue_care'])));
        // Just admitted is not due yet; two days and thirty hours ago are missing
        $this->assertEquals(['obs_due' => 5, 'obs_done' => 3, 'obs_pct' => 60.0], array_intersect_key($quality, array_flip(['obs_due', 'obs_done', 'obs_pct'])));
        $this->assertEquals([
            'pending' => 2, 'urgent' => 1, 'waiting' => 1, 'waiting_urgent' => 1, 'oldest' => 20,
            'answered_today' => 2, 'median_today' => 7.0, 'median_week' => 4.0,
        ], $quality['alerts']);
        $this->assertSame('critical', $quality['status']);

        $medical = collect($this->summary()['wards'])->firstWhere('code', 'MW1');
        $this->assertEquals(['obs_due' => 2, 'obs_done' => 1, 'obs_pct' => 50.0, 'alerts' => 1, 'alerts_urgent' => 1, 'alerts_waiting' => 1, 'alerts_oldest' => 20],
            array_intersect_key($medical, array_flip(['obs_due', 'obs_done', 'obs_pct', 'alerts', 'alerts_urgent', 'alerts_waiting', 'alerts_oldest'])));
    }

    public function test_staffing_reads_the_current_shift_from_the_roster_against_each_wards_minimum(): void
    {
        WardRosterSetting::create(['ward_id' => $this->medical->id, 'rules' => ['min_staff' => ['AM' => 3, 'PM' => 3, 'ON' => 2]]]);
        [$ana, $ben, $chen, $dina, $eva] = array_map(fn ($name) => $this->nurse($name), ['Ana', 'Ben', 'Chen', 'Dina', 'Eva']);

        $this->roster($ana, $this->icu, '2026-09-26', 'PM');
        $this->roster($ben, $this->icu, '2026-09-26', 'AM');
        $this->roster($chen, $this->medical, '2026-09-26', 'PM');
        // No roster entry, but holds beds on this ward's PM shift
        $this->holdsBed($dina, $this->medical, 'MW01', '2026-09-26', 'PM');
        // Holds a bed here, but the roster puts her on the ICU's morning
        $this->roster($eva, $this->icu, '2026-09-26', 'AM');
        $this->holdsBed($eva, $this->medical, 'MW02', '2026-09-26', 'PM');

        $summary = $this->summary();
        $wards = collect($summary['wards'])->keyBy('code');

        $this->assertEquals(['shift' => 'PM', 'on_duty' => 1, 'minimum' => 1, 'patients' => 2, 'ratio' => 2.0, 'status' => 'good'],
            array_intersect_key($wards['ICU1']['staffing'], array_flip(['shift', 'on_duty', 'minimum', 'patients', 'ratio', 'status'])));
        $this->assertEquals(['shift' => 'PM', 'on_duty' => 2, 'minimum' => 3, 'patients' => 3, 'ratio' => 1.5, 'status' => 'serious'],
            array_intersect_key($wards['MW1']['staffing'], array_flip(['shift', 'on_duty', 'minimum', 'patients', 'ratio', 'status'])));
        $this->assertNull($wards['SW1']['staffing']);

        $this->assertEquals([
            'wards' => 3, 'rostered_wards' => 2, 'shifts' => ['PM'], 'on_duty' => 3, 'minimum' => 4, 'coverage' => 75.0,
            'patients' => 5, 'ratio' => 1.7, 'below_minimum' => 1, 'status' => 'serious',
        ], $summary['workforce']);
    }

    public function test_in_the_small_hours_the_night_on_duty_is_the_one_that_started_yesterday_evening(): void
    {
        $this->travelTo(Carbon::parse('2026-09-27 02:00:00'));
        [$ana, $ben] = [$this->nurse('Ana'), $this->nurse('Ben')];
        $this->roster($ana, $this->icu, '2026-09-26', 'ON');
        $this->roster($ben, $this->icu, '2026-09-27', 'ON');

        $icu = collect($this->summary()['wards'])->firstWhere('code', 'ICU1');

        $this->assertSame(['shift' => 'ON', 'on_duty' => 1], array_intersect_key($icu['staffing'], array_flip(['shift', 'on_duty'])));
    }

    public function test_attention_lists_the_most_serious_first_and_marks_each_ward(): void
    {
        WardRosterSetting::create(['ward_id' => $this->medical->id, 'rules' => ['min_staff' => ['AM' => 3, 'PM' => 3, 'ON' => 2]]]);
        $this->roster($this->nurse('Ana'), $this->medical, '2026-09-26', 'PM');
        $this->alert($this->medical, 'calling', WardNotification::SEVERITY_URGENT, now()->subMinutes(20));

        $summary = $this->summary();
        $attention = collect($summary['attention']);

        $this->assertSame([
            "ward:{$this->icu->id}:full",
            "ward:{$this->medical->id}:alerts",
            "ward:{$this->icu->id}:ews",
            "ward:{$this->medical->id}:staffing",
            "ward:{$this->medical->id}:observations",
            "ward:{$this->surgical->id}:observations",
        ], $attention->pluck('key')->all());
        $this->assertSame(6, $summary['attention_total']);

        $this->assertSame(['severity' => 'critical', 'title' => 'Intensive Care Unit has no free beds', 'detail' => '2 of 2 occupied · 1 prebooked'],
            array_intersect_key($attention[0], array_flip(['severity', 'title', 'detail'])));
        $this->assertSame(['title' => 'Medical Ward 1: 1 alert waiting over 15 min', 'detail' => 'Oldest 20 min · 1 urgent'],
            array_intersect_key($attention[1], array_flip(['title', 'detail'])));
        $this->assertSame('Intensive Care Unit: 1 patient at EWS 5 or more', $attention[2]['title']);
        $this->assertSame(['title' => 'Medical Ward 1: 1 of 3 nurses on the PM shift', 'detail' => '3 patients · 3 per nurse'],
            array_intersect_key($attention[3], array_flip(['title', 'detail'])));
        $this->assertSame(['title' => 'Surgical Ward 1: 1 patient without vital signs in 24 h', 'detail' => '0% observed'],
            array_intersect_key($attention[5], array_flip(['title', 'detail'])));
        $this->assertSame(route('ward.dashboard', ['ward_id' => $this->medical->id]), $attention[1]['url']);

        $this->assertSame(['ICU1' => 'critical', 'MW1' => 'critical', 'SW1' => 'warning'], collect($summary['wards'])->pluck('status', 'code')->all());
    }

    public function test_acuity_is_the_share_of_inpatients_with_each_risk_and_each_ward_has_its_own(): void
    {
        $summary = $this->summary();
        $acuity = collect($summary['acuity'])->keyBy('key');

        $this->assertSame(['high_acuity', 'ventilated', 'high_fall', 'isolation', 'transfusing'], $acuity->keys()->all());
        $this->assertSame([2, 33.3, 'High acuity'], [$acuity['high_acuity']['count'], $acuity['high_acuity']['pct'], $acuity['high_acuity']['short']]);
        $this->assertSame([1, 16.7], [$acuity['high_fall']['count'], $acuity['high_fall']['pct']]);
        $this->assertSame([1, 16.7], [$acuity['isolation']['count'], $acuity['isolation']['pct']]);
        $this->assertSame(0, $acuity['ventilated']['count']);

        $wards = collect($summary['wards'])->keyBy('code');
        $risks = fn (string $code) => array_intersect_key($wards[$code], array_flip(['high_acuity', 'high_fall', 'isolation']));
        $this->assertSame(['high_acuity' => 2, 'high_fall' => 1, 'isolation' => 0], $risks('ICU1'));
        $this->assertSame(['high_acuity' => 0, 'high_fall' => 0, 'isolation' => 0], $risks('MW1'));
        $this->assertSame(['high_acuity' => 0, 'high_fall' => 0, 'isolation' => 1], $risks('SW1'));
    }

    public function test_a_hospital_without_active_wards_gets_an_empty_board(): void
    {
        Ward::query()->update(['is_active' => false]);

        $summary = $this->summary();

        $this->assertSame([], $summary['wards']);
        $this->assertSame([], $summary['attention']);
        $this->assertNull($summary['capacity']['occupancy']);
        $this->assertNull($summary['workforce']['status']);
        $this->assertSame(array_fill(0, 14, 0), $summary['trend']['census']);
        $this->actingAs($this->admin)->get(route('command-center-v2.index'))->assertOk();
    }

    public function test_the_sidebar_links_it_and_a_hospital_can_hide_it(): void
    {
        $link = 'href="' . route('command-center-v2.index') . '"';
        $this->actingAs($this->admin)->get(route('command-center.index'))->assertSee($link, false);

        $this->hospital->update(['hidden_nav_items' => ['command-center-v2']]);
        $this->actingAs($this->admin)->get(route('command-center.index'))
            ->assertDontSee($link, false)
            ->assertSee('href="' . route('command-center.index') . '"', false);
    }
}
