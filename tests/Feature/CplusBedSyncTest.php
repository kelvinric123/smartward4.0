<?php

namespace Tests\Feature;

use App\Models\AdmissionLog;
use App\Models\Bed;
use App\Models\Consultant;
use App\Models\EkadBedMapping;
use App\Models\EkadConfiguration;
use App\Models\Hospital;
use App\Models\IsolationType;
use App\Models\Patient;
use App\Models\PatientCareProvider;
use App\Models\User;
use App\Models\Ward;
use App\Models\WardType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CplusBedSyncTest extends TestCase
{
    use RefreshDatabase;

    private Hospital $hospital;
    private Consultant $consultant;
    private User $rpa;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cplus.hospital_id' => null]);
        $this->hospital = Hospital::create(['name' => 'Test Hospital']);
        $this->consultant = Consultant::create(['name' => 'Dr Tan Ah Kow', 'registration_number' => 'MMC1001', 'is_active' => true]);
        $this->rpa = User::factory()->create(['name' => 'C+ RPA', 'role' => User::ROLE_INTEGRATION]);
        $this->token = $this->rpa->generateApiToken();
    }

    private function sync(array $wards, ?string $token = null)
    {
        return $this->withToken($token ?? $this->token)->postJson('/api/cplus/bed-sync', [
            'facility' => ['id' => '26', 'name' => 'PHAK'],
            'wards' => $wards,
        ]);
    }

    private function bed(string $id, string $name, string $status = 'available', ?array $patient = null): array
    {
        return [
            'id' => $id, 'name' => $name, 'room_no' => explode('-', $name)[0],
            'status' => $patient ? 'occupied' : $status, 'patient' => $patient,
        ];
    }

    private function patient(string $mrn, string $name, string $physician = 'DR TAN AH KOW', ?string $admittedAt = null): array
    {
        return [
            'mrn' => $mrn, 'rn' => 'PHAK26IP' . substr($mrn, -8), 'name' => $name, 'gender' => 'Female', 'physician' => $physician,
            'admitted_at' => $admittedAt, 'admitted_at_source' => $admittedAt ? 'census' : null,
        ];
    }

    /** ICU with Siti in 217-A, 217-B empty and 217-C out of service; Ward C4 with Aminah in C401. */
    private function census(?array $icuBeds = null, ?array $c4Beds = null): array
    {
        return [
            ['id' => '190', 'name' => 'INTENSIVE CARE UNIT (ICU)', 'beds' => $icuBeds ?? [
                $this->bed('12646', '217-A', patient: $this->patient('3200000001', 'SITI BINTI ALI')),
                $this->bed('12647', '217-B'),
                $this->bed('12648', '217-C', 'out_of_service'),
            ]],
            ['id' => '699', 'name' => 'WARD C4', 'beds' => $c4Beds ?? [
                $this->bed('13001', 'C401', patient: $this->patient('3200000002', 'AMINAH BINTI OMAR', 'DR NOT HERE')),
            ]],
        ];
    }

    private function bedNamed(string $number): Bed
    {
        return Bed::where('bed_number', $number)->firstOrFail();
    }

    private function admittedAt(string $mrn): string
    {
        return (string) Patient::where('mrn', $mrn)->value('admitted_at');
    }

    public function test_only_an_active_integration_users_token_gets_in(): void
    {
        $this->postJson('/api/cplus/bed-sync', [])->assertStatus(401);
        $this->sync($this->census(), 'wrong-token')->assertStatus(401);

        $this->rpa->update(['deactivated_at' => now()]);
        $this->sync($this->census())->assertStatus(401);

        $this->rpa->update(['deactivated_at' => null, 'role' => User::ROLE_USER]);
        $this->sync($this->census())->assertStatus(401);

        $this->assertSame(0, Ward::count());
    }

    public function test_ping_names_the_hospital_and_records_the_tokens_use(): void
    {
        $this->withToken($this->token)->getJson('/api/cplus/ping')
            ->assertOk()
            ->assertJsonPath('hospital.name', 'Test Hospital')
            ->assertJsonPath('integration_user', 'C+ RPA');

        $this->assertNotNull($this->rpa->fresh()->api_token_last_used_at);
    }

    public function test_it_needs_a_hospital_when_several_are_active(): void
    {
        Hospital::create(['name' => 'Second Hospital']);

        $this->sync($this->census())->assertStatus(422);
        $this->assertSame(0, Ward::count());
    }

    public function test_the_first_sync_creates_the_wards_and_beds_and_admits_the_patients(): void
    {
        $this->sync($this->census())
            ->assertOk()
            ->assertJsonPath('wards_created', 2)
            ->assertJsonPath('beds_created', 4)
            ->assertJsonPath('admitted', 2)
            ->assertJsonPath('unmatched_physicians', ['DR NOT HERE']);

        $icu = Ward::where('cplus_location_id', '190')->firstOrFail();
        $this->assertSame('INTENSIVE CARE UNIT (ICU)', $icu->ward_name);
        $this->assertSame('CP26-190', $icu->ward_code);
        $this->assertSame('26', $icu->cplus_facility_id);
        $this->assertSame($this->hospital->id, $icu->hospital_id);
        $this->assertSame(3, $icu->capacity);

        $bed = $this->bedNamed('217-A');
        $this->assertSame($icu->id, $bed->ward_id);
        $this->assertSame('CP26-12646', $bed->bed_id);
        $this->assertSame('217', $bed->cplus_room_no);
        $this->assertSame('occupied', $bed->status);
        $this->assertSame('available', $this->bedNamed('217-B')->status);
        $this->assertSame(Bed::STATUS_MAINTENANCE, $this->bedNamed('217-C')->status);

        $siti = Patient::where('mrn', '3200000001')->firstOrFail();
        $this->assertSame(Patient::STATUS_ADMITTED, $siti->status);
        $this->assertSame($icu->id, $siti->ward_id);
        $this->assertSame('217-A', $siti->bed_number);
        $this->assertSame('PHAK26IP00000001', $siti->rn);
        $this->assertSame('Female', $siti->gender);
        $this->assertSame($this->consultant->id, $siti->consultant_id);
        $this->assertSame($siti->id, $bed->patient_id);
        $this->assertNull(Patient::where('mrn', '3200000002')->value('consultant_id'));

        $log = AdmissionLog::where('mrn', '3200000001')->firstOrFail();
        $this->assertSame('admit', $log->action);
        $this->assertSame('cplus', $log->source);
        $this->assertSame('C+ Bed Management', $log->sourceLabel());
    }

    public function test_the_same_census_again_changes_nothing(): void
    {
        $this->sync($this->census())->assertOk();

        $this->sync($this->census())
            ->assertOk()
            ->assertJsonPath('wards_created', 0)
            ->assertJsonPath('beds_created', 0)
            ->assertJsonPath('admitted', 0)
            ->assertJsonPath('unchanged', 2);

        $this->assertSame(2, Ward::count());
        $this->assertSame(4, Bed::count());
        $this->assertSame(2, Patient::count());
        $this->assertSame(2, AdmissionLog::count());
    }

    public function test_a_patient_moved_in_cplus_is_transferred_and_the_old_bed_freed(): void
    {
        $this->sync($this->census())->assertOk();

        $this->sync($this->census(icuBeds: [
            $this->bed('12646', '217-A'),
            $this->bed('12647', '217-B', patient: $this->patient('3200000001', 'SITI BINTI ALI')),
            $this->bed('12648', '217-C', 'out_of_service'),
        ]))->assertOk()->assertJsonPath('transferred', 1);

        $siti = Patient::where('mrn', '3200000001')->firstOrFail();
        $this->assertSame('217-B', $siti->bed_number);
        $this->assertSame('occupied', $this->bedNamed('217-B')->status);
        $this->assertSame('available', $this->bedNamed('217-A')->status);
        $this->assertNull($this->bedNamed('217-A')->patient_id);
        $this->assertSame('transfer', AdmissionLog::where('mrn', '3200000001')->latest('id')->value('action'));
    }

    public function test_a_patient_cplus_no_longer_lists_is_discharged(): void
    {
        $this->sync($this->census())->assertOk();

        $this->sync($this->census(c4Beds: [$this->bed('13001', 'C401')]))
            ->assertOk()
            ->assertJsonPath('discharged', 1);

        $aminah = Patient::where('mrn', '3200000002')->firstOrFail();
        $this->assertSame(Patient::STATUS_DISCHARGED, $aminah->status);
        $this->assertFalse($aminah->is_active);
        $this->assertNull($aminah->ward_id);
        $this->assertNotNull($aminah->discharged_at);
        $this->assertSame('available', $this->bedNamed('C401')->status);
        $this->assertSame('discharge', AdmissionLog::where('mrn', '3200000002')->latest('id')->value('action'));
        // Siti is still listed and stays
        $this->assertSame(Patient::STATUS_ADMITTED, Patient::where('mrn', '3200000001')->value('status'));
    }

    public function test_nobody_is_discharged_when_cplus_lists_no_patients(): void
    {
        $this->sync($this->census())->assertOk();

        $this->sync($this->census(
            icuBeds: [$this->bed('12646', '217-A'), $this->bed('12647', '217-B'), $this->bed('12648', '217-C', 'out_of_service')],
            c4Beds: [$this->bed('13001', 'C401')],
        ))->assertOk()
            ->assertJsonPath('discharged', 0)
            ->assertJsonPath('skipped.0', 'C+ listed no patients at all, so nobody was discharged.');

        $this->assertSame(2, Patient::where('status', Patient::STATUS_ADMITTED)->count());
    }

    public function test_patients_in_wards_not_linked_to_cplus_are_left_alone(): void
    {
        $manual = Ward::create(['hospital_id' => $this->hospital->id, 'ward_code' => 'D6', 'ward_name' => 'Ward D6', 'is_active' => true]);
        $other = Patient::create([
            'name' => 'Lee Mei', 'mrn' => 'MRN-D6-1', 'rn' => 'RN-D6-1', 'ic_passport' => '900101-10-1111',
            'gender' => 'Female', 'phone' => '012-0000000', 'ward_id' => $manual->id, 'bed_number' => 'D601',
            'status' => Patient::STATUS_ADMITTED, 'is_active' => true, 'admitted_at' => now()->subDay(),
        ]);

        $this->sync($this->census())->assertOk()->assertJsonPath('discharged', 0);

        $this->assertSame(Patient::STATUS_ADMITTED, $other->fresh()->status);
        $this->assertSame($manual->id, $other->fresh()->ward_id);
    }

    public function test_a_returning_patient_is_readmitted_on_the_same_record(): void
    {
        $returning = Patient::create([
            'name' => 'Siti Ali', 'mrn' => '3200000001', 'rn' => 'OLD-RN-1', 'ic_passport' => '800101-10-2222',
            'gender' => 'Female', 'phone' => '012-1111111', 'status' => Patient::STATUS_DISCHARGED,
            'is_active' => false, 'discharged_at' => now()->subMonth(),
        ]);

        $this->sync($this->census())->assertOk()->assertJsonPath('admitted', 2);

        $returning->refresh();
        $this->assertSame(Patient::STATUS_ADMITTED, $returning->status);
        $this->assertTrue($returning->is_active);
        $this->assertNull($returning->discharged_at);
        $this->assertSame('PHAK26IP00000001', $returning->rn);
        $this->assertSame('800101-10-2222', $returning->ic_passport);
        $this->assertSame('012-1111111', $returning->phone);
        $this->assertSame(2, Patient::count());
    }

    public function test_the_admission_date_is_the_one_cplus_gives(): void
    {
        $this->sync($this->census(icuBeds: [
            $this->bed('12646', '217-A', patient: $this->patient('3200000001', 'SITI BINTI ALI', admittedAt: '2026-09-20T10:15:00+08:00')),
        ]))->assertOk();

        $expected = Carbon::parse('2026-09-20 10:15:00', 'Asia/Kuala_Lumpur');
        $siti = Patient::where('mrn', '3200000001')->firstOrFail();
        $this->assertTrue($expected->equalTo($siti->admitted_at), "admitted_at is {$siti->admitted_at}");
        $this->assertTrue($expected->equalTo(AdmissionLog::where('mrn', '3200000001')->value('admitted_at')));
    }

    public function test_a_later_sync_corrects_the_admission_date_of_patients_already_in_bed(): void
    {
        Carbon::setTestNow('2026-09-29 14:00:00');
        $this->sync($this->census())->assertOk();
        $this->assertSame('2026-09-29 14:00:00', $this->admittedAt('3200000001'));

        Carbon::setTestNow('2026-09-29 14:05:00');
        $this->sync($this->census(
            icuBeds: [$this->bed('12646', '217-A', patient: $this->patient('3200000001', 'SITI BINTI ALI', admittedAt: '2026-09-25T08:30:00+08:00'))],
            c4Beds: [$this->bed('13001', 'C401', patient: $this->patient('3200000002', 'AMINAH BINTI OMAR', 'DR NOT HERE', '2026-09-28T23:10:00+08:00'))],
        ))->assertOk()
            ->assertJsonPath('admission_dates_corrected', 2)
            ->assertJsonPath('admitted', 0);

        $this->assertSame('2026-09-25 08:30:00', $this->admittedAt('3200000001'));
        $this->assertSame('2026-09-28 23:10:00', $this->admittedAt('3200000002'));
        // The admit entries follow, so the Command Center counts them on the real day
        $this->assertSame(['2026-09-25 08:30:00'], AdmissionLog::where('mrn', '3200000001')->where('action', 'admit')->pluck('admitted_at')->map(fn ($at) => (string) $at)->all());
        $this->assertSame(2, AdmissionLog::count());

        // The same dates again change nothing
        $this->sync($this->census(
            icuBeds: [$this->bed('12646', '217-A', patient: $this->patient('3200000001', 'SITI BINTI ALI', admittedAt: '2026-09-25T08:30:00+08:00'))],
            c4Beds: [$this->bed('13001', 'C401', patient: $this->patient('3200000002', 'AMINAH BINTI OMAR', 'DR NOT HERE', '2026-09-28T23:10:00+08:00'))],
        ))->assertOk()->assertJsonPath('admission_dates_corrected', 0)->assertJsonPath('unchanged', 2);
    }

    public function test_an_admission_date_in_the_future_is_not_used(): void
    {
        Carbon::setTestNow('2026-09-29 14:00:00');

        $this->sync($this->census(icuBeds: [
            $this->bed('12646', '217-A', patient: $this->patient('3200000001', 'SITI BINTI ALI', admittedAt: '2026-10-05T09:00:00+08:00')),
        ]))->assertOk();

        $this->assertSame('2026-09-29 14:00:00', $this->admittedAt('3200000001'));
    }

    /** ER Management's Yellow Zone (unit group 3) with the given beds. */
    private function edZone(array $beds): array
    {
        return ['id' => '138', 'name' => 'ED - YELLOW ZONE', 'unit_group' => '3', 'beds' => $beds];
    }

    public function test_ed_zones_get_the_emergency_ward_type_and_a_type_set_by_hand_stays(): void
    {
        $emergency = WardType::create(['code' => 'EDOB', 'name' => 'ED Observation Bay', 'is_emergency' => true, 'is_active' => true]);

        $this->sync([...$this->census(), $this->edZone([$this->bed('20001', 'YELLOW01')])])->assertOk();

        $zone = Ward::where('cplus_location_id', '138')->firstOrFail();
        $this->assertSame($emergency->id, $zone->ward_type_id);
        $this->assertTrue($zone->isEmergency());
        $this->assertNull(Ward::where('cplus_location_id', '190')->value('ward_type_id'));

        $other = WardType::create(['code' => 'RESUS', 'name' => 'Resus', 'is_emergency' => true, 'is_active' => true]);
        $zone->update(['ward_type_id' => $other->id]);
        $this->sync([...$this->census(), $this->edZone([$this->bed('20001', 'YELLOW01')])])->assertOk();
        $this->assertSame($other->id, $zone->fresh()->ward_type_id);
    }

    public function test_leaving_the_ed_for_a_ward_ends_the_ed_visit_and_admits_to_the_ward(): void
    {
        $this->travelTo(Carbon::parse('2026-09-29 14:00:00'));
        WardType::create(['code' => 'EDOB', 'name' => 'ED Observation Bay', 'is_emergency' => true, 'is_active' => true]);
        $inEd = ['mrn' => '3200000009', 'rn' => 'PHAK26ED09000001', 'name' => 'ALI BIN ABU', 'gender' => 'Male',
            'physician' => 'DR LEE', 'admitted_at' => '2026-09-29T09:00:00+08:00'];
        $this->sync([...$this->census(), $this->edZone([$this->bed('20001', 'YELLOW01', patient: $inEd)])])->assertOk();

        $onWard = ['rn' => 'PHAK26IP09000123', 'admitted_at' => '2026-09-29T13:15:00+08:00'] + $inEd;
        $this->sync([
            ...$this->census(c4Beds: [
                $this->bed('13001', 'C401', patient: $this->patient('3200000002', 'AMINAH BINTI OMAR', 'DR NOT HERE')),
                $this->bed('13002', 'C402', patient: $onWard),
            ]),
            $this->edZone([$this->bed('20001', 'YELLOW01')]),
        ])->assertOk()
            ->assertJsonPath('ed_admitted_to_ward', 1)
            ->assertJsonPath('admitted', 1);

        $zone = Ward::where('cplus_location_id', '138')->firstOrFail();
        $c4 = Ward::where('cplus_location_id', '699')->firstOrFail();
        $ali = Patient::where('mrn', '3200000009')->firstOrFail();
        $this->assertSame([$c4->id, 'C402', 'PHAK26IP09000123'], [$ali->ward_id, $ali->bed_number, $ali->rn]);
        $this->assertSame('2026-09-29 13:15:00', (string) $ali->admitted_at);

        $logs = AdmissionLog::where('mrn', '3200000009')->orderBy('id')->get();
        $this->assertSame(['admit', 'discharge', 'admit'], $logs->pluck('action')->all());
        $this->assertSame([$zone->id, $zone->id, $c4->id], $logs->pluck('ward_id')->all());
        $this->assertSame($c4->id, $logs[1]->to_ward_id);
        $this->assertSame('2026-09-29 13:15:00', (string) $logs[1]->discharged_at);
        $this->assertSame('available', Bed::where('bed_number', 'YELLOW01')->value('status'));
    }

    public function test_the_cplus_physician_is_the_attending_doctor_until_discharge(): void
    {
        $this->sync($this->census())->assertOk();

        $siti = Patient::where('mrn', '3200000001')->firstOrFail();
        $attending = PatientCareProvider::where('patient_id', $siti->id)->where('source', 'cplus')->firstOrFail();
        $this->assertSame([PatientCareProvider::ROLE_ATTENDING, 'DR TAN AH KOW', $this->consultant->id, true],
            [$attending->role, $attending->doctor_name, $attending->consultant_id, $attending->is_active]);
        $this->assertSame('Dr Tan Ah Kow', $attending->display_name);
        $aminah = Patient::where('mrn', '3200000002')->firstOrFail();
        $this->assertSame('DR NOT HERE', PatientCareProvider::where('patient_id', $aminah->id)->value('doctor_name'));

        $this->sync($this->census(c4Beds: [$this->bed('13001', 'C401')]))->assertOk();
        $this->assertFalse((bool) PatientCareProvider::where('patient_id', $aminah->id)->value('is_active'));
        $this->assertSame(1, PatientCareProvider::where('patient_id', $siti->id)->count());
    }

    public function test_a_prebook_waiting_for_a_bed_gets_it_when_its_patient_leaves(): void
    {
        $this->sync($this->census())->assertOk();
        $c4 = Ward::where('cplus_location_id', '699')->firstOrFail();
        $waiting = Patient::create([
            'name' => 'Nora Lim', 'mrn' => 'MRN-PB-1', 'rn' => 'RN-PB-1', 'ic_passport' => '850101-10-3333',
            'gender' => 'Female', 'phone' => '012-2222222', 'ward_id' => $c4->id, 'target_bed_number' => 'C401',
            'status' => Patient::STATUS_PREBOOK_PENDING, 'is_active' => true,
        ]);

        $this->sync($this->census(c4Beds: [$this->bed('13001', 'C401')]))->assertOk();

        $waiting->refresh();
        $this->assertSame(Patient::STATUS_PREBOOK, $waiting->status);
        $this->assertSame('C401', $waiting->bed_number);
        $this->assertSame('reserved', $this->bedNamed('C401')->status);
    }

    /** The census with the Isolation box of Siti's C+ admission card as the RPA read it; null = not read */
    private function censusWithIsolation(?bool $ticked): array
    {
        return $this->census(icuBeds: [
            $this->bed('12646', '217-A', patient: $this->patient('3200000001', 'SITI BINTI ALI') + ['isolation' => $ticked]),
        ]);
    }

    private function sitiIsolation(): array
    {
        $siti = Patient::where('mrn', '3200000001')->firstOrFail();

        return [$siti->isolation_type, $siti->cplus_isolation];
    }

    public function test_the_c_plus_isolation_box_isolates_the_patient_while_it_is_ticked(): void
    {
        $this->sync($this->censusWithIsolation(null))->assertOk();
        $this->assertSame(['none', null], $this->sitiIsolation());

        $this->sync($this->censusWithIsolation(true))->assertOk();
        $this->assertSame(['ISO', true], $this->sitiIsolation());
        $this->assertSame('Isolation', IsolationType::getDisplayName('ISO'));

        // Patient Details says where it comes from
        $siti = Patient::where('mrn', '3200000001')->firstOrFail();
        $details = route('ward.patient-details', ['patient_id' => $siti->id, 'active_tab' => 'additional']);
        $this->actingAs(User::factory()->create())->get($details)->assertOk()
            ->assertSee('Isolation is ticked on the C+ admission card');

        // Taken off here while C+ still has it ticked: C+ is the main source, so it comes back
        Patient::where('mrn', '3200000001')->firstOrFail()->update(['isolation_type' => 'none']);
        $this->sync($this->censusWithIsolation(true))->assertOk();
        $this->assertSame(['ISO', true], $this->sitiIsolation());

        // A sync that did not read the card changes nothing
        $this->sync($this->censusWithIsolation(null))->assertOk();
        $this->assertSame(['ISO', true], $this->sitiIsolation());

        $this->sync($this->censusWithIsolation(false))->assertOk();
        $this->assertSame(['none', false], $this->sitiIsolation());
        $this->actingAs(User::factory()->create())->get($details)->assertOk()
            ->assertDontSee('Isolation is ticked on the C+ admission card');
    }

    public function test_an_isolation_staff_set_in_patient_details_stays_whatever_c_plus_says(): void
    {
        $this->sync($this->censusWithIsolation(false))->assertOk();
        $siti = Patient::where('mrn', '3200000001')->firstOrFail();

        // Not ticked in C+: staff add one on the ward dashboard, even the plain Isolation type
        $siti->update(['isolation_type' => 'ISO']);
        $this->sync($this->censusWithIsolation(false))->assertOk();
        $this->assertSame(['ISO', false], $this->sitiIsolation());

        // A kind staff picked is kept when C+ ticks the box, and after it is unticked
        $siti->update(['isolation_type' => 'AI']);
        $this->sync($this->censusWithIsolation(true))->assertOk();
        $this->assertSame(['AI', true], $this->sitiIsolation());
        $this->sync($this->censusWithIsolation(false))->assertOk();
        $this->assertSame(['AI', false], $this->sitiIsolation());
    }

    public function test_a_c_plus_isolation_repaints_the_patients_ekad_screen(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*/api/v1/template/batchPaintingByJson' => Http::response(['code' => 200, 'msg' => 'ok'])]);
        EkadConfiguration::create([
            'base_url' => 'http://seekink.test/cloud/prod-api', 'username' => 'moe', 'password' => 'secret',
            'template_id' => '2000477842757914624', 'bearer_token' => 'token', 'token_expires_at' => now()->addHour(),
            'auto_push_enabled' => true, 'is_active' => true,
        ]);

        $this->sync($this->censusWithIsolation(false))->assertOk();
        EkadBedMapping::create(['bed_id' => $this->bedNamed('217-A')->id, 'mac_address' => 'D43D39000001']);

        $this->sync($this->censusWithIsolation(true))->assertOk();
        $this->sync($this->censusWithIsolation(false))->assertOk();

        $painted = Http::recorded()->map(fn (array $sent) => [$sent[0]['macList'][0], $sent[0]['data'][0]['isolation_type']]);
        $this->assertSame([['D43D39000001', 'ISOLATION'], ['D43D39000001', '-']], $painted->values()->all());
    }
}
