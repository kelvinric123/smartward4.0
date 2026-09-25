<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\PatientMovement;
use App\Models\User;
use App\Models\Ward;
use App\Models\WardType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CriticalCareDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Hospital $hospital;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->hospital = Hospital::create(['name' => 'Test Hospital']);
    }

    private function makeType(string $code, bool $criticalCare): WardType
    {
        return WardType::create(['code' => $code, 'name' => $code . ' type', 'is_critical_care' => $criticalCare, 'is_active' => true]);
    }

    private function makeWard(string $code, string $name, ?WardType $type, bool $active = true): Ward
    {
        return Ward::create([
            'hospital_id' => $this->hospital->id, 'ward_code' => $code, 'ward_name' => $name,
            'ward_type_id' => $type?->id, 'is_active' => $active,
        ]);
    }

    private function admitWithMovement(Ward $ward, string $bedNumber): PatientMovement
    {
        Bed::create([
            'ward_id' => $ward->id, 'bed_number' => $bedNumber, 'bed_id' => $ward->ward_code . '-' . $bedNumber,
            'bed_display_name' => $bedNumber, 'status' => 'available', 'is_active' => true,
        ]);

        $patient = Patient::create([
            'name' => 'Lim Ah Kow', 'mrn' => 'MRN80001', 'rn' => 'RN80001', 'ic_passport' => '700101-10-5001',
            'age' => 56, 'gender' => 'Male', 'phone' => '012-0000000',
            'ward_id' => $ward->id, 'bed_number' => $bedNumber, 'status' => Patient::STATUS_ADMITTED, 'is_active' => true,
            'admitted_at' => now()->subDay(),
        ]);

        return PatientMovement::create([
            'patient_id' => $patient->id, 'ward_id' => $ward->id, 'bed_number' => $bedNumber,
            'location' => 'Radiology', 'location_type' => 'radiology',
            'scheduled_at' => now()->subHour(), 'sent_at' => now()->subHour(), 'status' => 'sent',
        ]);
    }

    public function test_ward_type_form_saves_the_critical_care_choice(): void
    {
        $this->actingAs($this->user)->post(route('ward-types.store'), [
            'code' => 'cicu', 'name' => 'Cardiac ICU', 'is_critical_care' => '1',
        ])->assertRedirect(route('ward-types.index'));

        $type = WardType::where('code', 'CICU')->firstOrFail();
        $this->assertTrue($type->is_critical_care);

        $this->actingAs($this->user)->put(route('ward-types.update', $type), [
            'code' => 'CICU', 'name' => 'Cardiac ICU', 'is_critical_care' => '0',
        ])->assertRedirect(route('ward-types.index'));
        $this->assertFalse($type->fresh()->is_critical_care);

        // A form without the field (opened before this change) keeps the saved choice; a new type defaults to No
        $type->refresh()->update(['is_critical_care' => true]);
        $this->actingAs($this->user)->put(route('ward-types.update', $type), ['code' => 'CICU', 'name' => 'Cardiac ICU']);
        $this->assertTrue($type->fresh()->is_critical_care);

        $this->actingAs($this->user)->post(route('ward-types.store'), ['code' => 'GEN', 'name' => 'General']);
        $this->assertFalse(WardType::where('code', 'GEN')->firstOrFail()->is_critical_care);
    }

    public function test_ward_type_pages_show_the_critical_care_choice(): void
    {
        $icu = $this->makeType('ICU', true);
        $this->makeType('MED', false);

        $index = $this->actingAs($this->user)->get(route('ward-types.index'))->assertOk();
        $this->assertSame(1, substr_count($index->getContent(), 'Wards of this type are on the Critical Care Ward Dashboard'));

        $edit = $this->actingAs($this->user)->get(route('ward-types.edit', $icu))->assertOk()->assertSee('Critical Care Ward');
        $this->assertMatchesRegularExpression('/name="is_critical_care" value="1"\s+checked/', $edit->getContent());
        $this->assertDoesNotMatchRegularExpression('/name="is_critical_care" value="0"\s+checked/', $edit->getContent());

        $create = $this->actingAs($this->user)->get(route('ward-types.create'))->assertOk();
        $this->assertMatchesRegularExpression('/name="is_critical_care" value="0"\s+checked/', $create->getContent());
    }

    public function test_critical_care_dashboard_lists_only_active_critical_care_wards(): void
    {
        $icuType = $this->makeType('ICU', true);
        $icu = $this->makeWard('ICU1', 'Intensive Care Unit', $icuType);
        $hdu = $this->makeWard('HDU1', 'High Dependency Unit', $this->makeType('HDU', true));
        $this->makeWard('ICU2', 'Closed ICU', $icuType, false);
        $medical = $this->makeWard('MW1', 'Medical Ward 1', $this->makeType('MED', false));
        $untyped = $this->makeWard('NW1', 'New Ward', null);

        $response = $this->actingAs($this->user)->get(route('critical-care.dashboard'))->assertOk();

        $this->assertEquals([$icu->id, $hdu->id], $response->viewData('wards')->pluck('id')->all());
        $this->assertTrue($response->viewData('selectedWard')->is($icu));
        $response->assertSee('Critical Care Ward Dashboard')
            ->assertSee('High Dependency Unit')
            ->assertDontSee('Medical Ward 1')
            ->assertDontSee('Closed ICU');

        // The ward dashboard itself still lists every active ward
        $all = $this->actingAs($this->user)->get(route('ward.dashboard'))->assertOk();
        $this->assertEquals([$icu->id, $hdu->id, $medical->id, $untyped->id], $all->viewData('wards')->pluck('id')->all());
    }

    public function test_a_ward_outside_the_list_falls_back_to_the_first_critical_care_ward(): void
    {
        $icuType = $this->makeType('ICU', true);
        $icu = $this->makeWard('ICU1', 'Intensive Care Unit', $icuType);
        $hdu = $this->makeWard('HDU1', 'High Dependency Unit', $icuType);
        $medical = $this->makeWard('MW1', 'Medical Ward 1', $this->makeType('MED', false));

        $picked = $this->actingAs($this->user)->get(route('critical-care.dashboard', ['ward_id' => $hdu->id]));
        $this->assertTrue($picked->viewData('selectedWard')->is($hdu));

        $outside = $this->actingAs($this->user)->get(route('critical-care.dashboard', ['ward_id' => $medical->id]));
        $this->assertTrue($outside->viewData('selectedWard')->is($icu));

        // The ward dashboard still opens whichever ward it is asked for
        $ward = $this->actingAs($this->user)->get(route('ward.dashboard', ['ward_id' => $medical->id]));
        $this->assertTrue($ward->viewData('selectedWard')->is($medical));
    }

    public function test_without_critical_care_wards_the_page_says_how_to_add_them(): void
    {
        $this->makeWard('MW1', 'Medical Ward 1', $this->makeType('MED', false));

        // No ward type marked yet: sends the user to Ward Types
        $response = $this->actingAs($this->user)->get(route('critical-care.dashboard'))
            ->assertOk()
            ->assertSee('No critical care wards')
            ->assertSee('No Critical Care Wards')
            ->assertSee('No ward type is marked as critical care yet.')
            ->assertSee('Go to Ward Types');

        $this->assertNull($response->viewData('selectedWard'));

        // Types marked, but only an inactive ward uses one: names the types and sends the user to Wards
        $this->makeWard('ICU9', 'Old ICU', $this->makeType('ICU', true), false);
        $this->makeType('HDU', true);

        $this->actingAs($this->user)->get(route('critical-care.dashboard'))
            ->assertOk()
            ->assertSee('HDU type and ICU type are marked as critical care, but no active ward uses them yet.')
            ->assertSee('set its Ward Type to HDU type or ICU type.')
            ->assertSee(route('wards.index'))
            ->assertSee('Go to Wards')
            ->assertDontSee('No ward type is marked as critical care yet.');
    }

    public function test_sidebar_links_to_the_critical_care_dashboard_below_the_ward_dashboard(): void
    {
        $this->actingAs($this->user)->get(route('ward-types.index'))
            ->assertOk()
            ->assertSeeInOrder([
                route('ward.dashboard'), 'Ward Dashboard',
                route('critical-care.dashboard'), 'Critical Care Ward Dashboard',
            ]);
    }

    public function test_mark_returned_goes_back_to_the_dashboard_it_came_from(): void
    {
        $icu = $this->makeWard('ICU1', 'Intensive Care Unit', $this->makeType('ICU', true));
        $movement = $this->admitWithMovement($icu, 'B01');

        // The copied view's Mark Returned form names the critical care dashboard
        $this->actingAs($this->user)->get(route('critical-care.dashboard'))
            ->assertOk()
            ->assertSee('Mark Returned')
            ->assertSee('name="from_dashboard" value="critical-care"', false);

        $this->actingAs($this->user)
            ->post(route('ward.patient-movements.return', $movement), ['from_dashboard' => 'critical-care', 'ward_id' => $icu->id])
            ->assertRedirect(route('critical-care.dashboard', ['ward_id' => $icu->id]));

        $movement->update(['status' => 'sent', 'returned_at' => null]);

        $this->actingAs($this->user)
            ->post(route('ward.patient-movements.return', $movement), ['from_dashboard' => '1', 'ward_id' => $icu->id])
            ->assertRedirect(route('ward.dashboard', ['ward_id' => $icu->id]));
    }
}
