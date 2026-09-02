<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanyBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_update_branding(): void
    {
        $this->patch(route('settings.branding.update'), ['primary_color' => 'blue'])
            ->assertRedirect(route('login'));
    }

    public function test_employee_cannot_update_branding(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAs($employee)
            ->patch(route('settings.branding.update'), ['primary_color' => 'blue'])
            ->assertForbidden();
    }

    public function test_owner_can_choose_a_preset_primary_color(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->patch(route('settings.branding.update'), ['primary_color' => 'emerald'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertSame('emerald', $owner->company->fresh()->primary_color);
    }

    public function test_owner_can_choose_a_custom_hex_primary_color(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->patch(route('settings.branding.update'), ['primary_color' => '#FF5733'])
            ->assertSessionHasNoErrors();

        $this->assertSame('#ff5733', $owner->company->fresh()->primary_color);
    }

    public function test_invalid_primary_color_is_rejected_and_leaves_the_saved_value(): void
    {
        $owner = User::factory()->create();
        $owner->company->update(['primary_color' => 'blue']);

        foreach (['bright-red', '#fff', '#zzzzzz'] as $bad) {
            $this->actingAs($owner)
                ->patch(route('settings.branding.update'), ['primary_color' => $bad])
                ->assertSessionHasErrors('primary_color');
        }

        $this->assertSame('blue', $owner->company->fresh()->primary_color);
    }

    public function test_clearing_the_primary_color_falls_back_to_default(): void
    {
        $owner = User::factory()->create();
        $owner->company->update(['primary_color' => 'rose']);

        $this->actingAs($owner)
            ->patch(route('settings.branding.update'), ['primary_color' => ''])
            ->assertSessionHasNoErrors();

        $this->assertNull($owner->company->fresh()->primary_color);
    }

    public function test_owner_can_upload_a_logo(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->patch(route('settings.branding.update'), [
                'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            ])
            ->assertSessionHasNoErrors();

        $path = $owner->company->fresh()->logo_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_non_image_and_oversized_logos_are_rejected(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->patch(route('settings.branding.update'), [
                'logo' => UploadedFile::fake()->create('logo.pdf', 40, 'application/pdf'),
            ])
            ->assertSessionHasErrors('logo');

        $this->actingAs($owner)
            ->patch(route('settings.branding.update'), [
                'logo' => UploadedFile::fake()->image('huge.png')->size(700),
            ])
            ->assertSessionHasErrors('logo');

        $this->assertNull($owner->company->fresh()->logo_path);
    }

    public function test_uploading_a_new_logo_replaces_the_previous_file(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();

        $this->actingAs($owner)->patch(route('settings.branding.update'), [
            'logo' => UploadedFile::fake()->image('first.png'),
        ]);
        $first = $owner->company->fresh()->logo_path;

        $this->actingAs($owner)->patch(route('settings.branding.update'), [
            'logo' => UploadedFile::fake()->image('second.png'),
        ]);
        $second = $owner->company->fresh()->logo_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_owner_can_remove_the_logo(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $this->actingAs($owner)->patch(route('settings.branding.update'), [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);
        $path = $owner->company->fresh()->logo_path;

        $this->actingAs($owner)
            ->patch(route('settings.branding.update'), ['remove_logo' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertNull($owner->company->fresh()->logo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_branding_is_shared_to_every_inertia_page(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $owner->company->update(['primary_color' => 'blue']);
        $this->actingAs($owner)->patch(route('settings.branding.update'), [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $this->actingAs($owner)
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('branding.primary.name', 'blue')
                ->where('branding.primary.hex', '#2563eb')
                ->where('branding.primary.custom', false)
                ->whereNot('branding.logoUrl', null));
    }

    public function test_branding_defaults_when_the_company_has_no_customization(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('branding.primary.name', 'indigo')
                ->where('branding.logoUrl', null));
    }

    public function test_the_theme_css_variables_are_injected_in_the_document_head(): void
    {
        $owner = User::factory()->create();
        $owner->company->update(['primary_color' => '#ff5733']);

        $this->actingAs($owner)
            ->get(route('profile.edit'))
            ->assertSee('--c-primary-600:255 87 51 !important;', false);
    }

    public function test_one_company_theme_does_not_leak_to_another(): void
    {
        $alice = User::factory()->create();
        $alice->company->update(['primary_color' => 'rose']);

        $bob = User::factory()->create();

        $this->actingAs($bob)
            ->get(route('profile.edit'))
            ->assertDontSee('--c-primary-600', false)
            ->assertInertia(fn (Assert $page) => $page->where('branding.primary.name', 'indigo'));
    }
}
