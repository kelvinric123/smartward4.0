<?php

namespace Tests\Feature;

use App\Models\Hospital;
use App\Models\User;
use App\Support\HospitalTheme;
use App\Support\NavigationMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The hospital edit page's Theme & Menu tab: the theme colours that paint the
 * app's brand gradient, and the sidebar items a hospital hides.
 */
class HospitalThemeSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Hospital $hospital;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $this->hospital = Hospital::create(['name' => 'Test Hospital']);
    }

    /** The details fields the form always sends, plus the given theme and menu fields. */
    private function update(array $fields): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->put(route('hospitals.update', $this->hospital), [
            'name' => 'Test Hospital',
            ...$fields,
        ]);
    }

    /** Every menu item switched on, as the form sends it. */
    private function allShown(): array
    {
        return array_fill_keys(NavigationMenu::keys(), '1');
    }

    public function test_edit_page_has_details_and_theme_tabs(): void
    {
        $this->actingAs($this->admin)->get(route('hospitals.edit', $this->hospital))
            ->assertOk()
            ->assertSee('Hospital Details')
            ->assertSee('Theme &amp; Menu', false)
            ->assertSee('name="theme_primary_color"', false)
            ->assertSee('name="menu[command-center]"', false);
    }

    public function test_only_admins_can_open_or_change_hospital_settings(): void
    {
        $nurse = User::factory()->create(['role' => User::ROLE_NURSE]);

        $this->actingAs($nurse)->get(route('hospitals.edit', $this->hospital))->assertForbidden();
        $this->actingAs($nurse)->put(route('hospitals.update', $this->hospital), [
            'name' => 'Renamed',
            'theme_primary_color' => '#047857',
            'theme_secondary_color' => '#10b981',
        ])->assertForbidden();

        $this->assertSame('Test Hospital', $this->hospital->fresh()->name);
        $this->assertNull($this->hospital->fresh()->theme_primary_color);

        foreach ([User::ROLE_HOSPITAL_ADMIN, User::ROLE_IT_ADMIN] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('hospitals.edit', $this->hospital))->assertOk();
        }
    }

    public function test_saving_the_theme_tab_stores_colours_and_hidden_items(): void
    {
        $this->update([
            'tab' => 'theme',
            'theme_primary_color' => '#047857',
            'theme_secondary_color' => '#10B981',
            'menu' => [...$this->allShown(), 'command-center' => '0', 'integration-demo' => '0'],
        ])->assertRedirect(route('hospitals.edit', ['hospital' => $this->hospital, 'tab' => 'theme']));

        $hospital = $this->hospital->fresh();
        $this->assertSame('#047857', $hospital->theme_primary_color);
        $this->assertSame('#10b981', $hospital->theme_secondary_color);
        $this->assertSame(['command-center', 'integration-demo'], $hospital->hidden_nav_items);
    }

    public function test_the_default_colours_are_stored_as_no_theme(): void
    {
        $this->hospital->update(['theme_primary_color' => '#047857', 'theme_secondary_color' => '#10b981']);

        $this->update([
            'theme_primary_color' => strtoupper(HospitalTheme::DEFAULT_PRIMARY),
            'theme_secondary_color' => HospitalTheme::DEFAULT_SECONDARY,
        ])->assertRedirect(route('hospitals.edit', $this->hospital));

        $this->assertNull($this->hospital->fresh()->theme_primary_color);
        $this->assertNull($this->hospital->fresh()->theme_secondary_color);
    }

    public function test_colours_must_be_hex_codes(): void
    {
        $this->update([
            'theme_primary_color' => 'red; } body { display: none',
            'theme_secondary_color' => '#10b981',
        ])->assertSessionHasErrors('theme_primary_color');

        $this->assertNull($this->hospital->fresh()->theme_secondary_color);
    }

    public function test_the_hospital_item_cannot_be_hidden(): void
    {
        $this->update(['menu' => [...$this->allShown(), 'hospitals' => '0', 'beds' => '0']]);

        $this->assertSame(['beds'], $this->hospital->fresh()->hidden_nav_items);
    }

    public function test_saving_the_details_tab_leaves_theme_and_menu_alone(): void
    {
        $this->hospital->update([
            'theme_primary_color' => '#047857',
            'theme_secondary_color' => '#10b981',
            'hidden_nav_items' => ['beds'],
        ]);

        $this->update(['phone' => '06-1234567'])->assertSessionHasNoErrors();

        $hospital = $this->hospital->fresh();
        $this->assertSame('06-1234567', $hospital->phone);
        $this->assertSame('#047857', $hospital->theme_primary_color);
        $this->assertSame(['beds'], $hospital->hidden_nav_items);
    }

    public function test_the_sidebar_leaves_out_hidden_items_and_empty_sections(): void
    {
        $page = fn () => $this->actingAs($this->admin)->get(route('hospitals.index'))->assertOk();
        // The whole href: /command-center-v2 starts with the Command Center's own address
        $commandCenter = 'href="' . route('command-center.index') . '"';

        $page()->assertSee($commandCenter, false)
            ->assertSee(route('user-activities.index'))
            ->assertSee("toggleSection('appLogs')", false);

        $this->hospital->update(['hidden_nav_items' => ['command-center', 'user-activities']]);

        $page()->assertDontSee($commandCenter, false)
            ->assertDontSee(route('user-activities.index'))
            ->assertDontSee("toggleSection('appLogs')", false)
            // Still reachable: hiding only takes it out of the menu
            ->assertSee(route('hospitals.index'));
        $this->actingAs($this->admin)->get(route('command-center.index'))->assertOk();
    }

    public function test_pages_carry_the_hospital_theme(): void
    {
        $this->actingAs($this->admin)->get(route('hospitals.index'))
            ->assertDontSee('html:root{', false);

        $this->hospital->update(['theme_primary_color' => '#047857', 'theme_secondary_color' => '#10b981']);

        $this->actingAs($this->admin)->get(route('hospitals.index'))
            ->assertSee('--brand-600: 4 120 87;', false)
            ->assertSee('--accent-500: 16 185 129;', false);

        auth()->logout();
        $this->get(route('login'))->assertSee('--brand-600: 4 120 87;', false);
    }

    public function test_the_shade_scale_keeps_the_chosen_colour_and_runs_light_to_dark(): void
    {
        $scale = HospitalTheme::scale('#047857', 600);

        $this->assertSame('4 120 87', $scale[600]);
        $lightness = array_map(fn ($rgb) => array_sum(explode(' ', $rgb)), $scale);
        $this->assertSame(array_values($lightness), collect($lightness)->sortDesc()->values()->all());
    }
}
