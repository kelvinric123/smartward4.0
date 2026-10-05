<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\EkadBedMapping;
use App\Models\EkadConfiguration;
use App\Models\EkadTemplate;
use App\Models\Hospital;
use App\Models\IsolationType;
use App\Models\Patient;
use App\Models\User;
use App\Models\Ward;
use App\Models\WardType;
use App\Services\EkadService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Each SEEKINK template's fields are edited under Templates on /ekad: the
 * field names the template has and what SmartWard puts in each. A template
 * nobody has edited gets the default fields.
 */
class EkadTemplateFieldsTest extends TestCase
{
    use RefreshDatabase;

    private const ICU_TEMPLATE = '2105181664036876288';
    private const ICU_FIELDS = [
        ['key' => 'bed', 'source' => 'bed_number'],
        ['key' => 'name', 'source' => 'patient_name'],
        ['key' => 'iso', 'source' => 'isolation'],
        ['key' => 'allergy', 'source' => 'allergies'],
        ['key' => 'unit', 'source' => 'ward'],
        ['key' => 'nurse_on_duty', 'source' => 'nurse'],
        ['key' => 'title', 'source' => 'text', 'value' => 'Smart Ward'],
    ];

    private User $user;
    private Ward $icu;
    private Bed $bed;

    protected function setUp(): void
    {
        parent::setUp();

        // Never paint a real screen from a test
        Http::preventStrayRequests();
        Http::fake(function (HttpRequest $request) {
            if (str_ends_with($request->url(), '/api/v1/template/batchPaintingByJson')) {
                return Http::response(['code' => 200, 'msg' => 'Operation Successful！']);
            }

            return null;
        });

        $this->user = User::factory()->create();

        EkadConfiguration::create([
            'base_url' => 'http://seekink.test/cloud/prod-api',
            'username' => 'moe',
            'password' => 'secret',
            'template_id' => '2000477842757914624',
            'bearer_token' => 'token',
            'token_expires_at' => now()->addHour(),
            'auto_push_enabled' => true,
            'is_active' => true,
        ]);
        IsolationType::create(['code' => 'CI', 'name' => 'Contact Isolation', 'is_active' => true]);

        $type = WardType::create(['code' => 'ICU', 'name' => 'ICU type', 'is_critical_care' => true, 'is_active' => true]);
        $this->icu = Ward::create([
            'hospital_id' => Hospital::create(['name' => 'Test Hospital'])->id,
            'ward_code' => 'ICU', 'ward_name' => 'Intensive Care Unit', 'ward_type_id' => $type->id, 'is_active' => true,
        ]);
        $this->bed = Bed::create([
            'ward_id' => $this->icu->id, 'bed_number' => 'ICU 4', 'bed_id' => 'ICU-4',
            'bed_display_name' => 'ICU 4', 'status' => 'available', 'is_active' => true,
        ]);
        EkadBedMapping::create(['bed_id' => $this->bed->id, 'mac_address' => 'D43D393CD4F2', 'template_id' => self::ICU_TEMPLATE]);
    }

    private function makePatient(): Patient
    {
        return Model::withoutEvents(fn () => Patient::create([
            'name' => 'Chong Kar Wai', 'mrn' => 'MRN000005', 'rn' => 'RN-000005', 'ic_passport' => '800101-10-0005',
            'age' => 61, 'gender' => 'Male', 'phone' => '012-0000000', 'status' => 'active', 'is_active' => true,
            'isolation_type' => 'CI',
            'allergies' => ['Penicillin', ['allergen' => 'SEA^Seafood', 'status' => 'Active'], ['allergen' => 'Latex', 'status' => 'Resolved']],
        ]));
    }

    private function saveIcuFields(array $fields = self::ICU_FIELDS)
    {
        return $this->actingAs($this->user)->putJson(route('ekad.templates.save', self::ICU_TEMPLATE), [
            'name' => 'ICU 7.5-inch', 'fields' => $fields,
        ]);
    }

    /** The data of each paint, in order */
    private function paintedData(): array
    {
        return Http::recorded(fn (HttpRequest $request) => str_ends_with($request->url(), '/batchPaintingByJson'))
            ->map(fn (array $sent) => $sent[0]['data'][0])
            ->values()->all();
    }

    public function test_a_template_nobody_edited_gets_the_default_fields_isolation_included(): void
    {
        $patient = $this->makePatient();

        $this->actingAs($this->user)->post(route('ward.admit-patient'), [
            'patient_id' => $patient->id, 'ward_id' => $this->icu->id, 'bed_number' => 'ICU 4',
        ])->assertSessionHas('success');

        $this->assertSame([[
            'bed no' => 'ICU 4', 'MRN' => 'MRN000005', 'patient_name' => 'CHONG KAR WAI', 'diet_type' => '-',
            'doctor' => '-', 'nurse' => '-', 'anaesthetist' => '-', 'isolation_type' => 'CONTACT ISOLATION',
        ]], $this->paintedData());
    }

    public function test_a_templates_own_fields_decide_what_its_screens_are_sent(): void
    {
        $this->saveIcuFields()->assertOk()->assertJsonPath('template.fields', self::ICU_FIELDS);
        $patient = $this->makePatient();

        $this->actingAs($this->user)->post(route('ward.admit-patient'), [
            'patient_id' => $patient->id, 'ward_id' => $this->icu->id, 'bed_number' => 'ICU 4',
        ])->assertSessionHas('success');
        $this->actingAs($this->user)->post(route('ward.discharge-patient'), ['patient_id' => $patient->id])
            ->assertSessionHas('success');

        $this->assertSame([
            ['bed' => 'ICU 4', 'name' => 'CHONG KAR WAI', 'iso' => 'CONTACT ISOLATION', 'allergy' => 'PENICILLIN, SEAFOOD',
                'unit' => 'INTENSIVE CARE UNIT', 'nurse_on_duty' => '-', 'title' => 'SMART WARD'],
            // An empty bed: its number and ward and the fixed text; "-" for the patient's fields
            ['bed' => 'ICU 4', 'name' => '-', 'iso' => '-', 'allergy' => '-', 'unit' => 'INTENSIVE CARE UNIT',
                'nurse_on_duty' => '-', 'title' => 'SMART WARD'],
        ], $this->paintedData());

        // The ward's other templates keep the default fields
        $this->assertSame(EkadTemplate::DEFAULT_FIELDS, EkadTemplate::fieldsFor('2000477842757914624'));
    }

    public function test_overrides_still_reach_a_renamed_field(): void
    {
        $this->saveIcuFields()->assertOk();
        $patient = $this->makePatient();

        // The shift change command sends the incoming shift's nurse as the "nurse" override
        $data = (new EkadService(EkadConfiguration::getActive()))
            ->getPatientInfoPayload($patient, $this->bed, ['nurse' => 'Siti Aminah'], self::ICU_TEMPLATE);

        $this->assertSame('SITI AMINAH', $data['nurse_on_duty']);
    }

    public function test_template_fields_are_checked_before_they_are_saved(): void
    {
        $this->saveIcuFields([['key' => 'bed', 'source' => 'bed_number'], ['key' => 'bed', 'source' => 'mrn']])
            ->assertUnprocessable()->assertJsonValidationErrors('fields.1.key');
        $this->saveIcuFields([['key' => 'bed', 'source' => 'salary']])
            ->assertUnprocessable()->assertJsonValidationErrors('fields.0.source');
        $this->saveIcuFields([])->assertUnprocessable()->assertJsonValidationErrors('fields');
        $this->actingAs($this->user)->putJson('/ekad/templates/not a template', ['fields' => self::ICU_FIELDS])->assertNotFound();

        $this->assertSame(0, EkadTemplate::count());
    }

    public function test_the_page_lists_the_templates_and_the_sync_preview_follows_their_fields(): void
    {
        $this->saveIcuFields()->assertOk();
        $patient = $this->makePatient();
        Model::withoutEvents(function () use ($patient) {
            $patient->update(['ward_id' => $this->icu->id, 'bed_number' => 'ICU 4', 'status' => Patient::STATUS_ADMITTED, 'admitted_at' => now()]);
            $this->bed->update(['status' => 'occupied', 'patient_id' => $patient->id]);
        });

        $page = $this->actingAs($this->user)->get(route('ekad.index'))->assertOk()
            ->assertSee('Add Template')
            ->assertSee('Template Fields');
        $this->assertSame([self::ICU_TEMPLATE], $page->viewData('templates')->pluck('template_id')->all());
        $this->assertContains(['source' => 'isolation', 'label' => 'Isolation'], $page->viewData('templateSources')->all());

        $row = $this->actingAs($this->user)->getJson(route('ekad.sync-preview'))->assertOk()->json('data.0');
        $this->assertSame(['bed', 'name', 'iso', 'allergy', 'unit', 'nurse_on_duty', 'title'], array_keys($row['payload']));
        $this->assertSame(['CHONG KAR WAI', 'Occupied'], [$row['patient'], $row['status']]);
    }
}
