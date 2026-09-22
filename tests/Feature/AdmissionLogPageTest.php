<?php

namespace Tests\Feature;

use App\Models\AdmissionLog;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdmissionLogPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Ward $ward;
    private Ward $otherWard;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => 'Sister Rohana']);
        $hospital = Hospital::create(['name' => 'Pantai Test Hospital']);
        $this->ward = Ward::create(['hospital_id' => $hospital->id, 'ward_code' => 'D6', 'ward_name' => 'Ward D6', 'is_active' => true]);
        $this->otherWard = Ward::create(['hospital_id' => $hospital->id, 'ward_code' => 'D7', 'ward_name' => 'Ward D7', 'is_active' => true]);
        $this->patient = Patient::create([
            'name' => 'Placeholder', 'mrn' => 'MRN-P', 'rn' => 'RN-P', 'ic_passport' => '900101-01-0101',
            'age' => 40, 'gender' => 'Male', 'phone' => '012-0000000',
        ]);
    }

    private function log(string $action, string $name, string $at, array $extra = []): AdmissionLog
    {
        $this->travelTo(Carbon::parse($at));

        return AdmissionLog::create(array_merge([
            'patient_id' => $this->patient->id,
            'ward_id' => $this->ward->id,
            'user_id' => $this->user->id,
            'bed_number' => 'D610',
            'action' => $action,
            'patient_name' => $name,
            'mrn' => 'MRN-' . strtoupper(substr(md5($name), 0, 6)),
            'consultant_name' => 'Dr. Tan Wei Liang',
            'notes' => 'Logged for ' . $name,
            'source' => 'manual',
        ], $extra));
    }

    private function seedMixedLogs(): void
    {
        $this->log('admit', 'Aminah Yusof', '2026-09-01 10:00');
        $this->log('admit', 'Bala Krishnan', '2026-09-03 09:15', ['source' => 'adt', 'user_id' => null]);
        $this->log('discharge', 'Chong Mei Ling', '2026-09-05 23:30');
        $this->log('prebook-pending', 'Dewi Sartika', '2026-09-06 00:10');
        $this->log('admit', 'Elan Muthu', '2026-09-04 12:00', ['ward_id' => $this->otherWard->id]);
    }

    public function test_page_shows_status_chips_and_every_column_can_be_shown(): void
    {
        $this->seedMixedLogs();

        $response = $this->actingAs($this->user)->get(route('ward.admission-logs', ['ward_id' => $this->ward->id]))
            ->assertOk()
            ->assertSee('4 records')
            ->assertSee('Prebook (bed pending)');

        // Every column is on the page; the ones hidden by default start hidden
        foreach (AdmissionLog::COLUMNS as $key => [$label]) {
            $response->assertSee('data-col="' . $key . '"', false)->assertSee($label);
        }
        $response->assertSee('hide-nurse', false)->assertSee('hide-age', false);
        $this->assertStringNotContainsString('hide-patient', $this->hiddenClassesOf($response->getContent()));

        $response->assertSee('System (ADT)')->assertSee('Sister Rohana');
    }

    public function test_status_filter_takes_several_statuses_and_the_old_single_action(): void
    {
        $this->seedMixedLogs();
        $page = fn (array $query) => $this->actingAs($this->user)
            ->get(route('ward.admission-logs', ['ward_id' => $this->ward->id] + $query));

        $page(['statuses' => ['admit', 'discharge']])
            ->assertSee('Aminah Yusof')->assertSee('Chong Mei Ling')->assertDontSee('Dewi Sartika');

        $page(['action' => 'discharge'])
            ->assertSee('Chong Mei Ling')->assertDontSee('Aminah Yusof');
    }

    public function test_date_range_includes_the_whole_last_day(): void
    {
        $this->seedMixedLogs();

        $this->actingAs($this->user)
            ->get(route('ward.admission-logs', ['ward_id' => $this->ward->id, 'from_date' => '2026-09-01', 'to_date' => '2026-09-05']))
            ->assertSee('Aminah Yusof')
            ->assertSee('Chong Mei Ling') // 23:30 on the last day
            ->assertDontSee('Dewi Sartika'); // 00:10 the next day
    }

    public function test_summary_counts_what_the_print_would_contain(): void
    {
        $this->seedMixedLogs();

        $this->actingAs($this->user)
            ->getJson(route('ward.admission-logs.summary', [
                'ward_id' => $this->ward->id,
                'statuses' => ['admit'],
                'from_date' => '2026-09-01',
                'to_date' => '2026-09-05',
            ]))
            ->assertOk()
            ->assertJsonPath('total', 2)
            // Per status counts ignore the status choice, so every tick box shows its count
            ->assertJsonPath('by_status.admit', 2)
            ->assertJsonPath('by_status.discharge', 1)
            ->assertJsonPath('by_status.prebook-pending', 0)
            ->assertJsonPath('max', \App\Http\Controllers\AdmissionLogController::MAX_PRINT_ROWS);
    }

    public function test_print_has_every_matching_record_not_just_one_page(): void
    {
        foreach (range(1, 60) as $i) {
            $this->log($i % 3 ? 'admit' : 'discharge', sprintf('Patient %02d', $i),
                Carbon::parse('2026-09-10 08:00')->addMinutes($i)->toDateTimeString());
        }
        $this->log('admit', 'Outside Range', '2026-09-12 08:00');

        $html = $this->actingAs($this->user)
            ->get(route('ward.admission-logs.print', [
                'ward_id' => $this->ward->id,
                'from_date' => '2026-09-10',
                'to_date' => '2026-09-10',
                'columns' => ['time', 'patient', 'action'],
            ]))
            ->assertOk()
            ->assertSee('Admission Log Report')
            ->assertSee('Pantai Test Hospital')
            ->assertSee('Ward D6')
            ->assertSee('10 Sep 2026')
            ->assertSee('All statuses')
            ->assertDontSee('Outside Range')
            ->assertSee('<th>Patient</th>', false)
            ->assertDontSee('<th>Consultant</th>', false)
            ->getContent();

        $this->assertSame(60, substr_count($html, 'data-col="patient"'));
        // Oldest first by default
        $this->assertLessThan(strpos($html, 'Patient 60'), strpos($html, 'Patient 01'));
    }

    public function test_print_can_pick_statuses_group_by_status_and_sort_newest_first(): void
    {
        $this->seedMixedLogs();

        $html = $this->actingAs($this->user)
            ->get(route('ward.admission-logs.print', [
                'ward_id' => $this->ward->id,
                'statuses' => ['admit', 'discharge'],
                'group' => 'status',
                'sort' => 'desc',
                'orientation' => 'portrait',
            ]))
            ->assertOk()
            ->assertSee('Admit, Discharge')
            ->assertSee('size: A4 portrait', false)
            ->assertDontSee('Dewi Sartika')
            ->getContent();

        // Admit section before Discharge, newest admit first within it
        $this->assertLessThan(strpos($html, 'Chong Mei Ling'), strpos($html, 'Aminah Yusof'));
        $this->assertLessThan(strpos($html, 'Aminah Yusof'), strpos($html, 'Bala Krishnan'));
    }

    public function test_print_ignores_unknown_columns_and_keeps_the_usual_order(): void
    {
        $this->seedMixedLogs();

        $html = $this->actingAs($this->user)
            ->get(route('ward.admission-logs.print', ['columns' => ['notes', 'hack', 'time']]))
            ->assertOk()
            ->assertDontSee('hack')
            ->getContent();

        $this->assertLessThan(strpos($html, '<th>Notes</th>'), strpos($html, '<th>Date &amp; time</th>'));
    }

    public function test_pages_need_a_login(): void
    {
        $this->get(route('ward.admission-logs'))->assertRedirect(route('login'));
        $this->get(route('ward.admission-logs.print'))->assertRedirect(route('login'));
    }

    private function hiddenClassesOf(string $html): string
    {
        preg_match('/class="flex-1 min-h-0 overflow-auto[^"]*"/', $html, $match);

        return $match[0] ?? '';
    }
}
