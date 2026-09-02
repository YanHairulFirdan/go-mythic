<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Employee;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SubscriptionStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_company_can_access_protected_route(): void
    {
        $owner = User::factory()->create();
        $owner->company->update(['paid_until' => now()->addDay()]);

        $this->actingAs($owner)->get(route('dashboard'))->assertOk();
        $this->assertAuthenticatedAs($owner);
    }

    public function test_expired_subscription_degrades_live_and_redirects_owner_to_payment(): void
    {
        $owner = User::factory()->create();
        $owner->company->update(['paid_until' => now()->subSecond()]);

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response->assertRedirect(route('subscription.index'));
        $response->assertSessionHas('warning', 'Langganan sudah tidak aktif. Silakan perpanjang untuk memulihkan akses Employee.');
        $this->assertAuthenticatedAs($owner);
        $this->assertSame('active', $owner->fresh()->status);
    }

    public function test_expired_employee_is_disabled_and_denied_access(): void
    {
        $owner = User::factory()->create();
        $employee = User::factory()->create([
            'company_id' => $owner->company_id,
            'role' => 'employee',
            'status' => 'active',
        ]);
        Employee::factory()->create([
            'company_id' => $owner->company_id,
            'user_id' => $employee->id,
            'name' => $employee->name,
            'has_access_to_system' => true,
        ]);
        $owner->company->update(['paid_until' => now()->subSecond()]);

        $response = $this->actingAs($employee)->get(route('dashboard'));

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'Akun Employee tidak aktif. Alasan: subscription_expired.');
        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'status' => 'inactive',
            'inactive_reason' => 'subscription_expired',
        ]);
    }

    public function test_manual_inactive_employee_is_not_overwritten_by_degrade(): void
    {
        $owner = User::factory()->create();
        $employee = User::factory()->create([
            'company_id' => $owner->company_id,
            'role' => 'employee',
            'status' => 'inactive',
            'inactive_reason' => 'manual',
        ]);
        $owner->company->update(['paid_until' => now()->subSecond()]);

        $this->actingAs($owner)->get(route('dashboard'))->assertRedirect(route('subscription.index'));

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'status' => 'inactive',
            'inactive_reason' => 'manual',
        ]);
    }

    public function test_daily_worker_is_not_changed_by_degrade(): void
    {
        $owner = User::factory()->create();
        $worker = Employee::factory()->create([
            'company_id' => $owner->company_id,
            'user_id' => null,
            'has_access_to_system' => false,
            'status' => 'active',
        ]);
        $owner->company->update(['paid_until' => now()->subSecond()]);

        $this->actingAs($owner)->get(route('dashboard'))->assertRedirect(route('subscription.index'));

        $this->assertDatabaseHas('employees', [
            'id' => $worker->id,
            'has_access_to_system' => 0,
            'status' => 'active',
        ]);
    }

    public function test_expired_owner_can_login_but_expired_employee_cannot(): void
    {
        $owner = User::factory()->create(['password' => 'owner-password']);
        $employee = User::factory()->create([
            'company_id' => $owner->company_id,
            'role' => 'employee',
            'email' => 'employee@example.test',
            'password' => 'employee-password',
        ]);
        $owner->company->update(['paid_until' => now()->subSecond()]);

        $this->post(route('login'), [
            'email' => $owner->email,
            'password' => 'owner-password',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($owner);

        auth()->logout();

        $this->post(route('login'), [
            'email' => $employee->email,
            'password' => 'employee-password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_cached_paid_state_expires_at_paid_until(): void
    {
        $owner = User::factory()->create();
        $owner->company->update(['paid_until' => now()->addSecond()]);

        $this->actingAs($owner)->get(route('dashboard'))->assertOk();
        $this->travel(2)->seconds();

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertRedirect(route('subscription.index'));

        $this->travelBack();
    }

    public function test_subscription_approval_invalidates_cached_state(): void
    {
        $admin = Admin::factory()->create();
        $owner = User::factory()->create();
        $payment = Payment::factory()->create([
            'company_id' => $owner->company_id,
            'status' => 'pending',
        ]);
        $owner->company->update(['paid_until' => now()->subSecond()]);
        Cache::put('subscription:company:'.$owner->company_id, false, now()->addHour());

        $this->actingAs($admin, 'admin')
            ->post(route('admin.payments.approve', $payment))
            ->assertRedirect(route('admin.payments.index'));

        $this->assertNull(Cache::get('subscription:company:'.$owner->company_id));
        $this->actingAs($owner->fresh())->get(route('dashboard'))->assertOk();
    }

    public function test_admin_approval_reactivates_employees_degraded_by_expiry_but_not_manual_deactivations(): void
    {
        $owner = User::factory()->create();
        $expiredEmployee = User::factory()->create([
            'company_id' => $owner->company_id,
            'role' => 'employee',
            'status' => 'active',
            'inactive_reason' => null,
        ]);
        $manualEmployee = User::factory()->create([
            'company_id' => $owner->company_id,
            'role' => 'employee',
            'status' => 'inactive',
            'inactive_reason' => 'manual',
        ]);
        $admin = Admin::factory()->create();
        $payment = Payment::factory()->create([
            'company_id' => $owner->company_id,
            'status' => 'pending',
        ]);
        $owner->company->update(['paid_until' => now()->subSecond()]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertRedirect(route('subscription.index'));

        $this->assertDatabaseHas('users', [
            'id' => $expiredEmployee->id,
            'status' => 'inactive',
            'inactive_reason' => 'subscription_expired',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.payments.approve', $payment))
            ->assertRedirect(route('admin.payments.index'));

        $this->assertDatabaseHas('users', ['id' => $expiredEmployee->id, 'status' => 'active', 'inactive_reason' => null]);
        $this->assertDatabaseHas('users', ['id' => $manualEmployee->id, 'status' => 'inactive', 'inactive_reason' => 'manual']);
        $this->actingAs($expiredEmployee->fresh())->get(route('dashboard'))->assertOk();
    }
}
