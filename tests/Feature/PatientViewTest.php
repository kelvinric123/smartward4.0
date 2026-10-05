<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientViewTest extends TestCase
{
    use RefreshDatabase;

    private function makePatient(array $overrides = []): Patient
    {
        return Patient::create(array_merge([
            'name' => 'Siti Aminah',
            'mrn' => 'MRN900001',
            'rn' => 'RN900001',
            'ic_passport' => '800101-14-5566',
            'age' => 45,
            'gender' => 'Female',
            'phone' => '012-3456789',
        ], $overrides));
    }

    public function test_index_links_to_view_instead_of_edit(): void
    {
        $patient = $this->makePatient();

        $this->actingAs(User::factory()->create())
            ->get(route('patients.index'))
            ->assertOk()
            ->assertSee(route('patients.show', $patient), false)
            ->assertDontSee(route('patients.edit', $patient), false);
    }

    public function test_view_renders_for_legacy_patient_without_new_fields(): void
    {
        $patient = $this->makePatient();

        $this->actingAs(User::factory()->create())
            ->get(route('patients.show', $patient))
            ->assertOk()
            ->assertSee('Siti Aminah')
            ->assertSee('Patient Details')
            ->assertSee('Admission Details')
            ->assertSee('Medical Info')
            ->assertSee('Payor / Insurance')
            ->assertSee('Charges')
            ->assertSee('No charges recorded')
            ->assertSee('No COE programme');
    }

    public function test_view_shows_admission_payor_coe_and_charges(): void
    {
        $patient = $this->makePatient([
            'status' => Patient::STATUS_ADMITTED,
            'admitted_at' => now()->subDays(3)->subHours(2),
            'expected_discharge_at' => now()->addDays(2),
            'estimated_length_of_stay' => 5,
            'payor_type' => 'insurance',
            'payor_name' => 'Acme Assurance',
            'payor_status' => 'approved',
            'payor_gl_amount' => 10000,
            'coe_indicators' => ['CCPC Breast', 'Chronic Kidney Disease'],
            'total_charges' => 8500,
            'deposit_paid' => 500,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('patients.show', $patient))
            ->assertOk()
            ->assertSee('Acme Assurance')
            ->assertSee('GL Approved')
            ->assertSee('CCPC Breast')
            ->assertSee('Chronic Kidney Disease')
            ->assertSee('RM 8,500.00')
            ->assertSee('85%')
            ->assertSee('3 days 2 hrs')
            ->assertSee('In 2 days');
    }

    public function test_edit_route_opens_details_section_on_view_page(): void
    {
        $patient = $this->makePatient();

        $this->actingAs(User::factory()->create())
            ->get(route('patients.edit', $patient))
            ->assertRedirect(route('patients.show', ['patient' => $patient, 'tab' => 'details', 'edit' => 'details']));
    }

    public function test_view_opens_the_tab_holding_the_requested_section(): void
    {
        $patient = $this->makePatient();
        $user = User::factory()->create();

        // ?tab= wins when it is a known tab
        $this->assertSame('billing', $this->actingAs($user)
            ->get(route('patients.show', ['patient' => $patient, 'tab' => 'billing']))
            ->assertOk()
            ->viewData('initialTab'));

        // otherwise the tab is derived from the section being edited
        $this->assertSame('billing', $this->actingAs($user)
            ->get(route('patients.show', ['patient' => $patient, 'edit' => 'charges']))
            ->assertOk()
            ->viewData('initialTab'));

        // and an unknown tab falls back to the overview
        $this->assertSame('overview', $this->actingAs($user)
            ->get(route('patients.show', ['patient' => $patient, 'tab' => 'bogus']))
            ->assertOk()
            ->viewData('initialTab'));
    }

    public function test_list_filters_by_status_tab_and_counts_each_tab(): void
    {
        $admitted = $this->makePatient(['status' => Patient::STATUS_ADMITTED, 'admitted_at' => now()->subDay()]);
        $prebook = $this->makePatient([
            'mrn' => 'MRN900003', 'rn' => 'RN900003', 'ic_passport' => '910303-10-3333',
            'name' => 'Prebooked Person', 'status' => Patient::STATUS_PREBOOK,
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('patients.index', ['status' => 'admitted']))
            ->assertOk()
            ->assertSee($admitted->name)
            ->assertDontSee($prebook->name);

        // Tab counts ignore the selected status tab
        $tabs = $response->viewData('tabs');
        $this->assertSame(2, $tabs['all']['count']);
        $this->assertSame(1, $tabs['admitted']['count']);
        $this->assertSame(1, $tabs['prebook']['count']);
        $this->assertSame(0, $tabs['discharged']['count']);
    }

    public function test_list_search_covers_ic_and_keeps_sorting(): void
    {
        $this->makePatient(['name' => 'Zainab Omar']);
        $other = $this->makePatient([
            'mrn' => 'MRN900004', 'rn' => 'RN900004', 'ic_passport' => '770707-08-4321',
            'name' => 'Ahmad Faiz',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('patients.index', ['search' => '770707']))
            ->assertOk()
            ->assertSee($other->name)
            ->assertDontSee('Zainab Omar');

        $sorted = $this->actingAs(User::factory()->create())
            ->get(route('patients.index', ['sort' => 'name', 'dir' => 'asc']))
            ->assertOk()
            ->viewData('patients');

        $this->assertSame(['Ahmad Faiz', 'Zainab Omar'], $sorted->pluck('name')->all());
    }

    public function test_payor_section_saves_only_payor_fields(): void
    {
        $patient = $this->makePatient();

        $this->actingAs(User::factory()->create())
            ->put(route('patients.update', $patient), [
                '_section' => 'payor',
                'payor_type' => 'corporate',
                'payor_name' => 'Example Sdn Bhd',
                'payor_policy_number' => 'POL-123',
                'payor_gl_number' => 'GL-777',
                'payor_gl_amount' => '15000.50',
                'payor_status' => 'gl_requested',
                'payor_remarks' => '',
            ])
            ->assertRedirect(route('patients.show', ['patient' => $patient, 'tab' => 'billing']))
            ->assertSessionHasNoErrors();

        $patient->refresh();
        $this->assertSame('corporate', $patient->payor_type);
        $this->assertSame('15000.50', $patient->payor_gl_amount);
        $this->assertSame('gl_requested', $patient->payor_status);
        $this->assertNull($patient->payor_remarks);
        $this->assertSame('Siti Aminah', $patient->name);
    }

    public function test_payor_section_rejects_unknown_status(): void
    {
        $patient = $this->makePatient();

        $this->actingAs(User::factory()->create())
            ->from(route('patients.show', $patient))
            ->put(route('patients.update', $patient), [
                '_section' => 'payor',
                'payor_status' => 'bogus',
            ])
            ->assertRedirect(route('patients.show', $patient))
            ->assertSessionHasErrors('payor_status');

        $this->assertNull($patient->fresh()->payor_status);
    }

    public function test_charges_section_stamps_update_time_and_accepts_blanks(): void
    {
        $patient = $this->makePatient();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('patients.update', $patient), [
                '_section' => 'charges',
                'total_charges' => '1234.5',
                'deposit_paid' => '',
            ])
            ->assertSessionHasNoErrors();

        $patient->refresh();
        $this->assertSame('1234.50', $patient->total_charges);
        $this->assertNull($patient->deposit_paid);
        $this->assertNotNull($patient->charges_updated_at);

        $this->actingAs($user)
            ->put(route('patients.update', $patient), ['_section' => 'charges', 'total_charges' => '-5'])
            ->assertSessionHasErrors('total_charges');
    }

    public function test_medical_section_stores_coe_indicators(): void
    {
        $patient = $this->makePatient();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('patients.update', $patient), [
                '_section' => 'medical',
                'coe_indicators' => [' CCPC Breast ', 'Chronic Kidney Disease', 'CCPC Breast'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(['CCPC Breast', 'Chronic Kidney Disease'], $patient->fresh()->coe_indicators);

        // Clearing every indicator stores null rather than an empty list
        $this->actingAs($user)
            ->put(route('patients.update', $patient), ['_section' => 'medical'])
            ->assertSessionHasNoErrors();

        $this->assertNull($patient->fresh()->coe_indicators);
    }

    public function test_admission_section_saves_discharge_plan(): void
    {
        $patient = $this->makePatient();

        $this->actingAs(User::factory()->create())
            ->put(route('patients.update', $patient), [
                '_section' => 'admission',
                'expected_discharge_at' => '2030-01-15T10:30',
                'estimated_length_of_stay' => '4',
            ])
            ->assertSessionHasNoErrors();

        $patient->refresh();
        $this->assertSame('2030-01-15 10:30', $patient->expected_discharge_at->format('Y-m-d H:i'));
        $this->assertSame(4, (int) $patient->estimated_length_of_stay);
    }

    public function test_details_section_keeps_existing_validation(): void
    {
        $patient = $this->makePatient();
        $this->makePatient(['mrn' => 'MRN900002', 'rn' => 'RN900002', 'ic_passport' => '900202-10-1234']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('patients.update', $patient), [
                '_section' => 'details',
                'name' => '',
                'mrn' => 'MRN900002',
            ])
            ->assertSessionHasErrors(['name', 'mrn', 'rn', 'ic_passport', 'age', 'gender', 'phone']);

        $this->actingAs($user)
            ->put(route('patients.update', $patient), [
                '_section' => 'details',
                'name' => 'Siti Aminah binti Ali',
                'mrn' => 'MRN900001',
                'rn' => 'RN900001',
                'ic_passport' => '800101-14-5566',
                'age' => 46,
                'gender' => 'Female',
                'phone' => '012-3456789',
                'race' => 'Malay',
            ])
            ->assertRedirect(route('patients.show', ['patient' => $patient, 'tab' => 'details']))
            ->assertSessionHasNoErrors();

        $patient->refresh();
        $this->assertSame('Siti Aminah binti Ali', $patient->name);
        $this->assertSame('Malay', $patient->race);
    }

    public function test_details_section_saves_vip_status_and_view_shows_it(): void
    {
        $patient = $this->makePatient();
        $user = User::factory()->create();
        $details = [
            '_section' => 'details',
            'name' => 'Siti Aminah',
            'mrn' => 'MRN900001',
            'rn' => 'RN900001',
            'ic_passport' => '800101-14-5566',
            'age' => 45,
            'gender' => 'Female',
            'phone' => '012-3456789',
        ];

        $this->actingAs($user)
            ->put(route('patients.update', $patient), $details + ['vip_status' => 'vvip'])
            ->assertSessionHasNoErrors();
        $patient->refresh();
        $this->assertSame('vvip', $patient->vip_status);

        // The badge, not just the option in the edit form
        $this->actingAs($user)
            ->get(route('patients.show', $patient))
            ->assertOk()
            ->assertSee($patient->vipStatusBadgeClass() . '">VVIP</span>', false);

        // Unknown statuses are refused; a blank choice clears it
        $this->actingAs($user)
            ->put(route('patients.update', $patient), $details + ['vip_status' => 'royal'])
            ->assertSessionHasErrors('vip_status');
        $this->assertSame('vvip', $patient->fresh()->vip_status);

        $this->actingAs($user)
            ->put(route('patients.update', $patient), $details + ['vip_status' => ''])
            ->assertSessionHasNoErrors();
        $this->assertNull($patient->fresh()->vip_status);
    }

    public function test_alias_name_is_optional_saved_shown_and_searchable(): void
    {
        $patient = $this->makePatient();
        $user = User::factory()->create();

        // Optional: an existing patient without one still saves
        $this->actingAs($user)
            ->put(route('patients.update', $patient), [
                '_section' => 'details',
                'name' => 'Siti Aminah',
                'alias_name' => '',
                'mrn' => 'MRN900001',
                'rn' => 'RN900001',
                'ic_passport' => '800101-14-5566',
                'age' => 45,
                'gender' => 'Female',
                'phone' => '012-3456789',
            ])
            ->assertSessionHasNoErrors();
        $this->assertNull($patient->fresh()->alias_name);

        $this->actingAs($user)
            ->put(route('patients.update', $patient), [
                '_section' => 'details',
                'name' => 'Siti Aminah',
                'alias_name' => 'Kak Ti',
                'mrn' => 'MRN900001',
                'rn' => 'RN900001',
                'ic_passport' => '800101-14-5566',
                'age' => 45,
                'gender' => 'Female',
                'phone' => '012-3456789',
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame('Kak Ti', $patient->fresh()->alias_name);

        $this->actingAs($user)
            ->get(route('patients.show', $patient))
            ->assertOk()
            ->assertSee('Kak Ti');

        // The list can be searched by alias
        $this->makePatient(['mrn' => 'MRN900005', 'rn' => 'RN900005', 'ic_passport' => '850505-02-7777', 'name' => 'Other Patient']);

        $this->actingAs($user)
            ->get(route('patients.index', ['search' => 'Kak Ti']))
            ->assertOk()
            ->assertSee('Kak Ti')
            ->assertDontSee('Other Patient');
    }

    public function test_new_patient_can_be_created_with_an_alias(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('patients.store'), [
                'name' => 'Tan Wei Ming',
                'alias_name' => 'Ah Ming',
                'mrn' => 'MRN900006',
                'rn' => 'RN900006',
                'ic_passport' => '880808-05-1212',
                'age' => 38,
                'gender' => 'Male',
                'phone' => '013-2223333',
            ])
            ->assertRedirect(route('patients.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Ah Ming', Patient::where('mrn', 'MRN900006')->value('alias_name'));
    }

    public function test_unknown_section_is_rejected(): void
    {
        $patient = $this->makePatient();

        $this->actingAs(User::factory()->create())
            ->put(route('patients.update', $patient), ['_section' => 'status', 'status' => 'discharged'])
            ->assertStatus(422);

        $this->assertSame(Patient::STATUS_PREBOOK, $patient->fresh()->status);
    }
}
