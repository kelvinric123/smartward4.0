<?php

namespace Tests\Feature;

use App\Models\AdmissionLog;
use App\Models\Bed;
use App\Models\Hospital;
use App\Models\Nurse;
use App\Models\NurseCredential;
use App\Models\NurseLeave;
use App\Models\NursePrivilege;
use App\Models\NurseRosterEntry;
use App\Models\NurseRosterRequest;
use App\Models\Patient;
use App\Models\PatientAcuityRating;
use App\Models\PublicHoliday;
use App\Models\ShiftSetting;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The AI Nurse Schedule's three report printouts: monthly productivity,
 * leave and holidays, and credentialing and privileging. Who may print each,
 * the figures behind them, and what they must never show.
 *
 * Thu 8 Oct 2026, 10:00. Ward 6 has three patients now. The admission log
 * has a discharge on Tue 6 Oct and admissions on Wed 7 and Thu 8, so the
 * census works back to 2 on 1-5 Oct, 1 on the 6th, 2 on the 7th and 3 today.
 * Aisyah is on AM every day of October but the 6th; on Wed 7 Oct the AM has
 * Aisyah and Siti (a student), the PM Mei Ling and Ah Chong (a care
 * assistant), the ON Farah. On the 6th only Siti is on, on the AM. Two
 * patients were rated on the 7th: Level 4 (after a Level 3 earlier that
 * day) and Level 1. Founders Day is Fri 9 Oct; Farah is on annual leave on
 * the 9th and 10th, Mei Ling on medical leave 29 Sep to 2 Oct.
 */
class AiNurseScheduleReportsTest extends TestCase
{
    use RefreshDatabase;

    private const WEEK = '2026-10-05';

    private Ward $ward;
    private array $beds = [];
    private array $patients = [];
    private Nurse $aisyah;
    private Nurse $meiLing;
    private Nurse $farah;
    private Nurse $siti;
    private Nurse $ahChong;
    private User $manager;
    private User $nurseUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-08 10:00:00'));

        $hospital = Hospital::create(['name' => 'Test Hospital']);
        $this->ward = Ward::create(['hospital_id' => $hospital->id, 'ward_code' => 'W6', 'ward_name' => 'Ward 6', 'is_active' => true]);
        foreach (ShiftSetting::getDefaults() as $shift) {
            ShiftSetting::create($shift + ['ward_id' => $this->ward->id, 'is_active' => true]);
        }
        foreach (['B1', 'B2', 'B3'] as $number) {
            $this->beds[$number] = Bed::create(['ward_id' => $this->ward->id, 'bed_number' => $number, 'bed_id' => 'W6-' . $number, 'bed_display_name' => $number, 'status' => 'occupied', 'is_active' => true]);
        }

        $this->aisyah = $this->nurse('Aisyah Rahman', 'aisyah', ['phone' => '012-555 0101', 'email' => 'aisyah.rahman@example.test']);
        $this->meiLing = $this->nurse('Mei Ling', 'meiling');
        $this->farah = $this->nurse('Farah Idris', 'farah');
        $this->siti = $this->nurse('Siti Student', 'siti', ['designation' => 'Student nurse']);
        $this->ahChong = $this->nurse('Ah Chong', 'ahchong', ['designation' => 'Health Care Assistant']);
        $this->manager = User::factory()->create(['name' => 'Sister Wong']);
        $this->nurseUser = User::factory()->create(['name' => 'Staff Nurse Lim', 'role' => User::ROLE_NURSE]);

        // Levels now: B1 rated 3 (7.5 h), B2 suggested 1 (3 h), B3 suggested 2 for the fall risk (5 h)
        $this->patients['B1'] = $this->patient('B1', 'Siti Aminah', ['nursing_level' => 'level_3']);
        $this->patients['B2'] = $this->patient('B2', 'Tan Ah Kow');
        $this->patients['B3'] = $this->patient('B3', 'Rajesh Kumar', ['fall_risk' => '1']);
        $gone = $this->patient('B9', 'Gone Home', ['status' => Patient::STATUS_DISCHARGED, 'is_active' => false]);

        $this->log($gone, 'discharge', 'discharged_at', '2026-10-06 15:00:00');
        $this->log($this->patients['B3'], 'admit', 'admitted_at', '2026-10-07 09:00:00');
        $this->log($this->patients['B2'], 'admit', 'admitted_at', '2026-10-08 08:00:00');
        // Another ward's admission never counts here
        $other = Ward::create(['hospital_id' => $hospital->id, 'ward_code' => 'W7', 'ward_name' => 'Ward 7', 'is_active' => true]);
        AdmissionLog::create(['patient_id' => $gone->id, 'ward_id' => $other->id, 'bed_number' => 'X1', 'action' => 'admit', 'patient_name' => 'Gone Home', 'mrn' => 'MRN-B9', 'admitted_at' => '2026-10-07 12:00:00']);

        $this->rate('B1', 3, '2026-10-07 09:00:00');
        $this->rate('B1', 4, '2026-10-07 11:00:00');
        $this->rate('B3', 1, '2026-10-07 10:00:00');

        for ($day = 1; $day <= 31; $day++) {
            if ($day !== 6) {
                $this->roster($this->aisyah, sprintf('2026-10-%02d', $day), 'AM');
            }
        }
        $this->roster($this->siti, '2026-10-07', 'AM');
        $this->roster($this->meiLing, '2026-10-07', 'PM');
        $this->roster($this->ahChong, '2026-10-07', 'PM');
        $this->roster($this->farah, '2026-10-07', 'ON');
        $this->roster($this->siti, '2026-10-06', 'AM');

        PublicHoliday::create(['holiday_date' => '2026-10-09', 'name' => 'Founders Day']);
        NurseLeave::create(['nurse_id' => $this->farah->id, 'type' => 'annual', 'start_date' => '2026-10-09', 'end_date' => '2026-10-10', 'created_by' => $this->manager->id]);
        NurseLeave::create(['nurse_id' => $this->meiLing->id, 'type' => 'medical', 'start_date' => '2026-09-29', 'end_date' => '2026-10-02', 'note' => 'Fever and cough']);
        NurseLeave::create(['nurse_id' => $this->siti->id, 'type' => 'training', 'start_date' => '2026-12-01', 'end_date' => '2026-12-03']);
        NurseLeave::create(['nurse_id' => $this->aisyah->id, 'type' => 'annual', 'start_date' => '2026-02-02', 'end_date' => '2026-02-06']);
    }

    private function nurse(string $name, string $username, array $fields = []): Nurse
    {
        return Nurse::create($fields + [
            'name' => $name, 'registration_number' => 'LJM-' . crc32($name), 'designation' => 'STAFF NURSE I',
            'ward_id' => $this->ward->id, 'app_username' => $username, 'app_password' => 'secret123', 'is_active' => true,
        ]);
    }

    private function patient(string $bed, string $name, array $fields = []): Patient
    {
        return Patient::create($fields + [
            'name' => $name, 'mrn' => 'MRN-' . $bed, 'rn' => 'RN-' . $bed, 'ic_passport' => 'IC-' . $bed,
            'age' => 50, 'gender' => 'Female', 'phone' => '0', 'ward_id' => $this->ward->id, 'bed_number' => $bed,
            'status' => 'admitted', 'admitted_at' => now()->subDays(3), 'is_active' => true,
        ]);
    }

    private function log(Patient $patient, string $action, string $column, string $at): void
    {
        AdmissionLog::create([
            'patient_id' => $patient->id, 'ward_id' => $this->ward->id, 'bed_number' => $patient->bed_number,
            'action' => $action, 'patient_name' => $patient->name, 'mrn' => $patient->mrn, $column => $at,
        ]);
    }

    private function rate(string $bed, int $level, string $at): void
    {
        PatientAcuityRating::create([
            'patient_id' => $this->patients[$bed]->id, 'ward_id' => $this->ward->id, 'bed_number' => $bed,
            'level' => $level, 'suggested_level' => 1, 'rated_by_name' => 'Sister Wong', 'rated_at' => $at,
        ]);
    }

    private function roster(Nurse $nurse, string $date, string $shift): void
    {
        NurseRosterEntry::create(['ward_id' => $this->ward->id, 'nurse_id' => $nurse->id, 'roster_date' => $date, 'shift' => $shift, 'source' => NurseRosterEntry::SOURCE_AUTO]);
    }

    private function printAs(User $user, array $query = [])
    {
        return $this->actingAs($user)->get(route('ward.ai-schedule.print', $query + ['ward_id' => $this->ward->id, 'week' => self::WEEK]));
    }

    private function credentials(): void
    {
        $credential = fn (Nurse $nurse, array $fields) => $nurse->credentials()->create($fields + [
            'issuing_body' => NurseCredential::NURSING_BOARD, 'issued_on' => '2026-01-02',
            'reference_number' => 'REF-' . $nurse->id . '-' . $fields['type'], 'notes' => 'Private note on file',
            'verified_at' => '2026-01-05 09:00:00',
        ]);

        // Aisyah: APC ran out 8 days ago; BLS runs out in 60 days
        $credential($this->aisyah, ['type' => 'apc', 'title' => 'Annual Practising Certificate 2026', 'expires_on' => '2026-09-30']);
        $credential($this->aisyah, ['type' => 'life_support', 'title' => 'Basic Life Support (BLS)', 'expires_on' => '2026-12-07']);
        // Mei Ling: APC to the end of next year, an ALS privilege without ACLS, an IV review overdue
        $credential($this->meiLing, ['type' => 'apc', 'title' => 'Annual Practising Certificate 2027', 'expires_on' => '2027-12-31']);
        $this->meiLing->privileges()->create(['name' => 'Adult advanced life support', 'code' => 'adult_als', 'category' => 'advanced', 'status' => 'granted', 'granted_on' => '2026-01-10', 'review_on' => '2027-01-10', 'notes' => 'Private privilege note']);
        $this->meiLing->privileges()->create(['name' => 'Peripheral IV cannulation', 'code' => 'piv', 'category' => 'core', 'status' => 'granted', 'granted_on' => '2023-09-01', 'review_on' => '2026-09-01']);
        // Farah: APC to 31 Dec (84 days), and a registration never sighted
        $credential($this->farah, ['type' => 'apc', 'title' => 'Annual Practising Certificate 2026', 'expires_on' => '2026-12-31']);
        $credential($this->farah, ['type' => 'registration', 'title' => 'Registered Nurse', 'verified_at' => null]);
    }

    public function test_who_may_print_each_report(): void
    {
        $this->get(route('ward.ai-schedule.print', ['format' => 'credentials']))->assertRedirect(route('login'));

        $titles = [
            'productivity' => 'Monthly productivity report',
            'leave' => 'Leave &amp; holiday report',
            'credentials' => 'Credentialing &amp; privileging report',
        ];
        foreach ($titles as $format => $title) {
            $this->printAs($this->manager, ['format' => $format])
                ->assertOk()
                ->assertViewHas('format', $format)
                ->assertSee($title . ': Ward 6', false)
                ->assertSee('Confidential: staff information')
                ->assertSee('size: A4 landscape;', false);
        }

        // The staff reports are refused by name to a nurse and to the shared ward screen login, not swapped for another printout
        $wardScreen = User::factory()->create(['role' => User::ROLE_WARD_DASHBOARD]);
        foreach ([$this->nurseUser, $wardScreen] as $user) {
            $this->printAs($user, ['format' => 'productivity'])->assertForbidden();
            $this->printAs($user, ['format' => 'credentials'])->assertForbidden();
            $this->printAs($user, ['format' => 'leave'])->assertOk()->assertSee('Leave &amp; holiday report: Ward 6', false);
        }
    }

    public function test_productivity_works_the_census_back_from_the_admission_log(): void
    {
        $report = $this->printAs($this->manager, ['format' => 'productivity'])
            ->assertOk()
            ->assertSee('October 2026 · 31 days')
            ->viewData('report');
        $days = collect($report['days'])->keyBy('day');

        $this->assertSame(
            [2, 2, 2, 2, 2, 1, 2, 3, 3],
            collect(range(1, 9))->map(fn (int $day) => $days[sprintf('2026-10-%02d', $day)]['census'])->all()
        );
        $this->assertSame([0, 1, 1], [$days['2026-10-06']['admissions'], $days['2026-10-07']['admissions'], $days['2026-10-08']['admissions']]);
        $this->assertSame(1, $days['2026-10-06']['discharges']);
        $this->assertFalse($days['2026-10-08']['projected']);
        $this->assertTrue($days['2026-10-09']['projected']);

        // The month to today: 2 a day on 1-5, then 1, 2, 3; the 23 days after today are projected at 3 and added up apart
        $this->assertSame([16, 8, 24], [$report['totals']['patient_days'], $report['totals']['days'], $report['totals']['shifts']]);
        $this->assertSame(23, $report['totals']['days_projected']);
        $this->assertSame([23, 23, 69], [$report['totals']['projected']['days'], $report['totals']['projected']['days_rostered'], $report['totals']['projected']['patient_days']]);
        $this->assertSame([2, 1, 0, 0], [$report['totals']['admissions'], $report['totals']['discharges'], $report['totals']['transfers_in'], $report['totals']['transfers_out']]);
    }

    public function test_the_census_counts_moves_between_wards(): void
    {
        $other = Ward::where('ward_code', 'W7')->firstOrFail();
        $at = function (array $fields, string $when) {
            $log = AdmissionLog::create($fields + ['patient_name' => 'Patient', 'mrn' => 'MRN']);
            $log->forceFill(['created_at' => $when])->save();
        };

        // B1's patient came in on 30 Sep and moved bed on this ward on the 3rd: no change to the census
        $at(['patient_id' => $this->patients['B1']->id, 'ward_id' => $this->ward->id, 'bed_number' => 'B1', 'action' => 'admit', 'admitted_at' => '2026-09-30 09:00:00'], '2026-09-30 09:00:00');
        // Admitted here on the 2nd, moved to Ward 7 on the 5th: nothing on this ward's side of the log
        $leaver = $this->patient('B7', 'Moved Out', ['ward_id' => $other->id]);
        $at(['patient_id' => $leaver->id, 'ward_id' => $this->ward->id, 'bed_number' => 'B7', 'action' => 'admit', 'admitted_at' => '2026-10-02 09:00:00'], '2026-10-02 09:00:00');
        $at(['patient_id' => $this->patients['B1']->id, 'ward_id' => $this->ward->id, 'bed_number' => 'B1', 'action' => 'transfer'], '2026-10-03 11:00:00');
        // Admitted to Ward 7 in September, moved here on the 4th and still in B4
        Bed::create(['ward_id' => $this->ward->id, 'bed_number' => 'B4', 'bed_id' => 'W6-B4', 'bed_display_name' => 'B4', 'status' => 'occupied', 'is_active' => true]);
        $arrival = $this->patient('B4', 'Moved In');
        $at(['patient_id' => $arrival->id, 'ward_id' => $other->id, 'bed_number' => 'X4', 'action' => 'admit', 'admitted_at' => '2026-09-20 09:00:00'], '2026-09-20 09:00:00');
        $at(['patient_id' => $arrival->id, 'ward_id' => $this->ward->id, 'bed_number' => 'B4', 'action' => 'transfer'], '2026-10-04 14:00:00');
        $at(['patient_id' => $leaver->id, 'ward_id' => $other->id, 'bed_number' => 'X7', 'action' => 'transfer'], '2026-10-05 11:00:00');

        $report = $this->printAs($this->manager, ['format' => 'productivity'])->assertOk()->viewData('report');
        $days = collect($report['days'])->keyBy('day');

        // 2 on the 1st, the leaver in on the 2nd, the arrival on the 4th, the leaver out on the 5th, then as before with 4 now
        $this->assertSame(
            [2, 3, 3, 4, 3, 2, 3, 4],
            collect(range(1, 8))->map(fn (int $day) => $days[sprintf('2026-10-%02d', $day)]['census'])->all()
        );
        $this->assertSame([0, 1, 0], [$days['2026-10-03']['transfers_in'], $days['2026-10-04']['transfers_in'], $days['2026-10-04']['transfers_out']]);
        $this->assertSame(1, $days['2026-10-05']['transfers_out']);
        $this->assertSame([1, 1], [$report['totals']['transfers_in'], $report['totals']['transfers_out']]);
    }

    public function test_productivity_uses_the_ratings_on_a_rated_day_and_todays_mix_otherwise(): void
    {
        $report = $this->printAs($this->manager, ['format' => 'productivity'])->assertOk()->viewData('report');
        $days = collect($report['days'])->keyBy('day');

        // Wed 7: B1's last rating 4 (12 h) and B3's 1 (3 h) average 7.5 h; census 2 needs 15 h
        $this->assertSame(['rated', 7.5, 2, 15.0], [$days['2026-10-07']['source'], $days['2026-10-07']['hours_per_patient'], $days['2026-10-07']['rated_patients'], $days['2026-10-07']['required_hours']]);

        // Other days: today's mix, 7.5 + 3 + 5 h over 3 patients = 5.2 h
        $this->assertSame(5.2, $report['estimate']['hours_per_patient']);
        $this->assertSame(['estimated', 5.2, 5.2], [$days['2026-10-06']['source'], $days['2026-10-06']['hours_per_patient'], $days['2026-10-06']['required_hours']]);
        $this->assertSame(['estimated', 15.6], [$days['2026-10-08']['source'], $days['2026-10-08']['required_hours']]);
        // The month to today: 8 days, 1 of them rated
        $this->assertSame([1, 0, 7], [$report['totals']['days_rated'], $report['totals']['days_partly_rated'], $report['totals']['days_estimated']]);
    }

    public function test_a_day_with_only_some_patients_rated_takes_the_rest_from_todays_mix(): void
    {
        // Mon 5 Oct, 2 patients: only B1 was rated (Level 4, 12 h); the other is at today's 5.2 h, not at B1's 12 h
        $this->rate('B1', 4, '2026-10-05 10:00:00');

        $response = $this->printAs($this->manager, ['format' => 'productivity'])->assertOk();
        $monday = collect($response->viewData('report')['days'])->firstWhere('day', '2026-10-05');

        $this->assertSame(['partly', 1, 2, 17.2, 8.6], [$monday['source'], $monday['rated_patients'], $monday['census'], $monday['required_hours'], $monday['hours_per_patient']]);
        $this->assertSame(1, $response->viewData('report')['totals']['days_partly_rated']);
        $response->assertSee('1 of 2 rated');
    }

    public function test_days_after_today_without_a_roster_are_neither_short_nor_in_the_targets(): void
    {
        // The roster is built to Sun 18 Oct only
        NurseRosterEntry::where('roster_date', '>', '2026-10-18')->delete();

        $response = $this->printAs($this->manager, ['format' => 'productivity'])->assertOk();
        $report = $response->viewData('report');
        $days = collect($report['days']);

        // Unrostered days later in the month would each be 3 shifts short: none of them reach the month's figures
        $this->assertSame(3, $days->firstWhere('day', '2026-10-20')['shifts_short']);
        $this->assertSame($days->where('projected', false)->sum('shifts_short'), $report['totals']['shifts_short']);
        $this->assertSame($days->where('projected', false)->sum('shifts_below_min'), $report['totals']['shifts_below_min']);
        $this->assertSame([23, 10], [$report['totals']['projected']['days'], $report['totals']['projected']['days_rostered']]);

        // Targets run to the 18th: 5 a week over 18 days is 12.9; Farah's 2 leave days take 2 × 5/7 off
        $nurses = collect($report['nurses'])->keyBy('name');
        $this->assertSame([18, '2026-10-18', 12.9], [$report['rules']['target_days'], $report['rules']['target_through']->toDateString(), $report['rules']['target_shifts']]);
        $this->assertSame([17, 12.9, 4.1], [$nurses['Aisyah Rahman']['shifts'], $nurses['Aisyah Rahman']['expected'], $nurses['Aisyah Rahman']['variance']]);
        $this->assertSame([2, 11.4, -10.4], [$nurses['Farah Idris']['leave'], $nurses['Farah Idris']['expected'], $nurses['Farah Idris']['variance']]);

        $response->assertSee('No roster yet')->assertSee('the 18 days to 18 Oct')->assertSee('Month to 8 Oct');
    }

    public function test_productivity_counts_only_registered_nurse_hours_and_does_the_sums(): void
    {
        $report = $this->printAs($this->manager, ['format' => 'productivity'])->assertOk()->viewData('report');
        $wednesday = collect($report['days'])->firstWhere('day', '2026-10-07');

        // RN: Aisyah AM 7 h + Mei Ling PM 9 h + Farah ON 8 h; the student and the care assistant add 7 + 9 h to all hours
        $this->assertSame([24.0, 40.0], [$wednesday['rn_hours'], $wednesday['all_hours']]);
        $this->assertSame(['count' => 2, 'registered' => 1, 'min' => 1, 'recommended' => 1, 'below_min' => false, 'short' => false], $wednesday['shifts']['AM']);
        // 15 h needed / 24 RN h = 62.5%, and 24 RN h for 2 patients
        $this->assertSame([63, 12.0, 0, 0], [$wednesday['productivity'], $wednesday['actual_hppd'], $wednesday['shifts_short'], $wednesday['shifts_below_min']]);

        // Tue 6: only the student, on the AM: every shift short of a registered nurse, PM and ON below the minimum
        $tuesday = collect($report['days'])->firstWhere('day', '2026-10-06');
        $this->assertSame([0.0, 7.0, null, 3, 2], [$tuesday['rn_hours'], $tuesday['all_hours'], $tuesday['productivity'], $tuesday['shifts_short'], $tuesday['shifts_below_min']]);
    }

    public function test_productivity_per_nurse_shows_extra_shifts_against_the_target(): void
    {
        $response = $this->printAs($this->manager, ['format' => 'productivity'])->assertOk();
        $nurses = collect($response->viewData('report')['nurses'])->keyBy('name');

        // 5 a week over 31 days is 22.1 shifts; Aisyah worked 30, the holiday among them
        $this->assertSame([30, 30, 210.0, 1, 22.1, 7.9], [
            $nurses['Aisyah Rahman']['shifts'], $nurses['Aisyah Rahman']['AM'], $nurses['Aisyah Rahman']['hours'],
            $nurses['Aisyah Rahman']['holiday'], $nurses['Aisyah Rahman']['expected'], $nurses['Aisyah Rahman']['variance'],
        ]);
        // Farah's 2 days of leave take their share of the target off: 5 a week over 29 days is 20.7
        $this->assertSame([1, 2, 20.7, -19.7], [$nurses['Farah Idris']['shifts'], $nurses['Farah Idris']['leave'], $nurses['Farah Idris']['expected'], $nurses['Farah Idris']['variance']]);
        $this->assertFalse($nurses['Siti Student']['registered']);

        $response->assertSee('+7.9 extra')
            ->assertSee('ROSTERED hours')
            ->assertSee('(ANA, 2017, pp. 4, 8)')
            ->assertSee('Measures chosen from the American Nurses Association (2017)');

        // Sections switch off; the definitions go with them
        $this->printAs($this->manager, ['format' => 'productivity', 'sections' => ['daily']])
            ->assertOk()
            ->assertSee('Day by day')
            ->assertDontSee('+7.9 extra')
            ->assertDontSee('How the figures are worked out');
    }

    public function test_the_month_is_checked_and_defaults_to_the_weeks_month(): void
    {
        $month = fn (array $query) => $this->printAs($this->manager, $query + ['format' => 'productivity'])->assertOk()->viewData('report')['month']->format('Y-m');

        $this->assertSame('2026-10', $month([]));
        $this->assertSame('2025-01', $month(['month' => '2025-01']));
        // The week of Mon 28 Sep has its Thursday on 1 Oct, so it is October's
        $this->assertSame('2026-10', $month(['week' => '2026-09-28']));
        $this->assertSame('2026-09', $month(['week' => '2026-09-21']));
        foreach (['2026-13', 'October', '2026-1', ['2026-10'], '2029-01', '2024-01'] as $bad) {
            $this->assertSame('2026-10', $month(['month' => $bad]));
        }
        // A week far off cannot carry the month past the limit either: the census would walk back to it day by day
        $this->assertSame('2026-10', $month(['week' => '0001-01-01']));
        $this->assertSame('2026-10', $month(['week' => '1990-01-01', 'month' => '1990-01']));
        $this->assertSame('2025-03', $month(['week' => '1990-01-01', 'month' => '2025-03']));

        // The quick switches keep the month
        $query = $this->printAs($this->manager, ['format' => 'productivity', 'month' => '2026-08'])->viewData('query');
        $this->assertSame('2026-08', $query['month']);
        $this->assertArrayNotHasKey('names', $query);
    }

    public function test_leave_is_clipped_to_the_period_and_totalled_by_type(): void
    {
        $response = $this->printAs($this->manager, ['format' => 'leave', 'month' => '2026-10'])
            ->assertOk()
            ->assertSee('October 2026');
        $report = $response->viewData('report');

        $entries = collect($report['entries'])->keyBy('nurse');
        // Mei Ling's MC runs 29 Sep to 2 Oct: 2 of its 4 days are in October
        $this->assertSame([2, 4, true, 'MC'], [$entries['Mei Ling']['days'], $entries['Mei Ling']['total_days'], $entries['Mei Ling']['clipped'], $entries['Mei Ling']['code']]);
        $this->assertSame([2, false, 'Sister Wong'], [$entries['Farah Idris']['days'], $entries['Farah Idris']['clipped'], $entries['Farah Idris']['booked_by']]);
        $this->assertCount(2, $report['entries']);
        $this->assertSame(['annual' => 2, 'medical' => 2, 'training' => 0], array_intersect_key($report['totals']['by_type'], array_flip(['annual', 'medical', 'training'])));
        $this->assertSame([4, 2, 5, 0.8], [$report['totals']['days'], $report['totals']['nurses_with_leave'], $report['team'], $report['totals']['average_days']]);

        $nurses = collect($report['nurses'])->keyBy('name');
        $this->assertSame(['days' => 2, 'entries' => 1], ['days' => $nurses['Farah Idris']['days'], 'entries' => $nurses['Farah Idris']['entries']]);
        $this->assertSame(2, $nurses['Farah Idris']['by_type']['annual']);

        // The calendar: 1 of 5 away is 20%, so the day is flagged
        $calendar = collect($report['calendar'][0]['days'])->keyBy('day');
        $this->assertSame([1, ['annual' => 1], true, 'Founders Day'], [$calendar['2026-10-09']['on_leave'], $calendar['2026-10-09']['codes'], $calendar['2026-10-09']['busy'], $calendar['2026-10-09']['holiday']]);
        $this->assertSame(['AM' => 2, 'PM' => 2, 'ON' => 1], $calendar['2026-10-07']['rostered']);
        $this->assertFalse($calendar['2026-10-05']['busy']);
        $response->assertSee('20% or more of the team');
    }

    public function test_leave_notes_show_only_when_asked_for(): void
    {
        NurseRosterRequest::create(['ward_id' => $this->ward->id, 'nurse_id' => $this->meiLing->id, 'type' => 'leave', 'leave_type' => 'annual', 'start_date' => '2026-10-20', 'end_date' => '2026-10-21', 'note' => 'Family wedding', 'status' => 'pending']);

        $this->printAs($this->manager, ['format' => 'leave'])
            ->assertOk()
            ->assertDontSee('Fever and cough')
            ->assertDontSee('Family wedding')
            ->assertSee('Leave notes are left out')
            ->assertViewHas('report', fn (array $report) => collect($report['entries'])->pluck('note')->filter()->isEmpty());

        $this->printAs($this->manager, ['format' => 'leave', 'sections' => ['entries', 'requests', 'leave_notes']])
            ->assertOk()
            ->assertSee('Fever and cough')
            ->assertSee('Family wedding');

        // A nurse, or the shared ward screen, may print the report but never the notes, even when asking for them
        $wardScreen = User::factory()->create(['role' => User::ROLE_WARD_DASHBOARD]);
        foreach ([$this->nurseUser, $wardScreen] as $user) {
            $this->printAs($user, ['format' => 'leave', 'period' => 'year', 'sections' => ['entries', 'requests', 'leave_notes']])
                ->assertOk()
                ->assertDontSee('Fever and cough')
                ->assertDontSee('Family wedding')
                ->assertSee('Leave notes are left out')
                ->assertViewHas('sections', ['entries', 'requests']);
        }
    }

    public function test_overlapping_leave_counts_each_day_once(): void
    {
        // Medical leave booked over the last day of Farah's annual leave: she was away 9-11 Oct, 3 days
        NurseLeave::create(['nurse_id' => $this->farah->id, 'type' => 'medical', 'start_date' => '2026-10-10', 'end_date' => '2026-10-11']);

        $report = $this->printAs($this->manager, ['format' => 'leave', 'month' => '2026-10'])->assertOk()->viewData('report');
        $farah = collect($report['nurses'])->firstWhere('name', 'Farah Idris');

        $this->assertSame([3, 2, 1, 2], [$farah['days'], $farah['by_type']['annual'], $farah['by_type']['medical'], $farah['entries']]);
        // Each leave still shows its own days in the period
        $this->assertSame([2, 2], collect($report['entries'])->where('nurse', 'Farah Idris')->pluck('days')->all());
        $this->assertSame([5, 2, 3], [$report['totals']['days'], $report['totals']['by_type']['annual'], $report['totals']['by_type']['medical']]);
        $this->assertSame(1, collect($report['calendar'][0]['days'])->firstWhere('day', '2026-10-10')['on_leave']);
    }

    public function test_a_holiday_shows_who_worked_it_and_who_was_away(): void
    {
        $holiday = $this->printAs($this->manager, ['format' => 'leave'])->assertOk()->viewData('report')['holidays'][0];

        $this->assertSame('Founders Day', $holiday['name']);
        $this->assertSame([['name' => 'Aisyah Rahman', 'shift' => 'AM']], $holiday['worked']);
        $this->assertSame([['name' => 'Farah Idris', 'code' => 'AL', 'label' => 'Annual leave']], $holiday['on_leave']);
    }

    public function test_quarter_and_year_periods(): void
    {
        $quarter = $this->printAs($this->manager, ['format' => 'leave', 'month' => '2026-10', 'period' => 'quarter'])
            ->assertOk()
            ->assertSee('Q4 2026 · 1 Oct – 31 Dec 2026')
            ->viewData('report');
        $this->assertSame(['2026-10-01', '2026-12-31', 3], [$quarter['from']->toDateString(), $quarter['to']->toDateString(), $quarter['months']]);
        $this->assertSame([2, 2, 3, 7], [$quarter['totals']['by_type']['annual'], $quarter['totals']['by_type']['medical'], $quarter['totals']['by_type']['training'], $quarter['totals']['days']]);

        $year = $this->printAs($this->manager, ['format' => 'leave', 'month' => '2026-10', 'period' => 'year'])
            ->assertOk()
            ->assertSee('2026 · 1 Jan – 31 Dec 2026')
            ->viewData('report');
        // All of Mei Ling's MC and Aisyah's February leave now count
        $this->assertSame([7, 4, 3, 14], [$year['totals']['by_type']['annual'], $year['totals']['by_type']['medical'], $year['totals']['by_type']['training'], $year['totals']['days']]);
        $this->assertCount(12, $year['calendar']);

        // An unknown period is the month
        $this->printAs($this->manager, ['format' => 'leave', 'period' => 'decade'])->assertOk()->assertViewHas('period', 'month');
    }

    public function test_requests_from_the_nurse_app_in_the_period_are_listed(): void
    {
        NurseRosterRequest::create(['ward_id' => $this->ward->id, 'nurse_id' => $this->meiLing->id, 'type' => 'leave', 'leave_type' => 'annual', 'start_date' => '2026-10-20', 'end_date' => '2026-10-21', 'note' => 'Family wedding', 'status' => 'pending']);
        NurseRosterRequest::create(['ward_id' => $this->ward->id, 'nurse_id' => $this->aisyah->id, 'type' => 'swap', 'shift_date' => '2026-10-15', 'shift' => 'AM', 'colleague_id' => $this->farah->id, 'status' => 'approved', 'decided_by_name' => 'Sister Wong', 'decided_at' => '2026-10-05 12:00:00']);
        NurseRosterRequest::create(['ward_id' => $this->ward->id, 'nurse_id' => $this->farah->id, 'type' => 'leave', 'leave_type' => 'training', 'start_date' => '2026-11-16', 'end_date' => '2026-11-16', 'status' => 'pending']);

        $response = $this->printAs($this->manager, ['format' => 'leave'])
            ->assertOk()
            ->assertSee('Annual leave, Tue 20 Oct to Wed 21 Oct')
            ->assertSee('Give away AM on Thu 15 Oct with Farah Idris')
            ->assertSee('Waiting for approval')
            ->assertSee('5 Oct 2026 by Sister Wong')
            ->assertDontSee('Training / course, Mon 16 Nov');
        $this->assertCount(2, $response->viewData('report')['requests']);

        $this->printAs($this->manager, ['format' => 'leave', 'period' => 'quarter'])->assertSee('Training / course, Mon 16 Nov');

        // Without the section the requests stay off the sheet
        $this->printAs($this->manager, ['format' => 'leave', 'sections' => ['entries']])->assertDontSee('Give away AM');
    }

    public function test_credentials_list_what_has_expired_or_runs_out_with_the_days(): void
    {
        $this->credentials();

        $response = $this->printAs($this->manager, ['format' => 'credentials'])
            ->assertOk()
            ->assertSee('As of 8 Oct 2026 · expiring within 90 days')
            ->assertSee('(American Nurses Association (2017), Defining staffing: workforce management, patient classification and acuity systems, pp. 3-4)', false);
        $report = $response->viewData('report');
        $actions = collect($report['actions'])->map(fn (array $action) => [$action['type'], $action['nurse'], $action['item'], $action['days']])->all();

        $this->assertContains(['expired', 'Aisyah Rahman', 'APC: Annual Practising Certificate 2026', -8], $actions);
        $this->assertContains(['expiring', 'Aisyah Rahman', 'Life support: Basic Life Support (BLS)', 60], $actions);
        $this->assertContains(['expiring', 'Farah Idris', 'APC: Annual Practising Certificate 2026', 84], $actions);
        $this->assertContains(['at_risk', 'Mei Ling', 'Adult advanced life support', null], $actions);
        $this->assertContains(['review_overdue', 'Mei Ling', 'Peripheral IV cannulation', -37], $actions);
        $this->assertContains(['unverified', 'Farah Idris', 'Registration: Registered Nurse', null], $actions);
        // Everyone needs BLS: the four without one are on the list, by name
        $this->assertSame(['Ah Chong', 'Farah Idris', 'Mei Ling', 'Siti Student'], collect($report['actions'])->where('type', 'missing')->pluck('nurse')->values()->all());
        $this->assertSame(['BLS'], collect($report['actions'])->where('type', 'missing')->pluck('item')->unique()->values()->all());
        // Most urgent first: what is wrong now before what is coming, then the paperwork
        $this->assertSame(['expired', 'missing', 'missing', 'missing', 'missing', 'at_risk', 'review_overdue', 'expiring', 'expiring', 'unverified'], array_column($report['actions'], 'type'));
        $response->assertSee('8 days ago')->assertSee('in 60 days')->assertSee('37 days overdue')->assertSee('everyone needs it')->assertSee('4 not on file');

        $nurses = collect($report['nurses'])->keyBy('name');
        $this->assertSame('expired', $nurses['Aisyah Rahman']['apc']['status']);
        $this->assertSame(['expiring', 60], [$nurses['Aisyah Rahman']['certificates']['bls']['status'], $nurses['Aisyah Rahman']['certificates']['bls']['days']]);
        $this->assertSame(['missing', true], [$nurses['Mei Ling']['certificates']['acls']['status'], $nurses['Mei Ling']['certificates']['acls']['needed']]);
        // A missing BLS is "Missing", not "not needed"
        $this->assertSame(['missing', true, false], [$nurses['Siti Student']['certificates']['bls']['status'], $nurses['Siti Student']['certificates']['bls']['needed'], $nurses['Siti Student']['certificates']['bls']['for_privilege']]);
        $this->assertSame([1, 1, 2], [$nurses['Mei Ling']['at_risk'], $nurses['Mei Ling']['review_overdue'], $nurses['Mei Ling']['privilege_counts']['granted']]);
        $this->assertSame(['bls', 'acls'], array_keys($report['certificates']));
        $this->assertSame(['nurses' => 5, 'registered' => 3, 'apc_valid' => 2, 'apc_valid_percent' => 67], array_intersect_key($report['summary'], array_flip(['nurses', 'registered', 'apc_valid', 'apc_valid_percent'])));
    }

    public function test_the_window_changes_what_counts_as_expiring(): void
    {
        $this->credentials();

        $expiring = fn (int $window) => collect($this->printAs($this->manager, ['format' => 'credentials', 'window' => $window])->assertOk()->viewData('report')['actions'])
            ->where('type', 'expiring')
            ->map(fn (array $action) => $action['nurse'] . ': ' . $action['item'])
            ->values()
            ->all();

        $this->assertSame(['Aisyah Rahman: Life support: Basic Life Support (BLS)', 'Farah Idris: APC: Annual Practising Certificate 2026'], $expiring(90));
        $this->assertSame(['Aisyah Rahman: Life support: Basic Life Support (BLS)'], $expiring(60));
        $this->assertSame([], $expiring(30));

        // The matrix agrees: at 30 days Farah's APC is simply valid
        $report = $this->printAs($this->manager, ['format' => 'credentials', 'window' => 30])->viewData('report');
        $this->assertSame('valid', collect($report['nurses'])->firstWhere('name', 'Farah Idris')['apc']['status']);
        $this->assertSame(30, $report['window']);

        // An unknown window is 90 days
        $this->printAs($this->manager, ['format' => 'credentials', 'window' => 45])->assertSee('expiring within 90 days');
    }

    public function test_the_credentials_report_shows_no_contact_details_numbers_or_notes(): void
    {
        $this->credentials();
        $tagging = $this->nurse('Nurul Orientation', 'nurul', ['is_tagging' => true]);

        $response = $this->printAs($this->manager, ['format' => 'credentials', 'sections' => ['matrix', 'actions', 'privileges']])
            ->assertOk()
            ->assertSee('Adult advanced life support')
            ->assertSee('Nurul Orientation')
            ->assertSee('orientation (tagging)');

        foreach (['012-555 0101', 'aisyah.rahman@example.test', 'REF-', 'Private note on file', 'Private privilege note', $this->aisyah->registration_number] as $private) {
            $response->assertDontSee($private);
        }

        // The tagging nurse is on the ward's list, and without an APC is something to act on
        $this->assertContains(['apc_missing', 'Nurul Orientation'], collect($response->viewData('report')['actions'])->map(fn (array $action) => [$action['type'], $action['nurse']])->all());
        $this->assertTrue(collect($response->viewData('report')['nurses'])->firstWhere('id', $tagging->id)['tagging']);
    }

    public function test_the_schedule_page_offers_the_reports_by_role(): void
    {
        $page = fn (User $user) => $this->actingAs($user)
            ->get(route('ward.ai-schedule', ['ward_id' => $this->ward->id, 'week' => self::WEEK, 'tab' => 'roster']))
            ->assertOk()
            ->assertSee('Print the AI Nurse Schedule')
            ->assertSee('Reports')
            ->assertSee('Leave &amp; holiday report', false)
            ->assertSee('rememberedNames', false);

        $page($this->manager)
            ->assertSee('Monthly productivity report')
            ->assertSee('Credentialing &amp; privileging report', false)
            ->assertSee('type="month"', false);

        foreach ([$this->nurseUser, User::factory()->create(['role' => User::ROLE_WARD_DASHBOARD])] as $user) {
            $page($user)
                ->assertDontSee('Monthly productivity report')
                ->assertDontSee('Credentialing &amp; privileging report', false)
                ->assertDontSee('sections.leave_notes', false)
                ->assertSee('The productivity and credentialing reports are for nurse managers and admins.');
        }
        $page($this->manager)->assertSee('sections.leave_notes', false)->assertSee('placeholder="YYYY-MM"', false);
    }

    public function test_the_footer_cites_ana_wherever_acuity_is_shown_and_calls_the_numbers_smartwards(): void
    {
        $line = 'Patient acuity and acuity-based staffing after the American Nurses Association (2017)';

        foreach (['acuity', 'assign', 'pack'] as $format) {
            $this->printAs($this->manager, ['format' => $format])->assertOk()->assertSee($line)->assertSee("the nurse numbers are SmartWard's own", false);
        }
        // The bed sheet cites it even with the staffing rows off: its care levels are acuity too
        $this->printAs($this->manager, ['format' => 'assign', 'sections' => ['legend']])->assertOk()->assertSee($line);
        // The roster cites it when its staffing rows show the nurses needed
        $this->printAs($this->manager, ['format' => 'roster'])->assertSee($line);
        $this->printAs($this->manager, ['format' => 'roster', 'sections' => ['legend']])->assertDontSee($line);

        foreach (['workload', 'nurse', 'leave'] as $format) {
            $this->printAs($this->manager, ['format' => $format])->assertOk()->assertDontSee('American Nurses Association (2017)');
        }
    }

    public function test_signature_lines_and_black_and_white_work_on_the_reports(): void
    {
        $this->printAs($this->manager, ['format' => 'leave', 'sections' => ['entries', 'signature'], 'mono' => 1])
            ->assertOk()
            ->assertSee('Prepared by')
            ->assertSee('class="sheet mx-auto my-4 bg-white shadow ro-mono"', false)
            ->assertSee('.rp-tile', false);

        $this->printAs($this->manager, ['format' => 'credentials'])->assertOk()->assertDontSee('Prepared by');
    }
}
