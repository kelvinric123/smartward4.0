<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\EkadBedMapping;
use App\Models\EkadConfiguration;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Models\Ward;
use App\Models\WardType;
use App\Services\EkadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Each EKad screen can have its own SEEKINK template (Bed Mapping on /ekad),
 * for screens of another size or another ward's layout. Screens without one
 * use the Template ID under Configuration & Login.
 */
class EkadScreenTemplateTest extends TestCase
{
    use RefreshDatabase;

    private const DEFAULT_TEMPLATE = '2000477842757914624';
    private const ICU_TEMPLATE = '2000555666777888999';

    private User $user;
    private Hospital $hospital;

    /** SEEKINK's replies to the next paints; once used up, every paint succeeds */
    private array $paintReplies = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Never paint a real screen from a test
        Http::preventStrayRequests();
        Http::fake(function (HttpRequest $request) {
            if (str_ends_with($request->url(), '/api/v1/user/login')) {
                return Http::response(['code' => 200, 'data' => 'fresh-token']);
            }
            if (str_ends_with($request->url(), '/api/v1/template/batchPaintingByJson')) {
                return Http::response(array_shift($this->paintReplies) ?? ['code' => 200, 'msg' => 'ok']);
            }

            return null;
        });

        $this->user = User::factory()->create();
        $this->hospital = Hospital::create(['name' => 'Test Hospital']);

        EkadConfiguration::create([
            'base_url' => 'http://seekink.test/cloud/prod-api',
            'username' => 'moe',
            'password' => 'secret',
            'template_id' => self::DEFAULT_TEMPLATE,
            'bearer_token' => 'token',
            'token_expires_at' => now()->addHour(),
            'auto_push_enabled' => true,
            'is_active' => true,
        ]);
    }

    private function makeWard(string $code, string $name, bool $criticalCare): Ward
    {
        $type = WardType::create(['code' => $code, 'name' => $code . ' type', 'is_critical_care' => $criticalCare, 'is_active' => true]);

        return Ward::create([
            'hospital_id' => $this->hospital->id, 'ward_code' => $code, 'ward_name' => $name,
            'ward_type_id' => $type->id, 'is_active' => true,
        ]);
    }

    private function makeBed(Ward $ward, string $number, ?string $mac = null, ?string $templateId = null): Bed
    {
        $bed = Bed::create([
            'ward_id' => $ward->id, 'bed_number' => $number, 'bed_id' => $ward->ward_code . '-' . $number,
            'bed_display_name' => $number, 'status' => 'available', 'is_active' => true,
        ]);

        if ($mac) {
            EkadBedMapping::create(['bed_id' => $bed->id, 'mac_address' => $mac, 'template_id' => $templateId]);
        }

        return $bed;
    }

    private function makePatient(string $mrn, string $ic): Patient
    {
        return Patient::create([
            'name' => 'Tan Mei Ling', 'mrn' => $mrn, 'rn' => 'RN-' . $mrn, 'ic_passport' => $ic,
            'age' => 46, 'gender' => 'Female', 'phone' => '012-0000000', 'status' => 'active', 'is_active' => true,
        ]);
    }

    /** What was sent to SEEKINK: [template, screen MAC, MRN shown] per paint */
    private function paints(): array
    {
        return Http::recorded(fn (HttpRequest $request) => str_ends_with($request->url(), '/batchPaintingByJson'))
            ->map(fn (array $sent) => [$sent[0]['id'], $sent[0]['macList'][0], $sent[0]['data'][0]['MRN']])
            ->values()->all();
    }

    public function test_critical_care_dashboard_admission_and_discharge_repaint_the_screen_with_its_template(): void
    {
        $icu = $this->makeWard('ICU', 'Intensive Care Unit', true);
        $this->makeBed($icu, 'ICU-01', 'D4:3D:39:00:00:01', self::ICU_TEMPLATE);
        $patient = $this->makePatient('MRN70001', '800101-10-7001');

        $dashboard = route('critical-care.dashboard', ['ward_id' => $icu->id]);
        $this->actingAs($this->user)->get($dashboard)->assertOk()->assertSee('ICU-01');
        $this->assertSame([], $this->paints());

        // The critical care dashboard's Admit form posts to the ward dashboard's admit action and comes back
        $this->actingAs($this->user)->from($dashboard)->post(route('ward.admit-patient'), [
            'patient_id' => $patient->id, 'ward_id' => $icu->id, 'bed_number' => 'ICU-01',
        ])->assertRedirect($dashboard)->assertSessionHas('success');

        $this->assertSame([[self::ICU_TEMPLATE, 'D43D39000001', 'MRN70001']], $this->paints());

        $this->actingAs($this->user)->post(route('ward.discharge-patient'), ['patient_id' => $patient->id])
            ->assertSessionHas('success');

        $this->assertSame([
            [self::ICU_TEMPLATE, 'D43D39000001', 'MRN70001'],
            [self::ICU_TEMPLATE, 'D43D39000001', 'Vacant'],
        ], $this->paints());
    }

    public function test_a_screen_without_its_own_template_uses_the_configured_one(): void
    {
        $ward = $this->makeWard('D6', 'Ward D6', false);
        $this->makeBed($ward, 'D601', 'D43D393D1C32');
        $patient = $this->makePatient('MRN70002', '800101-10-7002');

        $this->actingAs($this->user)->from(route('ward.dashboard'))->post(route('ward.admit-patient'), [
            'patient_id' => $patient->id, 'ward_id' => $ward->id, 'bed_number' => 'D601',
        ])->assertSessionHas('success');

        $this->assertSame([[self::DEFAULT_TEMPLATE, 'D43D393D1C32', 'MRN70002']], $this->paints());
    }

    public function test_manual_sync_paints_each_screen_with_its_own_template(): void
    {
        $ward = $this->makeWard('D6', 'Ward D6', false);
        $icu = $this->makeWard('ICU', 'Intensive Care Unit', true);
        $wardBed = $this->makeBed($ward, 'D601', 'D43D393D1C32');
        $icuBed = $this->makeBed($icu, 'ICU-01', 'D43D39000001', self::ICU_TEMPLATE);

        $preview = $this->actingAs($this->user)->getJson(route('ekad.sync-preview'))->assertOk()->json('data');
        $this->assertEqualsCanonicalizing([
            ['D43D393D1C32', self::DEFAULT_TEMPLATE, true],
            ['D43D39000001', self::ICU_TEMPLATE, false],
        ], collect($preview)->map(fn ($row) => [$row['mac_address'], $row['template_id'], $row['template_is_default']])->all());

        $this->actingAs($this->user)->postJson(route('ekad.sync-selected'), ['bed_ids' => [$wardBed->id, $icuBed->id]])
            ->assertOk()->assertJsonPath('message', 'Sync completed: 2 success, 0 failed');

        $this->assertSame([
            [self::DEFAULT_TEMPLATE, 'D43D393D1C32', 'Vacant'],
            [self::ICU_TEMPLATE, 'D43D39000001', 'Vacant'],
        ], $this->paints());
    }

    public function test_a_retry_after_an_expired_token_keeps_the_screens_template(): void
    {
        $icu = $this->makeWard('ICU', 'Intensive Care Unit', true);
        $bed = $this->makeBed($icu, 'ICU-01', 'D43D39000001', self::ICU_TEMPLATE);
        $this->paintReplies = [['code' => 401, 'msg' => 'token expired']];

        $result = (new EkadService(EkadConfiguration::getActive()))->pushVacant($bed, 'test');

        $this->assertTrue($result['success']);
        $this->assertSame('fresh-token', EkadConfiguration::getActive()->bearer_token);
        $this->assertSame([
            [self::ICU_TEMPLATE, 'D43D39000001', 'Vacant'],
            [self::ICU_TEMPLATE, 'D43D39000001', 'Vacant'],
        ], $this->paints());
    }

    public function test_bed_mapping_saves_a_screen_template_or_leaves_it_on_the_default(): void
    {
        $icu = $this->makeWard('ICU', 'Intensive Care Unit', true);
        $first = $this->makeBed($icu, 'ICU-01');
        $second = $this->makeBed($icu, 'ICU-02');

        $this->actingAs($this->user)->postJson(route('ekad.bed-mappings.store'), [
            'bed_id' => $first->id, 'mac_address' => 'D4:3D:39:00:00:01', 'template_id' => ' ' . self::ICU_TEMPLATE . ' ',
        ])->assertOk()->assertJsonPath('mapping.template_id', self::ICU_TEMPLATE);

        $this->actingAs($this->user)->postJson(route('ekad.bed-mappings.store'), [
            'bed_id' => $second->id, 'mac_address' => 'D43D39000002', 'template_id' => null,
        ])->assertOk()->assertJsonPath('mapping.template_id', null);

        $this->assertSame(self::ICU_TEMPLATE, EkadBedMapping::where('bed_id', $first->id)->value('template_id'));
        $this->assertNull(EkadBedMapping::where('bed_id', $second->id)->value('template_id'));

        $this->actingAs($this->user)->postJson(route('ekad.bed-mappings.store'), [
            'bed_id' => $this->makeBed($icu, 'ICU-03')->id, 'mac_address' => 'D43D39000003', 'template_id' => str_repeat('9', 65),
        ])->assertUnprocessable()->assertJsonValidationErrors('template_id');
    }

    public function test_changing_a_screens_template_alone_or_for_its_whole_ward(): void
    {
        $icu = $this->makeWard('ICU', 'Intensive Care Unit', true);
        $ward = $this->makeWard('D6', 'Ward D6', false);
        $this->makeBed($icu, 'ICU-01', 'D43D39000001');
        $this->makeBed($icu, 'ICU-02', 'D43D39000002');
        $this->makeBed($ward, 'D601', 'D43D393D1C32');
        [$icuFirst, $icuSecond, $wardScreen] = EkadBedMapping::orderBy('id')->get()->all();

        // The template alone, without resending the MAC address
        $this->actingAs($this->user)->putJson(route('ekad.bed-mappings.update', $icuFirst), ['template_id' => self::ICU_TEMPLATE])
            ->assertOk()->assertJsonPath('updated_ids', [$icuFirst->id])->assertJsonPath('message', 'Template updated for 1 screen');
        $this->assertSame(self::ICU_TEMPLATE, $icuFirst->fresh()->template_id);
        $this->assertSame('D43D39000001', $icuFirst->fresh()->mac_address);
        $this->assertNull($icuSecond->fresh()->template_id);

        // For every screen in the ICU, and no other ward
        $this->actingAs($this->user)->putJson(route('ekad.bed-mappings.update', $icuSecond), [
            'template_id' => '2000111222333444555', 'apply_to_ward' => true,
        ])->assertOk()->assertJsonPath('updated_ids', [$icuFirst->id, $icuSecond->id])
            ->assertJsonPath('message', 'Template updated for 2 screens');
        $this->assertSame('2000111222333444555', $icuFirst->fresh()->template_id);
        $this->assertSame('2000111222333444555', $icuSecond->fresh()->template_id);
        $this->assertNull($wardScreen->fresh()->template_id);

        // Back to the default
        $this->actingAs($this->user)->putJson(route('ekad.bed-mappings.update', $icuFirst), ['template_id' => null])
            ->assertOk()->assertJsonPath('mapping.template_id', null);
        $this->assertNull($icuFirst->fresh()->template_id);
    }

    public function test_ekad_page_shows_each_screens_template(): void
    {
        $icu = $this->makeWard('ICU', 'Intensive Care Unit', true);
        $this->makeBed($icu, 'ICU-01', 'D43D39000001', self::ICU_TEMPLATE);
        $this->makeBed($icu, 'ICU-02', 'D43D39000002');

        $response = $this->actingAs($this->user)->get(route('ekad.index'))->assertOk()
            ->assertSee('Other template ID…')
            ->assertSee('Screen Template')
            ->assertSee('defaultTemplateId: "' . self::DEFAULT_TEMPLATE . '"', false);

        $this->assertEqualsCanonicalizing(
            [self::ICU_TEMPLATE, null],
            $response->viewData('bedMappings')->pluck('template_id')->all()
        );
    }
}
