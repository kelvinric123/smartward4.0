<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Hospital;
use App\Models\Nurse;
use App\Models\NurseLeave;
use App\Models\NurseRosterEntry;
use App\Models\Patient;
use App\Models\PublicHoliday;
use App\Models\ShiftSetting;
use App\Models\User;
use App\Models\Ward;
use App\Models\WardScheduleAssignment;
use App\Services\NurseScheduling\ScheduleSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * AI Nurse Schedule printouts: every format for managers and nurses, the
 * options checked on the server, patient names masked as chosen, and the
 * read-only snapshot behind them (which the view-only link reuses).
 *
 * Mon 5 Oct 2026, 10:00 (AM shift). Aisyah AM Mon, PM Tue, OFF Wed, ON Thu;
 * Mei Ling PM Mon, AM Tue; Farah AM Mon and on medical leave Wed. Beds: AM
 * Mon B1, B2 Aisyah and B3 Farah; PM Tue all three Aisyah.
 */
class AiNurseSchedulePrintTest extends TestCase
{
    use RefreshDatabase;

    private const WEEK = '2026-10-05';

    private const FORMATS = ['roster', 'nurse', 'assign', 'acuity', 'workload', 'pack'];

    private Ward $ward;
    private array $beds = [];
    private Nurse $aisyah;
    private Nurse $meiLing;
    private Nurse $farah;
    private User $manager;
    private User $nurseUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));

        $hospital = Hospital::create(['name' => 'Test Hospital']);
        $this->ward = Ward::create(['hospital_id' => $hospital->id, 'ward_code' => 'W6', 'ward_name' => 'Ward 6', 'is_active' => true]);
        foreach (ShiftSetting::getDefaults() as $shift) {
            ShiftSetting::create($shift + ['ward_id' => $this->ward->id, 'is_active' => true]);
        }
        foreach (['B1', 'B2', 'B3'] as $number) {
            $this->beds[$number] = Bed::create(['ward_id' => $this->ward->id, 'bed_number' => $number, 'bed_id' => 'W6-' . $number, 'bed_display_name' => $number, 'status' => 'occupied', 'is_active' => true]);
        }

        $this->aisyah = $this->nurse('Aisyah Rahman', 'aisyah');
        $this->meiLing = $this->nurse('Mei Ling', 'meiling');
        $this->farah = $this->nurse('Farah Idris', 'farah');
        $this->manager = User::factory()->create(['name' => 'Sister Wong']);
        $this->nurseUser = User::factory()->create(['name' => 'Staff Nurse Lim', 'role' => User::ROLE_NURSE]);

        $this->patient('B1', 'Siti Aminah', ['nursing_level' => 'level_3', 'isolation_type' => 'contact']);
        $this->patient('B2', 'Tan Ah Kow');
        $this->patient('B3', 'Rajesh Kumar', ['fall_risk' => '1']);

        $this->roster($this->aisyah, ['2026-10-05' => 'AM', '2026-10-06' => 'PM', '2026-10-07' => 'OFF', '2026-10-08' => 'ON', '2026-10-12' => 'AM']);
        $this->roster($this->meiLing, ['2026-10-05' => 'PM', '2026-10-06' => 'AM']);
        $this->roster($this->farah, ['2026-10-05' => 'AM']);
        $this->bedsFor('2026-10-05', 'AM', ['B1' => $this->aisyah, 'B2' => $this->aisyah, 'B3' => $this->farah]);
        $this->bedsFor('2026-10-06', 'PM', ['B1' => $this->aisyah, 'B2' => $this->aisyah, 'B3' => $this->aisyah]);
        NurseLeave::create(['nurse_id' => $this->farah->id, 'type' => 'medical', 'start_date' => '2026-10-07', 'end_date' => '2026-10-07', 'note' => 'Fever']);
        PublicHoliday::create(['holiday_date' => '2026-10-09', 'name' => 'Founders Day']);
    }

    private function nurse(string $name, string $username): Nurse
    {
        return Nurse::create([
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

    private function roster(Nurse $nurse, array $days): void
    {
        foreach ($days as $date => $shift) {
            NurseRosterEntry::create(['ward_id' => $this->ward->id, 'nurse_id' => $nurse->id, 'roster_date' => $date, 'shift' => $shift, 'source' => NurseRosterEntry::SOURCE_AUTO]);
        }
    }

    private function bedsFor(string $date, string $shift, array $map): void
    {
        foreach ($map as $bed => $nurse) {
            WardScheduleAssignment::create(['ward_id' => $this->ward->id, 'bed_id' => $this->beds[$bed]->id, 'nurse_id' => $nurse->id, 'scheduled_date' => $date, 'shift' => $shift]);
        }
    }

    private function printUrl(array $query = []): string
    {
        return route('ward.ai-schedule.print', $query + ['ward_id' => $this->ward->id, 'week' => self::WEEK]);
    }

    private function printAs(User $user, array $query = [])
    {
        return $this->actingAs($user)->get($this->printUrl($query));
    }

    public function test_every_format_prints_for_a_manager_and_for_a_nurse(): void
    {
        $labels = [
            'roster' => 'Weekly roster',
            'nurse' => 'Duty list per nurse',
            'assign' => 'Bed assignment',
            'acuity' => 'Patient acuity &amp; staffing',
            'workload' => 'Workload &amp; fairness',
            'pack' => 'Full pack',
        ];

        foreach ([$this->manager, $this->nurseUser] as $user) {
            foreach (self::FORMATS as $format) {
                $this->printAs($user, ['format' => $format])
                    ->assertOk()
                    ->assertViewHas('format', $format)
                    ->assertSee($labels[$format] . ': Ward 6', false)
                    ->assertSee('Test Hospital')
                    ->assertSee('Printed 05 Oct 2026, 10:00')
                    ->assertSee('by ' . $user->name)
                    ->assertSee('SmartWard AI Nurse Schedule');
            }
        }

        // The pack has every part, each from a new page
        $this->printAs($this->manager, ['format' => 'pack'])
            ->assertSeeInOrder(['Weekly roster', 'Patient acuity &amp; staffing', 'Bed assignment', 'Workload &amp; fairness', 'Method and references'], false);
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get($this->printUrl(['format' => 'assign']))->assertRedirect(route('login'));
    }

    public function test_unknown_values_fall_back_to_the_defaults(): void
    {
        $this->printAs($this->manager, [
            'format' => 'everything', 'paper' => 'B5', 'orientation' => 'sideways', 'names' => 'everyone',
            'size' => 'huge', 'weeks' => 3, 'day' => '2027-01-01', 'shift' => 'XX', 'mono' => 'maybe',
        ])
            ->assertOk()
            ->assertViewHas('format', 'roster')
            ->assertViewHas('paper', 'A4')
            ->assertViewHas('orientation', 'landscape')
            ->assertViewHas('orientationChoice', 'auto')
            ->assertViewHas('names', 'initials')
            ->assertViewHas('size', 'normal')
            ->assertViewHas('mono', false)
            ->assertViewHas('sections', ['staffing', 'legend', 'leave_detail'])
            ->assertViewHas('snapshot', fn (array $snapshot) => count($snapshot['weeks']) === 1)
            ->assertSee('size: A4 landscape;', false);

        // Unknown sections are dropped, and so are those the format does not have
        $this->printAs($this->manager, ['format' => 'roster', 'sections' => ['legend', 'evil', 'references', ['nested']]])
            ->assertOk()
            ->assertViewHas('sections', ['legend']);

        // "none" switches every section off rather than bringing back the defaults
        $this->printAs($this->manager, ['format' => 'roster', 'sections' => ['none']])
            ->assertOk()
            ->assertViewHas('sections', []);

        // An array where a word belongs, an unknown ward and a nonsense week: still the defaults
        $this->printAs($this->manager, ['format' => ['assign'], 'ward_id' => 99999, 'week' => 'not a date'])
            ->assertOk()
            ->assertViewHas('format', 'roster')
            ->assertViewHas('snapshot', fn (array $snapshot) => $snapshot['ward']['id'] === $this->ward->id
                && $snapshot['from']->toDateString() === self::WEEK);

        // The bed sheet for a day outside the week is the shift on now
        $this->printAs($this->manager, ['format' => 'assign', 'day' => '2026-12-25', 'shift' => 'PM', 'weeks' => 4])
            ->assertOk()
            ->assertViewHas('snapshot', fn (array $snapshot) => $snapshot['assign']['day'] === '2026-10-05'
                && $snapshot['assign']['shift'] === 'PM'
                && count($snapshot['weeks']) === 1);
    }

    public function test_the_page_size_follows_the_paper_and_orientation(): void
    {
        $this->printAs($this->manager, ['paper' => 'A3', 'orientation' => 'portrait'])->assertSee('size: A3 portrait;', false);
        $this->printAs($this->manager, ['format' => 'acuity', 'paper' => 'Letter'])->assertSee('size: Letter portrait;', false);
        $this->printAs($this->manager, ['format' => 'nurse', 'paper' => 'Legal', 'orientation' => 'landscape'])->assertSee('size: Legal landscape;', false);
        $this->printAs($this->manager, ['format' => 'workload'])->assertSee('size: A4 landscape;', false);

        // The quick switches keep every other choice
        $response = $this->printAs($this->manager, ['format' => 'acuity', 'names' => 'hidden', 'sections' => ['reasons'], 'size' => 'large']);
        $query = $response->viewData('query');
        $this->assertSame(['acuity', 'hidden', ['reasons'], 'large'], [$query['format'], $query['names'], $query['sections'], $query['size']]);
        $response->assertSee(e(route('ward.ai-schedule.print', array_merge($query, ['paper' => 'A3']))), false)
            ->assertSee(e(route('ward.ai-schedule.print', array_merge($query, ['orientation' => 'landscape']))), false);
    }

    public function test_hidden_names_show_no_patient_at_all(): void
    {
        foreach (['assign', 'acuity', 'pack'] as $format) {
            $this->printAs($this->manager, ['format' => $format, 'names' => 'hidden'])
                ->assertOk()
                ->assertSee('Patients: bed only')
                ->assertSee('B1')
                ->assertDontSee('Siti Aminah')
                ->assertDontSee('S.A.')
                ->assertDontSee('T.A.K.')
                ->assertDontSee('MRN-B1');
        }
    }

    public function test_initials_show_initials_and_never_the_full_name(): void
    {
        foreach (['assign', 'acuity', 'pack'] as $format) {
            $this->printAs($this->manager, ['format' => $format])
                ->assertOk()
                ->assertSee('S.A.')
                ->assertSee('T.A.K.')
                ->assertSee('R.K.')
                ->assertDontSee('Siti Aminah')
                ->assertDontSee('Tan Ah Kow')
                ->assertDontSee('MRN-B1');
        }

        // Full names come with the MRN, and a reminder to keep the sheet on the ward
        $this->printAs($this->manager, ['format' => 'assign', 'names' => 'full'])
            ->assertSee('Siti Aminah')
            ->assertSee('MRN-B1')
            ->assertSee('Confidential: for ward use only');
    }

    public function test_the_bed_sheet_shows_each_beds_nurse_on_the_chosen_shift(): void
    {
        $nurses = fn ($response) => collect($response->viewData('snapshot')['assign']['rows'])->pluck('nurse', 'bed')->all();

        // The shift on now by default: Monday AM
        $now = $this->printAs($this->manager, ['format' => 'assign'])
            ->assertOk()
            ->assertSee('AM &middot; Monday 5 Oct 2026', false)
            ->assertSee('07:00 - 14:00 (on now)');
        $this->assertSame(['B1' => 'Aisyah Rahman', 'B2' => 'Aisyah Rahman', 'B3' => 'Farah Idris'], $nurses($now));

        $tuesday = $this->printAs($this->manager, ['format' => 'assign', 'day' => '2026-10-06', 'shift' => 'PM'])
            ->assertOk()
            ->assertSee('PM &middot; Tuesday 6 Oct 2026', false)
            ->assertSee('Aisyah Rahman')
            ->assertSee('Handover notes');
        $this->assertSame(['B1' => 'Aisyah Rahman', 'B2' => 'Aisyah Rahman', 'B3' => 'Aisyah Rahman'], $nurses($tuesday));
        $loads = $tuesday->viewData('snapshot')['assign']['loads'];
        $this->assertSame([['Aisyah Rahman', ['B1', 'B2', 'B3'], 3]], array_map(fn ($load) => [$load['name'], $load['beds'], $load['patients']], $loads));

        // Nobody on the shift: every bed shows it has no nurse
        $empty = $this->printAs($this->manager, ['format' => 'assign', 'day' => '2026-10-10', 'shift' => 'ON'])
            ->assertOk()
            ->assertSee('No nurse')
            ->assertSee('Nobody is rostered on this shift and no beds are assigned.');
        $this->assertSame(3, $empty->viewData('snapshot')['assign']['unassigned']);
    }

    public function test_two_weeks_print_two_week_tables(): void
    {
        $response = $this->printAs($this->manager, ['format' => 'roster', 'weeks' => 2])
            ->assertOk()
            ->assertSee('data-week="2026-10-05"', false)
            ->assertSee('data-week="2026-10-12"', false)
            ->assertSee('Week 41')
            ->assertSee('Week 42')
            ->assertSee('2 weeks, 5 Oct – 18 Oct 2026');
        $this->assertSame(2, substr_count($response->getContent(), 'data-week="'));

        // Every nurse's duty list covers both weeks; workload adds the totals
        $this->printAs($this->manager, ['format' => 'nurse', 'weeks' => 2])->assertOk()->assertSee('Week of');
        $this->printAs($this->manager, ['format' => 'workload', 'weeks' => 4])->assertOk()->assertSee('All 4 weeks');

        $one = $this->printAs($this->manager, ['format' => 'roster']);
        $this->assertSame(1, substr_count($one->getContent(), 'data-week="'));
    }

    public function test_the_references_section_shows_the_ana_citation(): void
    {
        $this->printAs($this->manager, ['format' => 'acuity'])
            ->assertOk()
            ->assertSee('Method: patient classification and acuity-based staffing (ANA, 2017).')
            ->assertSee('id="acuity-references"', false)
            ->assertSee('Method and references')
            ->assertSee('Silver Spring, MD: American Nurses Association.')
            ->assertSee('Patient acuity and acuity-based staffing after the American Nurses Association (2017)');

        $this->printAs($this->manager, ['format' => 'acuity', 'sections' => ['staffing', 'reasons']])
            ->assertOk()
            ->assertDontSee('id="acuity-references"', false)
            ->assertDontSee('Silver Spring, MD')
            ->assertSee(\App\Support\AcuityReferences::DISCLAIMER);

        // No acuity in the printout, no citation in the footer
        $this->printAs($this->manager, ['format' => 'workload'])
            ->assertDontSee('after the American Nurses Association');
    }

    public function test_the_acuity_printout_shows_levels_staffing_and_reasons(): void
    {
        $response = $this->printAs($this->manager, ['format' => 'acuity'])
            ->assertOk()
            ->assertSee('Staffing by patient acuity')
            ->assertSee('Registered nurses needed')
            ->assertSee('Level 3 Maximum')
            ->assertSee('Isolation')
            ->assertSee('High fall risk');

        $rows = collect($response->viewData('snapshot')['acuity']['rows'])->keyBy('bed');
        // B1 is rated Level 3; isolation alone suggests Level 2, so the rating is above the indicators
        $this->assertSame([3, 2, 'review_down'], [$rows['B1']['level'], $rows['B1']['suggested'], $rows['B1']['status']]);
        $this->assertSame([2, 'unclassified'], [$rows['B3']['suggested'], $rows['B3']['status']]);

        // Without reasons, the indicators stay off the sheet
        $this->printAs($this->manager, ['format' => 'acuity', 'sections' => ['staffing']])
            ->assertOk()
            ->assertDontSee('High fall risk');
    }

    public function test_leave_detail_off_hides_the_mc_code(): void
    {
        $this->printAs($this->manager, ['format' => 'roster'])
            ->assertOk()
            ->assertSee('ro-chip-leave bg-rose-100 text-rose-800 border-rose-200">MC</span>', false)
            ->assertSee('Medical leave');

        foreach (['roster', 'nurse'] as $format) {
            $this->printAs($this->manager, ['format' => $format, 'sections' => ['staffing', 'legend']])
                ->assertOk()
                ->assertDontSee('>MC</span>', false)
                ->assertDontSee('Medical leave')
                ->assertSee('>Leave</span>', false);
        }
    }

    public function test_black_and_white_and_printing_straight_away(): void
    {
        $this->printAs($this->manager, ['mono' => 1, 'autoprint' => 1])
            ->assertOk()
            ->assertSee('ro-mono')
            ->assertSee('setTimeout(() => window.print(), 400)', false);

        $this->printAs($this->manager)
            ->assertDontSee('class="sheet mx-auto my-4 bg-white shadow ro-mono"', false)
            ->assertDontSee('setTimeout(() => window.print(), 400)', false);
    }

    public function test_a_public_snapshot_never_carries_names_mrns_leave_types_or_reasons(): void
    {
        $snapshot = ScheduleSnapshot::build($this->ward, Carbon::parse(self::WEEK), ['public' => true, 'names' => 'full', 'leave_detail' => true]);

        $this->assertSame(['initials', false, true], [$snapshot['names'], $snapshot['leave_detail'], $snapshot['public']]);
        $rows = collect($snapshot['acuity']['rows']);
        $this->assertSame(['S.A.', 'T.A.K.', 'R.K.'], $rows->pluck('patient')->all());
        $this->assertSame([null, null, null], $rows->pluck('mrn')->all());
        $this->assertSame([[], [], []], $rows->pluck('reasons')->all());
        $this->assertSame([[], [], []], array_column($snapshot['assign']['rows'], 'needs'));

        $farah = collect($snapshot['weeks'][0]['nurses'])->firstWhere('name', 'Farah Idris');
        $this->assertSame(['code' => 'Leave', 'label' => 'Leave', 'type' => null], $farah['days']['2026-10-07']['leave']);
        $this->assertStringNotContainsString('Fever', json_encode($snapshot));
        $this->assertStringNotContainsString('Medical', json_encode($snapshot));

        // Bed only: no patient label anywhere
        $hidden = ScheduleSnapshot::build($this->ward, Carbon::parse(self::WEEK), ['public' => true, 'names' => 'hidden']);
        $this->assertSame([null, null, null], array_column($hidden['assign']['rows'], 'patient'));
        $this->assertStringNotContainsString('Siti', json_encode($hidden));
    }

    public function test_the_schedule_page_shows_the_print_dialog_for_managers_and_nurses(): void
    {
        foreach ([$this->manager, $this->nurseUser] as $user) {
            $this->actingAs($user)
                ->get(route('ward.ai-schedule', ['ward_id' => $this->ward->id, 'week' => self::WEEK, 'tab' => 'assign']))
                ->assertOk()
                ->assertSee('aiSchedulePrint(', false)
                ->assertSee('Print the AI Nurse Schedule')
                ->assertSee('Duty list per nurse')
                ->assertSee('Full names and MRN')
                ->assertSee('ward-schedule\\\\\/ai\\\\\/print', false);
        }
    }
}
