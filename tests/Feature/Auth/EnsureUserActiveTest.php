<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class EnsureUserActiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_employee_is_logged_out_from_an_active_session(): void
    {
        $user = User::factory()->create([
            'role' => 'employee',
            'status' => 'inactive',
            'inactive_reason' => 'manual',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'Akun Employee tidak aktif. Alasan: manual.');
    }

    public function test_inactive_employee_is_logged_out_when_accessing_profile(): void
    {
        $user = User::factory()->create([
            'role' => 'employee',
            'status' => 'inactive',
            'inactive_reason' => 'company_closed',
        ]);

        $response = $this->actingAs($user)->get('/profile');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'Akun Employee tidak aktif. Alasan: company_closed.');
    }

    public function test_deactivated_employee_session_is_revoked_and_error_is_shown_on_login_screen(): void
    {
        $user = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->forceFill(['status' => 'inactive', 'inactive_reason' => 'manual'])->save();

        $response = $this->get('/dashboard');

        $this->assertGuest();
        $response->assertRedirect(route('login'));

        $this->get(route('login'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auth/Login')
            ->where('error', 'Akun Employee tidak aktif. Alasan: manual.'));
    }

    public function test_active_employee_can_access_protected_routes(): void
    {
        $user = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $this->assertAuthenticatedAs($user);
        $response->assertOk();
    }

    public function test_inactive_owner_is_not_blocked_by_employee_middleware(): void
    {
        $user = User::factory()->create([
            'role' => 'owner',
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $this->assertAuthenticatedAs($user);
        $response->assertOk();
    }
}
