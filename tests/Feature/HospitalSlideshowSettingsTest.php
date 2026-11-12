<?php

namespace Tests\Feature;

use App\Models\Hospital;
use App\Models\Slideshow;
use App\Models\User;
use App\Models\Ward;
use App\Models\WardType;
use App\Support\WardSlideshow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The hospital's Slideshow page: whether the ward dashboard logo opens the
 * slideshow, the seconds per slide, and which slides show; and the bottom bar
 * text set on the hospital's Theme & Menu tab.
 */
class HospitalSlideshowSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Hospital $hospital;
    private Ward $ward;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $this->hospital = Hospital::create(['name' => 'Test Hospital']);
        $this->ward = Ward::create([
            'hospital_id' => $this->hospital->id, 'ward_code' => 'W1', 'ward_name' => 'Medical Ward', 'is_active' => true,
        ]);
    }

    private function slide(array $attributes = []): Slideshow
    {
        return $this->hospital->slideshows()->create([
            'file_path' => 'slideshows/abc123.png', 'file_type' => 'image/png', 'order' => 0, 'is_active' => true,
            ...$attributes,
        ]);
    }

    private function saveSettings(array $fields): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->put(route('hospitals.slideshows.settings', $this->hospital), [
            'slideshow_enabled' => '1',
            'slideshow_seconds' => '10',
            ...$fields,
        ]);
    }

    public function test_the_slideshow_is_on_with_ten_second_slides_until_changed(): void
    {
        $this->assertTrue($this->hospital->slideshowEnabled());
        $this->assertSame(10, $this->hospital->slideshowSeconds());

        // A hospital read before the columns' migration has run has none of them
        $unmigrated = (new Hospital)->forceFill(['name' => 'Old']);
        $this->assertTrue($unmigrated->slideshowEnabled());
        $this->assertSame(10, $unmigrated->slideshowSeconds());
        $this->assertSame([Hospital::DEFAULT_FOOTER_HIGHLIGHT, Hospital::DEFAULT_FOOTER_TEXT], $unmigrated->footerParts());
    }

    public function test_a_server_that_has_not_migrated_keeps_working_as_before(): void
    {
        Storage::fake('public');
        // As on a legacy server whose database lacks the new columns
        Schema::partialMock()->shouldReceive('hasColumn')->andReturn(false);

        // Saving the hospital form still saves everything else
        $this->actingAs($this->admin)->put(route('hospitals.update', $this->hospital), [
            'name' => 'Renamed Hospital', 'footer_highlight' => 'PHM', 'footer_text' => 'Smart Ward',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Renamed Hospital', $this->hospital->fresh()->name);
        $this->assertNull($this->hospital->fresh()->footer_highlight);

        // Uploads work as they did, without a name
        $this->actingAs($this->admin)->post(route('hospitals.slideshows.store', $this->hospital), [
            'file' => UploadedFile::fake()->image('Visiting Hours.png'),
        ])->assertSessionHasNoErrors();
        $this->assertNull($this->hospital->slideshows()->first()->title);

        // The settings wait for the migration instead of failing
        $this->saveSettings(['slideshow_enabled' => '0'])->assertSessionHasErrors('slideshow_enabled');
        $this->assertTrue($this->hospital->fresh()->slideshowEnabled());
    }

    public function test_page_shows_the_settings_the_slides_and_the_built_in_pictures(): void
    {
        $this->slide(['title' => 'Hand hygiene week']);

        $response = $this->actingAs($this->admin)->get(route('hospitals.slideshows.index', $this->hospital))
            ->assertOk()
            ->assertSee('Ward dashboard link')
            ->assertSee('name="slideshow_enabled"', false)
            ->assertSee('name="slideshow_seconds"', false)
            ->assertSee('Hand hygiene week');

        foreach (WardSlideshow::pictures() as $index => $fileName) {
            $response->assertSee('name="pictures[' . $index . '][file]" value="' . e($fileName) . '"', false);
        }
    }

    public function test_only_hospital_admins_manage_the_slideshow(): void
    {
        $nurse = User::factory()->create(['role' => User::ROLE_NURSE]);

        $this->actingAs($nurse)->get(route('hospitals.slideshows.index', $this->hospital))->assertForbidden();
        $this->actingAs($nurse)->put(route('hospitals.slideshows.settings', $this->hospital), [
            'slideshow_enabled' => '0', 'slideshow_seconds' => '10',
        ])->assertForbidden();

        $this->assertTrue($this->hospital->fresh()->slideshowEnabled());
    }

    public function test_settings_switch_the_link_and_set_the_seconds_and_slides(): void
    {
        $slide = $this->slide();
        $pictures = WardSlideshow::pictures();

        $this->saveSettings([
            'slideshow_enabled' => '0',
            'slideshow_seconds' => '25',
            'slides' => [$slide->id => ['title' => '  Visiting hours  ', 'order' => '3', 'active' => '0']],
            // Hide the first built-in picture, and a file the server doesn't have
            'pictures' => [
                ...collect($pictures)->map(fn ($file, $i) => ['file' => $file, 'shown' => $i === 0 ? '0' : '1'])->all(),
                ['file' => 'Not on the server.jpeg', 'shown' => '0'],
            ],
        ])->assertRedirect(route('hospitals.slideshows.index', $this->hospital))->assertSessionHasNoErrors();

        $hospital = $this->hospital->fresh();
        $this->assertFalse($hospital->slideshowEnabled());
        $this->assertSame(25, $hospital->slideshowSeconds());
        $this->assertSame(array_slice($pictures, 0, 1), $hospital->hiddenSlideshowPictures());

        $slide->refresh();
        $this->assertSame('Visiting hours', $slide->title);
        $this->assertSame(3, $slide->order);
        $this->assertFalse($slide->is_active);

        // Another hospital's slide can't be changed through this one
        $other = Hospital::create(['name' => 'Other']);
        $theirs = $other->slideshows()->create(['file_path' => 'slideshows/x.png', 'file_type' => 'image/png', 'is_active' => true]);
        $this->saveSettings(['slides' => [$theirs->id => ['active' => '0']]]);
        $this->assertTrue($theirs->fresh()->is_active);
    }

    public function test_seconds_per_slide_stay_in_range(): void
    {
        $this->saveSettings(['slideshow_seconds' => '1'])->assertSessionHasErrors('slideshow_seconds');
        $this->saveSettings(['slideshow_seconds' => '9999'])->assertSessionHasErrors('slideshow_seconds');
        $this->assertSame(10, $this->hospital->fresh()->slideshowSeconds());
    }

    public function test_upload_names_the_slide_after_its_file(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->post(route('hospitals.slideshows.store', $this->hospital), [
            'file' => UploadedFile::fake()->image('Visiting Hours.png'),
        ])->assertSessionHasNoErrors();

        $this->assertSame('Visiting Hours', $this->hospital->slideshows()->first()->title);
    }

    public function test_the_dashboard_logo_opens_the_slideshow_only_while_it_is_on(): void
    {
        $this->actingAs($this->admin)->get(route('ward.dashboard', ['ward_id' => $this->ward->id]))
            ->assertOk()
            ->assertSee("new CustomEvent('open-slideshow-modal')", false)
            ->assertSee(route('ward.slideshow-viewer', ['ward_id' => $this->ward->id]), false);

        $this->hospital->update(['slideshow_enabled' => false]);

        $this->actingAs($this->admin)->get(route('ward.dashboard', ['ward_id' => $this->ward->id]))
            ->assertOk()
            ->assertDontSee('open-slideshow-modal', false)
            ->assertDontSee(route('ward.slideshow-viewer', ['ward_id' => $this->ward->id]), false);

        // The critical care copy of the dashboard follows the same switch
        $icu = WardType::create(['code' => 'ICUX', 'name' => 'ICU type', 'is_critical_care' => true, 'is_active' => true]);
        $this->ward->update(['ward_type_id' => $icu->id]);
        $this->actingAs($this->admin)->get(route('critical-care.dashboard', ['ward_id' => $this->ward->id]))
            ->assertOk()
            ->assertDontSee('open-slideshow-modal', false);
        $this->hospital->update(['slideshow_enabled' => true]);
        $this->actingAs($this->admin)->get(route('critical-care.dashboard', ['ward_id' => $this->ward->id]))
            ->assertOk()
            ->assertSee('open-slideshow-modal', false);
    }

    public function test_viewer_plays_the_shown_slides_at_the_set_pace_and_is_gone_when_off(): void
    {
        $this->slide(['title' => 'Shown slide']);
        $this->slide(['title' => 'Hidden slide', 'is_active' => false]);
        $pictures = WardSlideshow::pictures();
        $this->hospital->update(['slideshow_seconds' => 20, 'slideshow_hidden_pictures' => array_slice($pictures, 0, 1)]);

        $response = $this->actingAs($this->admin)->get(route('ward.slideshow-viewer', ['ward_id' => $this->ward->id]))
            ->assertOk()
            ->assertSee('delay: 20000', false)
            ->assertSee('Shown slide')
            ->assertDontSee('Hidden slide');
        if (count($pictures) > 1) {
            $response->assertDontSee('. ' . e(pathinfo($pictures[0], PATHINFO_FILENAME)) . '</option>', false)
                ->assertSee('. ' . e(pathinfo($pictures[1], PATHINFO_FILENAME)) . '</option>', false);
        }

        $this->hospital->update(['slideshow_enabled' => false]);
        $this->actingAs($this->admin)->get(route('ward.slideshow-viewer', ['ward_id' => $this->ward->id]))->assertNotFound();
    }

    public function test_bottom_bar_text_is_set_on_the_theme_tab(): void
    {
        $this->actingAs($this->admin)->get(route('hospitals.edit', ['hospital' => $this->hospital, 'tab' => 'theme']))
            ->assertOk()
            ->assertSee('Bottom bar')
            ->assertSee('name="footer_highlight"', false)
            ->assertSee('name="footer_text"', false);

        $this->actingAs($this->admin)->get(route('hospitals.index'))
            ->assertSee('<span class="font-semibold text-brand-600">PHKL</span> Smart Ward 4.0', false);

        $this->actingAs($this->admin)->put(route('hospitals.update', $this->hospital), [
            'name' => 'Test Hospital', 'tab' => 'theme',
            'footer_highlight' => ' PHM ', 'footer_text' => 'Smart Ward',
        ])->assertRedirect(route('hospitals.edit', ['hospital' => $this->hospital, 'tab' => 'theme']));

        $this->assertSame(['PHM', 'Smart Ward'], $this->hospital->fresh()->footerParts());
        $this->actingAs($this->admin)->get(route('hospitals.index'))
            ->assertSee('<span class="font-semibold text-brand-600">PHM</span> Smart Ward', false)
            ->assertDontSee('Smart Ward 4.0');

        // Both empty goes back to the default; a form without the fields keeps what's saved
        $this->actingAs($this->admin)->put(route('hospitals.update', $this->hospital), ['name' => 'Test Hospital']);
        $this->assertSame(['PHM', 'Smart Ward'], $this->hospital->fresh()->footerParts());
        $this->actingAs($this->admin)->put(route('hospitals.update', $this->hospital), [
            'name' => 'Test Hospital', 'footer_highlight' => '', 'footer_text' => '',
        ]);
        $this->assertSame(['PHKL', 'Smart Ward 4.0'], $this->hospital->fresh()->footerParts());

        $this->actingAs($this->admin)->put(route('hospitals.update', $this->hospital), [
            'name' => 'Test Hospital', 'footer_text' => str_repeat('x', 81),
        ])->assertSessionHasErrors('footer_text');
    }
}
