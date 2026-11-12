<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Hospital;
use App\Models\User;
use App\Models\Ward;
use App\Services\CommandCenterSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A ward's beds follow it: deactivating the ward takes them out of use (the
 * Beds page, Command Center V2 and every count of active beds), activating it
 * brings them back, and deleting it deletes them.
 */
class WardBedsFollowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Hospital $hospital;
    private Ward $ward;
    private Ward $other;

    protected function setUp(): void
    {
        parent::setUp();
        // The pages' figures are under test, not their built assets
        $this->withoutVite();

        $this->admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $this->hospital = Hospital::create(['name' => 'Test Hospital']);
        $this->ward = $this->ward('W5', 'Ward 5');
        $this->other = $this->ward('W6', 'Ward 6');
        foreach (['W5-01', 'W5-02', 'W5-03'] as $number) {
            $this->bed($this->ward, $number);
        }
        $this->bed($this->other, 'W6-01');
        // Taken out of use on its own, before the ward was
        $this->bedNamed('W5-03')->update(['is_active' => false]);
    }

    private function ward(string $code, string $name): Ward
    {
        return Ward::create(['hospital_id' => $this->hospital->id, 'ward_code' => $code, 'ward_name' => $name, 'capacity' => 4, 'is_active' => true]);
    }

    private function bed(Ward $ward, string $number): Bed
    {
        return Bed::create([
            'ward_id' => $ward->id, 'bed_number' => $number, 'bed_id' => $number, 'bed_display_name' => $number,
            'status' => 'available', 'is_active' => true,
        ]);
    }

    private function bedNamed(string $number): Bed
    {
        return Bed::where('bed_number', $number)->firstOrFail();
    }

    /** @return array<string, bool> bed number => active */
    private function activeBeds(): array
    {
        return Bed::orderBy('bed_number')->pluck('is_active', 'bed_number')->map(fn ($active) => (bool) $active)->all();
    }

    public function test_deactivating_a_ward_takes_its_beds_with_it_and_activating_brings_them_back(): void
    {
        $this->actingAs($this->admin)->post(route('wards.deactivate', $this->ward))
            ->assertRedirect(route('wards.index'))
            ->assertSessionHas('success', 'Ward deactivated successfully. 2 beds in it were deactivated with it.');

        $this->assertSame(['W5-01' => false, 'W5-02' => false, 'W5-03' => false, 'W6-01' => true], $this->activeBeds());
        $this->assertTrue($this->bedNamed('W5-01')->deactivated_with_ward);
        $this->assertFalse($this->bedNamed('W5-03')->deactivated_with_ward);

        $this->actingAs($this->admin)->post(route('wards.deactivate', $this->ward))
            ->assertSessionHas('success', 'Ward activated successfully. 2 beds in it are back in use.');

        // The bed deactivated on its own stays so
        $this->assertSame(['W5-01' => true, 'W5-02' => true, 'W5-03' => false, 'W6-01' => true], $this->activeBeds());
        $this->assertFalse($this->bedNamed('W5-01')->deactivated_with_ward);
    }

    public function test_the_beds_page_shows_beds_out_of_use_with_their_ward(): void
    {
        $this->ward->update(['is_active' => false]);

        $this->actingAs($this->admin)->get(route('beds.index'))
            ->assertOk()
            ->assertSee('Ward deactivated')
            ->assertSee('title="Out of use with its ward">Inactive', false)
            ->assertSee('Its ward is deactivated: activate the ward to bring its beds back');
    }

    public function test_a_bed_in_a_deactivated_ward_cannot_be_activated_on_its_own(): void
    {
        $this->ward->update(['is_active' => false]);
        $bed = $this->bedNamed('W5-01');

        $this->actingAs($this->admin)->from(route('beds.index', ['page' => 2]))->post(route('beds.deactivate', $bed))
            ->assertRedirect(route('beds.index', ['page' => 2]))
            ->assertSessionHas('error');
        $this->assertFalse($bed->fresh()->is_active);

        // Switched by hand once the ward is back, it no longer follows the ward's switch
        $this->ward->update(['is_active' => true]);
        $this->actingAs($this->admin)->post(route('beds.deactivate', $bed))->assertSessionHas('success', 'Bed deactivated successfully.');
        $this->ward->update(['is_active' => false]);
        $this->ward->update(['is_active' => true]);
        $this->assertFalse($bed->fresh()->is_active);
    }

    public function test_beds_made_in_or_moved_between_wards_follow_the_ward_they_are_in(): void
    {
        $this->ward->update(['is_active' => false]);

        // As the C+ sync or the Add Bed form makes one
        $new = $this->bed($this->ward, 'W5-04');
        $this->assertFalse($new->fresh()->is_active);
        $this->assertTrue($new->fresh()->deactivated_with_ward);

        // Moved to an active ward, it comes back; moved into the deactivated one, it goes out of use
        $new->update(['ward_id' => $this->other->id]);
        $this->assertTrue($new->fresh()->is_active);
        $moved = $this->bedNamed('W6-01');
        $moved->update(['ward_id' => $this->ward->id]);
        $this->assertFalse($moved->fresh()->is_active);
    }

    public function test_deleting_a_ward_deletes_its_beds(): void
    {
        $this->ward->delete();

        $this->assertSame(['W6-01' => true], $this->activeBeds());
    }

    public function test_command_center_v2_and_bed_counts_leave_out_a_deactivated_ward(): void
    {
        $before = CommandCenterSummary::build();
        $this->assertContains('Ward 5', array_column($before['wards'], 'name'));
        $this->assertSame(3, $before['capacity']['beds']);

        $this->ward->update(['is_active' => false]);

        $after = CommandCenterSummary::build();
        $this->assertNotContains('Ward 5', array_column($after['wards'], 'name'));
        $this->assertSame(1, $after['capacity']['beds']);

        // The first Command Center counts every active bed in the hospital
        $this->actingAs($this->admin)->get(route('command-center.index'))
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats) => $stats['total_beds'] === 1);
    }

    public function test_the_migration_brings_beds_already_in_deactivated_wards_in_line(): void
    {
        // As on a server whose wards were deactivated before beds followed them
        DB::table('wards')->where('id', $this->ward->id)->update(['is_active' => false]);
        $this->assertTrue($this->bedNamed('W5-01')->is_active);

        (require database_path('migrations/2026_11_12_000003_add_deactivated_with_ward_to_beds_table.php'))->up();

        $this->assertSame(['W5-01' => false, 'W5-02' => false, 'W5-03' => false, 'W6-01' => true], $this->activeBeds());
        $this->ward->refresh()->update(['is_active' => true]);
        $this->assertSame(['W5-01' => true, 'W5-02' => true, 'W5-03' => false, 'W6-01' => true], $this->activeBeds());
    }

    public function test_a_server_that_has_not_migrated_keeps_its_beds_as_they_are(): void
    {
        Schema::partialMock()->shouldReceive('hasColumn')->andReturn(false);

        $this->actingAs($this->admin)->post(route('wards.deactivate', $this->ward))
            ->assertSessionHas('success', 'Ward deactivated successfully.');
        $this->assertSame(['W5-01' => true, 'W5-02' => true, 'W5-03' => false, 'W6-01' => true], $this->activeBeds());

        $this->actingAs($this->admin)->post(route('beds.deactivate', $this->bedNamed('W5-03')))
            ->assertSessionHas('success', 'Bed activated successfully.');
    }
}
