<?php

namespace Tests\Feature;

use App\Http\Controllers\AiNurseScheduleShareController;
use App\Models\Bed;
use App\Models\Hospital;
use App\Models\Nurse;
use App\Models\NurseLeave;
use App\Models\NurseRosterEntry;
use App\Models\Patient;
use App\Models\PublicHoliday;
use App\Models\ShiftSetting;
use App\Models\User;
use App\Models\UserActivity;
use App\Models\Ward;
use App\Models\WardRosterShareLink;
use App\Models\WardScheduleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * View-only links to the AI Nurse Schedule: made and revoked by managers only,
 * opened without signing in, looked up by the token's hash, and showing a
 * public copy (patients by bed or initials, no MRN, leave without its type).
 *
 * Mon 5 Oct 2026, 10:00 (AM shift). Aisyah AM Mon, PM Tue, OFF Wed, ON Thu
 * and AM next Monday; Mei Ling PM Mon, AM Tue; Farah AM Mon and on medical
 * leave Wed. Beds: AM Mon B1, B2 Aisyah and B3 Farah; PM Tue all three Aisyah.
 */
class AiNurseScheduleShareTest extends TestCase
{
    use RefreshDatabase;

    private const WEEK = '2026-10-05';

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

    /** Make a link straight in the database: [link, token]. */
    private function link(array $attributes = [], ?Ward $ward = null): array
    {
        return WardRosterShareLink::issue($ward ?? $this->ward, $attributes + [
            'label' => null,
            'sections' => ['roster'],
            'week_mode' => WardRosterShareLink::WEEK_ROLLING,
            'week_start' => null,
            'names' => 'hidden',
            'expires_at' => now()->addDays(7),
            'created_by' => $this->manager->id,
            'created_by_name' => $this->manager->name,
        ]);
    }

    /** The Share dialog's create form, as posted. */
    private function share(User $user, array $fields = []): TestResponse
    {
        return $this->actingAs($user)->post(route('ward.ai-schedule.shares.store'), $fields + [
            'ward_id' => $this->ward->id,
            'week' => self::WEEK,
            'tab' => 'assign',
            'label' => 'Ward 6 staff chat',
            'sections' => ['assign', 'roster'],
            'week_mode' => 'rolling',
            'expires_in' => 7,
            'names' => 'hidden',
        ]);
    }

    /** Open a link as whoever is there: nobody, unless a test signs in. */
    private function open(string $token, array $query = [], array $headers = []): TestResponse
    {
        return $this->withHeaders($headers)->get(route('ward.ai-schedule.shared', ['token' => $token] + $query));
    }

    private function schedulePage(User $user): TestResponse
    {
        return $this->actingAs($user)->get(route('ward.ai-schedule', ['ward_id' => $this->ward->id, 'week' => self::WEEK, 'tab' => 'roster']));
    }

    public function test_a_manager_creates_a_link_and_only_its_hash_is_looked_up(): void
    {
        $response = $this->share($this->manager);

        $link = WardRosterShareLink::sole();
        $response->assertRedirect(route('ward.ai-schedule', ['ward_id' => $this->ward->id, 'week' => self::WEEK, 'tab' => 'assign']))
            ->assertSessionHas('share_created', $link->id)
            ->assertSessionHas('success', 'View-only link created for Ward 6, open until Mon 12 Oct 2026, 10:00. Copy it or show its QR code from the Share dialog.');

        // Stored in the shared page's order, open for the days chosen, for the current week
        $this->assertSame(['roster', 'assign'], $link->sections);
        $this->assertSame('Ward 6 staff chat', $link->label);
        $this->assertSame('rolling', $link->week_mode);
        $this->assertNull($link->week_start);
        $this->assertSame('hidden', $link->names);
        $this->assertSame('2026-10-12 10:00:00', $link->expires_at->toDateTimeString());
        $this->assertSame($this->manager->id, $link->created_by);
        $this->assertSame('Sister Wong', $link->created_by_name);
        $this->assertSame(0, $link->view_count);

        // The token: letters and digits, found by its hash, and kept only encrypted
        $token = $link->plainToken();
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{48}$/', $token);
        $this->assertSame(hash('sha256', $token), $link->token_hash);
        $this->assertSame(route('ward.ai-schedule.shared', $token), $link->url());
        $row = (array) DB::table('ward_roster_share_links')->where('id', $link->id)->first();
        $this->assertSame($token, Crypt::decryptString($row['token']));
        foreach ($row as $column => $value) {
            $this->assertStringNotContainsString($token, (string) $value, "The token is readable in the {$column} column");
        }
        $this->assertArrayNotHasKey('token', $link->toArray());
        $this->assertArrayNotHasKey('token_hash', $link->toArray());

        // Shift settings log their own activity; this is the link's
        $activity = UserActivity::where('activity_type', 'roster_share')->sole();
        $this->assertSame($this->manager->id, $activity->user_id);
        $this->assertSame($link->id, $activity->properties['share_link_id']);
        $this->assertSame('Created a view-only link to the Ward 6 AI Nurse Schedule: Ward 6 staff chat', $activity->description);

        // A link for one week keeps the Monday of the week the page showed
        $this->share($this->manager, ['week' => '2026-10-07', 'week_mode' => 'fixed', 'label' => '', 'expires_in' => 1, 'names' => 'initials']);
        $fixed = WardRosterShareLink::latest('id')->first();
        $this->assertSame('fixed', $fixed->week_mode);
        $this->assertSame('2026-10-05', $fixed->week_start->toDateString());
        $this->assertNull($fixed->label);
        $this->assertSame('View-only link', $fixed->title());
        $this->assertSame('2026-10-06 10:00:00', $fixed->expires_at->toDateTimeString());
    }

    public function test_the_create_form_takes_only_the_choices_offered(): void
    {
        $this->share($this->manager, ['sections' => []])->assertSessionHasErrors('sections');
        $this->share($this->manager, ['sections' => ['roster', 'leave']])->assertSessionHasErrors('sections.1');
        // Full names never go on a link
        $this->share($this->manager, ['names' => 'full'])->assertSessionHasErrors('names');
        $this->share($this->manager, ['expires_in' => 3])->assertSessionHasErrors('expires_in');
        $this->share($this->manager, ['week_mode' => 'monthly'])->assertSessionHasErrors('week_mode');
        $this->share($this->manager, ['label' => str_repeat('a', 101)])->assertSessionHasErrors('label');

        $this->assertSame(0, WardRosterShareLink::count());
    }

    public function test_a_nurse_can_neither_create_nor_revoke_a_link(): void
    {
        $this->share($this->nurseUser)->assertForbidden();
        $this->assertSame(0, WardRosterShareLink::count());

        [$link] = $this->link();
        $this->actingAs($this->nurseUser)
            ->post(route('ward.ai-schedule.shares.revoke', $link), ['week' => self::WEEK])
            ->assertForbidden();
        $this->assertNull($link->fresh()->revoked_at);
    }

    public function test_anyone_with_the_link_sees_the_schedule_without_signing_in(): void
    {
        [, $token] = $this->link(['sections' => array_keys(WardRosterShareLink::SECTIONS), 'label' => 'Ward 6 staff chat']);
        $pageUrl = route('ward.ai-schedule.shared', $token);

        $response = $this->open($token)
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
            ->assertSee('Ward 6: AI Nurse Schedule')
            ->assertSee('Test Hospital')
            ->assertSee('Ward 6 staff chat')
            ->assertSee('View only')
            ->assertSee('shared by Sister Wong')
            ->assertSee('link expires Mon 12 Oct 2026, 10:00')
            ->assertSee('Mon 5 Oct &ndash; Sun 11 Oct 2026', false)
            ->assertSee('Aisyah Rahman')
            ->assertSee('Founders Day');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertGuest();

        // Every section the link shows, as a tab
        foreach (WardRosterShareLink::SECTIONS as $key => $section) {
            $response->assertSee('id="section-' . $key . '"', false)->assertSee($section['label']);
        }

        // Nothing links into the app: only the page itself, its own anchors and the built assets
        preg_match_all('/(?<![:\w-])(?:href|action|src)="([^"]*)"/', $response->getContent(), $matches);
        $this->assertNotEmpty($matches[1]);
        foreach ($matches[1] as $target) {
            $this->assertTrue(
                str_starts_with($target, '#') || str_starts_with($target, $pageUrl) || str_contains($target, '/build/assets/'),
                'The shared page links into the app: ' . $target
            );
        }
        $response->assertDontSee(route('login'), false)->assertDontSee('/ward-schedule/', false);
    }

    public function test_a_link_by_bed_only_shows_no_patient_names_mrns_or_care_needs(): void
    {
        [, $token] = $this->link(['sections' => ['assign', 'acuity'], 'names' => 'hidden']);

        $response = $this->open($token)->assertOk()
            ->assertSee('B1')
            ->assertSee('Aisyah Rahman')
            ->assertSee('Farah Idris');

        foreach (['Siti Aminah', 'S.A.', 'Tan Ah Kow', 'T.A.K.', 'Rajesh Kumar', 'R.K.', 'MRN-B1', 'MRN-B2', 'IC-B1', 'RN-B1'] as $private) {
            $response->assertDontSee($private);
        }
        // Neither the care needs on the bed sheet nor the indicators behind each level
        $response->assertDontSee('Care needs')
            ->assertDontSee('<th class="text-left">Indicators</th>', false)
            ->assertDontSee('<th class="text-left">Patient</th>', false);
    }

    public function test_a_link_by_initials_shows_initials_only(): void
    {
        [$link, $token] = $this->link(['sections' => ['assign', 'acuity'], 'names' => 'initials']);

        $this->open($token)->assertOk()
            ->assertSee('S.A.')
            ->assertSee('T.A.K.')
            ->assertSee('R.K.')
            ->assertDontSee('Siti Aminah')
            ->assertDontSee('Tan Ah Kow')
            ->assertDontSee('MRN-B1');

        // Even a link changed by hand to full names shows initials and no MRN
        DB::table('ward_roster_share_links')->where('id', $link->id)->update(['names' => 'full']);
        $this->open($token)->assertOk()
            ->assertSee('S.A.')
            ->assertDontSee('Siti Aminah')
            ->assertDontSee('MRN-B1');
    }

    public function test_leave_shows_on_a_link_without_its_type(): void
    {
        [, $token] = $this->link(['sections' => ['roster', 'nurse']]);

        $response = $this->open($token)->assertOk()
            ->assertSee('Farah Idris')
            ->assertSee('>Leave</span>', false);

        $response->assertDontSee('>MC<', false)
            ->assertDontSee('Medical leave')
            ->assertDontSee('Fever');
        $this->assertFalse($response->viewData('snapshot')['leave_detail']);
        $this->assertSame(['code' => 'Leave', 'label' => 'Leave', 'type' => null], collect($response->viewData('snapshot')['weeks'][0]['nurses'])->firstWhere('name', 'Farah Idris')['days']['2026-10-07']['leave']);
    }

    public function test_unknown_expired_and_revoked_links_are_the_same_404(): void
    {
        [$expired, $expiredToken] = $this->link(['expires_at' => now()->subMinute()]);
        [$revoked, $revokedToken] = $this->link(['revoked_at' => now()->subHour()]);
        [$week, $weekToken] = $this->link(['expires_at' => now()->addDays(7)]);

        foreach ([Str::random(48), $expiredToken, $revokedToken] as $token) {
            $response = $this->open($token)
                ->assertNotFound()
                ->assertSee('This link has expired or was turned off. Ask the nurse manager for a new one.')
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }

        // Open for seven days, then no more
        $this->open($weekToken)->assertOk();
        $this->travelTo(Carbon::parse('2026-10-12 10:00:01'));
        $this->open($weekToken)->assertNotFound();

        $this->assertSame(0, $expired->fresh()->view_count);
        $this->assertSame(0, $revoked->fresh()->view_count);
        $this->assertSame(1, $week->fresh()->view_count);
    }

    public function test_each_view_counts_but_the_page_refreshing_itself_does_not(): void
    {
        [$link, $token] = $this->link();
        $changed = $link->updated_at->toDateTimeString();

        $this->travelTo(Carbon::parse('2026-10-05 11:30:00'));
        $this->open($token)->assertOk();
        $this->open($token)->assertOk();
        $this->open($token, [], [AiNurseScheduleShareController::REFRESH_HEADER => '1'])->assertOk();

        $link->refresh();
        $this->assertSame(2, $link->view_count);
        $this->assertSame('2026-10-05 11:30:00', $link->last_viewed_at->toDateTimeString());
        // A view is not a change to the link
        $this->assertSame($changed, $link->updated_at->toDateTimeString());
    }

    public function test_a_rolling_link_follows_the_current_week_and_can_open_the_next(): void
    {
        [, $token] = $this->link(['expires_at' => now()->addDays(30)]);

        $this->open($token)->assertOk()
            ->assertSee('Next week')
            ->assertSee('Mon 5 Oct &ndash; Sun 11 Oct 2026', false)
            ->assertViewHas('next', false)
            ->assertViewHas('snapshot', fn (array $snapshot) => $snapshot['from']->toDateString() === '2026-10-05');

        $this->open($token, ['week' => 'next'])->assertOk()
            ->assertSee('Mon 12 Oct &ndash; Sun 18 Oct 2026', false)
            ->assertViewHas('next', true)
            ->assertViewHas('snapshot', fn (array $snapshot) => $snapshot['from']->toDateString() === '2026-10-12');

        // Any other week is not on offer
        $this->open($token, ['week' => '2026-12-07'])->assertOk()
            ->assertViewHas('snapshot', fn (array $snapshot) => $snapshot['from']->toDateString() === '2026-10-05');

        // A week on, the link shows that week
        $this->travelTo(Carbon::parse('2026-10-13 09:00:00'));
        $this->open($token)->assertOk()
            ->assertSee('Mon 12 Oct &ndash; Sun 18 Oct 2026', false);
    }

    public function test_a_link_for_one_week_shows_only_that_week(): void
    {
        [, $token] = $this->link(['week_mode' => 'fixed', 'week_start' => '2026-09-28']);

        foreach ([[], ['week' => 'next']] as $query) {
            $this->open($token, $query)->assertOk()
                ->assertSee('Mon 28 Sep &ndash; Sun 4 Oct 2026', false)
                ->assertSee('this link shows this week only')
                ->assertDontSee('Next week')
                ->assertViewHas('next', false)
                ->assertViewHas('snapshot', fn (array $snapshot) => $snapshot['from']->toDateString() === '2026-09-28');
        }
    }

    public function test_the_bed_assignment_on_a_link_is_the_shift_on_now_or_the_one_picked(): void
    {
        [, $token] = $this->link(['sections' => ['assign']]);

        $nurseOf = fn (TestResponse $response) => collect($response->viewData('snapshot')['assign']['rows'])->pluck('nurse', 'bed')->all();

        $now = $this->open($token)->assertOk()->assertSee('(on now)');
        $this->assertSame(['2026-10-05', 'AM'], [$now->viewData('snapshot')['assign']['day'], $now->viewData('snapshot')['assign']['shift']]);
        $this->assertSame(['B1' => 'Aisyah Rahman', 'B2' => 'Aisyah Rahman', 'B3' => 'Farah Idris'], $nurseOf($now));

        $picked = $this->open($token, ['day' => '2026-10-06', 'shift' => 'PM'])->assertOk();
        $this->assertSame(['2026-10-06', 'PM'], [$picked->viewData('snapshot')['assign']['day'], $picked->viewData('snapshot')['assign']['shift']]);
        $this->assertSame(['B1' => 'Aisyah Rahman', 'B2' => 'Aisyah Rahman', 'B3' => 'Aisyah Rahman'], $nurseOf($picked));

        // A day outside the week or an unknown shift falls back to the shift on now
        $fallback = $this->open($token, ['day' => '2026-11-30', 'shift' => 'XX'])->assertOk();
        $this->assertSame(['2026-10-05', 'AM'], [$fallback->viewData('snapshot')['assign']['day'], $fallback->viewData('snapshot')['assign']['shift']]);

        // Only the sections the link was made for
        $now->assertDontSee('id="section-roster"', false)->assertDontSee('id="section-acuity"', false);
    }

    public function test_the_acuity_section_cites_its_method_and_references(): void
    {
        [, $token] = $this->link(['sections' => ['acuity']]);

        // The method is SmartWard's own; ANA is cited for the approach and the references only
        $this->open($token)->assertOk()
            ->assertSee('Method: patient classification and acuity-based staffing (ANA, 2017).')
            ->assertSee('<details id="acuity-references"', false)
            ->assertSee('How SmartWard suggests levels and staffing')
            ->assertSee('How SmartWard suggests levels and staffing, and the ANA (2017) references')
            ->assertDontSee('How it is worked out (ANA, 2017)')
            ->assertSee('American Nurses Association');
    }

    public function test_a_link_stops_when_its_ward_is_deactivated_and_none_is_made_for_an_inactive_ward(): void
    {
        [$link, $token] = $this->link();
        $this->open($token)->assertOk();

        // The schedule page no longer lists the ward, so its links could not be seen or revoked there
        $this->ward->update(['is_active' => false]);
        $this->open($token)
            ->assertNotFound()
            ->assertSee('This link has expired or was turned off. Ask the nurse manager for a new one.')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertSame(1, $link->fresh()->view_count);

        $this->share($this->manager)->assertSessionHasErrors(['ward_id' => 'Links can only be made for an active ward.']);
        $this->assertSame(1, WardRosterShareLink::count());
    }

    public function test_a_signed_in_siem_auditor_can_open_a_link_like_anyone_else(): void
    {
        [, $token] = $this->link();
        $auditor = User::factory()->create(['role' => User::ROLE_SIEM_AUDITOR]);

        $this->actingAs($auditor);
        $this->open($token)->assertOk()->assertSee('Ward 6: AI Nurse Schedule');
        // Still kept out of the app itself
        $this->get(route('ward.ai-schedule'))->assertForbidden();
    }

    public function test_link_views_are_rate_limited_in_their_own_bucket(): void
    {
        [, $token] = $this->link();
        // Nobody signed in: the limiter keys on the address, here 127.0.0.1
        $signature = sha1('|127.0.0.1');

        $this->open($token)->assertOk();

        // Counted under the link's own prefix, not in the bucket the integration APIs share
        $this->assertSame(1, RateLimiter::attempts('roster-shared' . $signature));
        $this->assertSame(0, RateLimiter::attempts($signature));
    }

    public function test_a_manager_revokes_a_link_and_it_stops_working(): void
    {
        [$link, $token] = $this->link(['label' => 'Ward 6 staff chat']);
        $this->open($token)->assertOk();

        $this->actingAs($this->manager)
            ->post(route('ward.ai-schedule.shares.revoke', $link), ['ward_id' => $this->ward->id, 'week' => self::WEEK, 'tab' => 'workload'])
            ->assertRedirect(route('ward.ai-schedule', ['ward_id' => $this->ward->id, 'week' => self::WEEK, 'tab' => 'workload']))
            ->assertSessionHas('share_open', true)
            ->assertSessionHas('success', 'Link revoked: Ward 6 staff chat. Anyone who opens it now is told it has expired.');

        $link->refresh();
        $this->assertSame('2026-10-05 10:00:00', $link->revoked_at->toDateTimeString());
        $this->assertSame($this->manager->id, $link->revoked_by);
        $this->assertSame(1, UserActivity::where('activity_type', 'roster_share')->count());

        // Revoking again changes nothing
        $this->travel(5)->minutes();
        $this->post(route('ward.ai-schedule.shares.revoke', $link), ['week' => self::WEEK])->assertRedirect();
        $this->assertSame('2026-10-05 10:00:00', $link->fresh()->revoked_at->toDateTimeString());
        $this->assertSame(1, UserActivity::where('activity_type', 'roster_share')->count());

        $this->open($token)->assertNotFound();
    }

    public function test_the_share_dialog_lists_the_wards_links_that_still_work(): void
    {
        $other = Ward::create(['hospital_id' => $this->ward->hospital_id, 'ward_code' => 'W7', 'ward_name' => 'Ward 7', 'is_active' => true]);
        [$open] = $this->link(['label' => 'Ward 6 staff chat', 'sections' => ['roster', 'acuity'], 'names' => 'initials']);
        $this->link(['label' => 'Old link', 'revoked_at' => now()->subDay()]);
        $this->link(['label' => 'Last month', 'expires_at' => now()->subDay()]);
        $this->link(['label' => 'Ward 7 link'], $other);
        $url = $open->url();

        $this->schedulePage($this->manager)->assertOk()
            ->assertViewHas('shareLinks', fn ($links) => $links->pluck('id')->all() === [$open->id])
            ->assertSee('aiScheduleShare(', false)
            ->assertSee('Share the AI Nurse Schedule')
            ->assertSee('Staff who sign in to SmartWard')
            ->assertSee('Anyone with the link: view only, no sign-in')
            ->assertSee(route('ward.ai-schedule.shares.store'), false)
            ->assertSee('Create link')
            ->assertSee('View-only links open now (1)')
            ->assertSee('Ward 6 staff chat')
            ->assertSee('Current week (and next)')
            ->assertSee('Patients: Initials')
            ->assertSee('Not opened yet')
            ->assertSee(e($url), false)
            ->assertSee(route('ward.ai-schedule.shares.revoke', $open), false)
            ->assertDontSee('Old link')
            ->assertDontSee('Last month')
            ->assertDontSee('Ward 7 link');

        // Nurses can copy the links but cannot make or revoke them
        $this->schedulePage($this->nurseUser)->assertOk()
            ->assertSee('Share the AI Nurse Schedule')
            ->assertSee('A nurse manager or admin can create a view-only link for staff who do not sign in to SmartWard.')
            ->assertSee(e($url), false)
            ->assertDontSee(route('ward.ai-schedule.shares.store'), false)
            ->assertDontSee(route('ward.ai-schedule.shares.revoke', $open), false);
    }

    public function test_a_new_link_opens_the_share_dialog_on_it(): void
    {
        $this->followingRedirects();
        $response = $this->share($this->manager)->assertOk();

        // The dialog's settings, as @js writes them inside JSON.parse('...')
        $setting = fn (string $json) => substr(json_encode($json, JSON_HEX_QUOT), 1, -1);

        $link = WardRosterShareLink::sole();
        $response->assertSee('View-only link created for Ward 6')
            ->assertSee('id="share-link-' . $link->id . '"', false)
            // Open, on the new link
            ->assertSee($setting('"open":true'), false)
            ->assertSee($setting('"highlight":' . $link->id), false);

        // Without a new link it stays shut
        $this->schedulePage($this->manager)->assertOk()->assertSee($setting('"open":false'), false);
    }

    public function test_a_link_whose_token_can_no_longer_be_read_still_works(): void
    {
        [$link, $token] = $this->link();
        // As after the app key changed: the encrypted copy can no longer be read
        DB::table('ward_roster_share_links')->where('id', $link->id)->update(['token' => 'not-readable-any-more']);

        $this->assertNull($link->fresh()->plainToken());
        $this->assertNull($link->fresh()->url());
        $this->schedulePage($this->manager)->assertOk()
            ->assertSee('SmartWard can no longer show it');
        $this->open($token)->assertOk();
    }
}
