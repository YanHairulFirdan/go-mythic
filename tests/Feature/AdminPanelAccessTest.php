<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\Payment;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_seeder_creates_login_capable_admin(): void
    {
        $this->seed(AdminSeeder::class);

        $this->assertDatabaseHas('admins', ['email' => 'admin@sparta.test']);
        $this->assertTrue(
            Auth::guard('admin')->attempt(['email' => 'admin@sparta.test', 'password' => 'password'])
        );
    }

    public function test_admin_can_login_and_lands_on_dashboard(): void
    {
        $admin = Admin::factory()->create(['email' => 'boss@sparta.test']);

        $this->post(route('admin.login.store'), [
            'email' => 'boss@sparta.test',
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_admin_login_fails_with_wrong_password(): void
    {
        Admin::factory()->create(['email' => 'boss@sparta.test']);

        $this->post(route('admin.login.store'), [
            'email' => 'boss@sparta.test',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }

    public function test_admin_index_redirects_to_dashboard(): void
    {
        $this->get('/admin')->assertRedirect('/admin/dashboard');
    }

    public function test_guest_is_redirected_from_admin_dashboard(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_dashboard_shows_company_and_pending_payment_counts(): void
    {
        $admin = Admin::factory()->create();
        $paid = Company::factory()->create(['paid_until' => now()->addDays(10)]);
        $expired = Company::factory()->create(['paid_until' => now()->subDay()]);
        $free = Company::factory()->create(['paid_until' => null]);
        Payment::factory()->create(['company_id' => $paid->id, 'status' => 'pending']);
        Payment::factory()->create(['company_id' => $free->id, 'status' => 'pending']);
        Payment::factory()->create(['company_id' => $expired->id, 'status' => 'approved']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('stats.companies', 3)
                ->where('stats.paid', 1)
                ->where('stats.free', 2)
                ->where('stats.pendingPayments', 2));
    }
}
