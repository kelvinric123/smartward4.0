<?php

namespace Tests\Feature;

use App\Models\Hospital;
use App\Models\User;
use App\Support\HospitalTheme;
use App\Support\UserTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Profile -> Appearance: each user's display mode (Normal, Dark, Sol, System)
 * and colours (the hospital's theme, a preset or their own three).
 */
class UserThemeTest extends TestCase
{
    use RefreshDatabase;

    /** The inline script <x-theme-style> uses to set a mode */
    private function modeScript(string $mode): string
    {
        return "})('{$mode}', document.documentElement)";
    }

    private function saveTheme(User $user, array $input)
    {
        return $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.theme.update'), $input);
    }

    public function test_a_new_user_keeps_the_hospital_theme_in_normal_mode(): void
    {
        $user = User::factory()->create();

        $this->assertSame(
            ['mode' => 'light', 'colours' => 'hospital', 'primary' => null, 'secondary' => null, 'background' => null],
            UserTheme::preferences($user)
        );
        $this->assertNull(UserTheme::css($user, null), 'nothing to write for the default theme in Normal mode');

        $this->actingAs($user)
            ->get(route('patients.index'))
            ->assertOk()
            ->assertDontSee('data-theme-mode', false);
    }

    public function test_the_profile_page_offers_the_modes_and_colours_and_can_try_every_mode(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Appearance')
            ->assertSeeInOrder(['Normal', 'Dark', 'Sol', 'System'])
            ->assertSeeInOrder(['Hospital theme', 'Ocean', 'Sunset', 'Graphite', 'Custom'])
            ->assertSee('Your own colours')
            // Dark and Sol variants for the theme's colours, so picking a mode shows it at once
            ->assertSee('html:root[data-theme-mode="dark"]', false)
            ->assertSee('html:root[data-theme-mode="sol"]', false);
    }

    public function test_dark_mode_with_a_preset_applies_on_every_page(): void
    {
        $user = User::factory()->create();

        $this->saveTheme($user, ['mode' => 'dark', 'colours' => 'sunset'])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'theme-updated')
            ->assertSessionHasNoErrors();

        $this->assertSame(['mode' => 'dark', 'colours' => 'sunset'], $user->fresh()->theme);

        $this->actingAs($user->fresh())
            ->get(route('patients.index'))
            ->assertOk()
            ->assertSee($this->modeScript('dark'), false)
            // Sunset: primary #c2410c, background #f97316, with their dark variants
            ->assertSee('--brand-600: 194 65 12;', false)
            ->assertSee('--backdrop-600: 249 115 22;', false)
            ->assertSee('html:root[data-theme-mode="dark"]{--bg-brand-50: ', false)
            ->assertDontSee('html:root[data-theme-mode="sol"]', false);
    }

    public function test_custom_colours_are_stored_in_lower_case_and_colour_the_page(): void
    {
        $user = User::factory()->create();

        $this->saveTheme($user, [
            'mode' => 'sol', 'colours' => 'custom',
            'primary' => '#0F766E', 'secondary' => '#14B8A6', 'background' => '#F59E0B',
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            ['mode' => 'sol', 'colours' => 'custom', 'primary' => '#0f766e', 'secondary' => '#14b8a6', 'background' => '#f59e0b'],
            $user->fresh()->theme
        );

        $css = UserTheme::css($user->fresh(), null);
        $this->assertStringContainsString('--brand-600: 15 118 110;', $css);
        $this->assertStringContainsString('--accent-500: 20 184 166;', $css);
        $this->assertStringContainsString('--backdrop-600: 245 158 11;', $css);
        $this->assertStringContainsString('html:root[data-theme-mode="sol"]{', $css);
        $this->assertStringNotContainsString('html:root[data-theme-mode="dark"]', $css);

        // A preset's colours are not stored, so it follows any later change to the preset
        $this->saveTheme($user, ['mode' => 'light', 'colours' => 'teal', 'primary' => '#000000', 'secondary' => '#000000', 'background' => '#000000'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['mode' => 'light', 'colours' => 'teal'], $user->fresh()->theme);
    }

    public function test_unknown_or_incomplete_choices_are_refused(): void
    {
        $user = User::factory()->create();

        $this->saveTheme($user, ['mode' => 'neon', 'colours' => 'plaid'])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrorsIn('updateTheme', ['mode', 'colours']);

        $this->saveTheme($user, ['mode' => 'dark', 'colours' => 'custom', 'primary' => 'red'])
            ->assertSessionHasErrorsIn('updateTheme', ['primary', 'secondary', 'background']);

        $this->assertNull($user->fresh()->theme);
    }

    public function test_a_users_own_colours_win_over_the_hospital_theme(): void
    {
        $hospital = Hospital::create(['name' => 'Test Hospital', 'theme_primary_color' => '#047857', 'theme_secondary_color' => '#10b981']);

        // Following the hospital: its colours, the background following its primary
        $user = User::factory()->create();
        $this->assertSame(['#047857', '#10b981', '#047857'], UserTheme::colours($user, $hospital));
        $this->assertStringContainsString('--brand-600: 4 120 87;', UserTheme::css($user, $hospital));
        $this->assertStringNotContainsString('--backdrop-', UserTheme::css($user, $hospital), 'the backdrop follows the brand');

        $rose = User::factory()->create(['theme' => ['mode' => 'light', 'colours' => 'rose']]);
        $this->assertSame(['#be123c', '#f43f5e', '#fb7185'], UserTheme::colours($rose, $hospital));

        // The hospital's admin page still sets everyone's default
        $this->assertSame(['#047857', '#10b981'], HospitalTheme::colours($hospital));
    }

    public function test_system_mode_follows_the_device(): void
    {
        $user = User::factory()->create(['theme' => ['mode' => 'system', 'colours' => 'hospital']]);

        $css = UserTheme::css($user, null);
        $this->assertStringContainsString('html:root[data-theme-mode="dark"]', $css, 'ready for the device going dark');
        $this->assertStringNotContainsString('--brand-600:', $css, 'default colours are already compiled');

        $this->actingAs($user)
            ->get(route('patients.index'))
            ->assertOk()
            ->assertSee($this->modeScript('system'), false)
            ->assertSee("matchMedia('(prefers-color-scheme: dark)')", false);
    }

    public function test_pages_outside_the_main_layout_follow_the_mode_too(): void
    {
        $user = User::factory()->create(['theme' => ['mode' => 'dark', 'colours' => 'hospital']]);

        // Signed out, the login page is always Normal
        $this->get(route('login'))->assertOk()->assertDontSee('data-theme-mode', false);

        // The patient details panel opens in a frame on the ward dashboard
        $patient = \App\Models\Patient::create([
            'name' => 'Siti Aminah', 'mrn' => 'MRN94001', 'rn' => 'RN94001', 'ic_passport' => '800101-14-5566',
            'age' => 45, 'gender' => 'Female', 'phone' => '012-3456789', 'is_active' => true,
        ]);
        $this->actingAs($user)
            ->get(route('ward.patient-details', ['patient_id' => $patient->id]))
            ->assertOk()
            ->assertSee($this->modeScript('dark'), false);
    }

    public function test_saved_values_that_no_longer_exist_fall_back_safely(): void
    {
        $user = User::factory()->create(['theme' => ['mode' => 'neon', 'colours' => 'retired-preset']]);
        $this->assertSame('light', UserTheme::mode($user));
        $this->assertSame('hospital', UserTheme::preferences($user)['colours']);

        $halfCustom = User::factory()->create(['theme' => ['mode' => 'dark', 'colours' => 'custom', 'primary' => '#123456']]);
        $this->assertSame('hospital', UserTheme::preferences($halfCustom)['colours']);
    }
}
