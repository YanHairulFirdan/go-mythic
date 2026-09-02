<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_employee_management(): void
    {
        $this->get(route('employees.index'))
            ->assertRedirect(route('login'));
    }

    public function test_owner_sees_employee_account_fields_and_existing_statuses(): void
    {
        $owner = User::factory()->create();
        Employee::factory()->create([
            'company_id' => $owner->company_id,
            'name' => 'Inactive Employee',
            'has_access_to_system' => true,
            'status' => 'inactive',
            'user_id' => User::factory()->create([
                'company_id' => $owner->company_id,
                'role' => 'employee',
                'status' => 'inactive',
            ])->id,
        ]);

        $this->actingAs($owner)
            ->get(route('employees.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Employees/Index')
                ->where('canCreateEmployee', false)
                ->has('employees', 1)
                ->where('employees.0.name', 'Inactive Employee')
                ->where('employees.0.status', 'inactive')
                ->where('employees.0.has_access_to_system', true)
                ->where('employees.0.user.username', fn ($username) => is_string($username) && $username !== '')
                ->has('employees'));
    }

    public function test_free_owner_is_redirected_to_payment_without_creating_employee_account(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->post(route('employees.account.store'), [
                'name' => 'Made Employee',
                'username' => 'made.employee',
                'password' => 'secret-password',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('subscription.index'));

        $this->assertDatabaseCount('employees', 0);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_pending_payment_does_not_unlock_employee_account_creation(): void
    {
        $owner = User::factory()->create();
        $owner->company->payments()->create([
            'amount' => 99000,
            'attachment_path' => 'payment-proofs/proof.png',
            'status' => 'pending',
        ]);

        $this->actingAs($owner)
            ->post(route('employees.account.store'), [
                'name' => 'Pending Employee',
                'username' => 'pending.employee',
                'password' => 'secret-password',
            ])
            ->assertRedirect(route('subscription.index'));

        $this->assertDatabaseCount('employees', 0);
    }

    public function test_paid_owner_can_create_employee_account_with_active_roster_row(): void
    {
        $owner = User::factory()->create();
        $owner->company->update(['paid_until' => now()->addDays(30)]);

        $this->actingAs($owner)
            ->post(route('employees.account.store'), [
                'name' => 'Made Employee',
                'username' => 'made.employee',
                'password' => 'secret-password',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('employees.index'));

        $employee = User::query()->where('username', 'made.employee')->firstOrFail();

        $this->assertSame('employee', $employee->role);
        $this->assertSame('active', $employee->status);
        $this->assertTrue(password_verify('secret-password', $employee->password));
        $this->assertDatabaseHas('employees', [
            'company_id' => $owner->company_id,
            'user_id' => $employee->id,
            'name' => 'Made Employee',
            'has_access_to_system' => true,
            'status' => 'active',
        ]);
    }

    public function test_paid_owner_can_create_multiple_employee_accounts_without_quota_rejection(): void
    {
        $owner = User::factory()->create();
        $owner->company->update(['paid_until' => now()->addDays(30)]);

        foreach (['first.employee', 'second.employee'] as $username) {
            $this->actingAs($owner)
                ->post(route('employees.account.store'), [
                    'name' => $username,
                    'username' => $username,
                    'password' => 'secret-password',
                ])
                ->assertRedirect(route('employees.index'));
        }

        $this->assertDatabaseCount('employees', 2);
        $this->assertDatabaseCount('users', 3);
    }

    public function test_employee_cannot_manage_employee_accounts(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAs($employee)
            ->get(route('employees.index'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->post(route('employees.account.store'), [
                'name' => 'Not Allowed',
                'username' => 'not.allowed',
                'password' => 'secret-password',
            ])
            ->assertForbidden();
    }

    public function test_employee_account_fields_are_required_and_password_has_minimum_length(): void
    {
        $owner = User::factory()->create();
        $owner->company->update(['paid_until' => now()->addDays(30)]);

        $this->actingAs($owner)
            ->from(route('employees.index'))
            ->post(route('employees.account.store'), [
                'name' => '',
                'username' => '',
                'password' => 'short',
            ])
            ->assertSessionHasErrors(['name', 'username', 'password']);

        $this->assertDatabaseCount('employees', 0);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_employee_username_must_be_unique_within_company(): void
    {
        $owner = User::factory()->create();
        $owner->company->update(['paid_until' => now()->addDays(30)]);

        $this->actingAs($owner)->post(route('employees.account.store'), [
            'name' => 'First Employee',
            'username' => 'same.username',
            'password' => 'secret-password',
        ]);

        $this->actingAs($owner)
            ->from(route('employees.index'))
            ->post(route('employees.account.store'), [
                'name' => 'Second Employee',
                'username' => 'same.username',
                'password' => 'secret-password',
            ])
            ->assertSessionHasErrors('username');

        $this->assertDatabaseCount('employees', 1);
    }

    public function test_employee_can_log_in_with_username(): void
    {
        $owner = User::factory()->create();
        $owner->company->update(['paid_until' => now()->addDays(30)]);

        $this->actingAs($owner)->post(route('employees.account.store'), [
            'name' => 'Login Employee',
            'username' => 'login.employee',
            'password' => 'secret-password',
        ]);

        auth()->logout();

        $this->post(route('login'), [
            'email' => 'login.employee',
            'password' => 'secret-password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs(User::query()->where('username', 'login.employee')->firstOrFail());
    }

    public function test_employee_account_creation_is_tenant_scoped(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $owner->company->update(['paid_until' => now()->addDays(30)]);
        $otherOwner->company->update(['paid_until' => now()->addDays(30)]);

        $this->actingAs($otherOwner)->post(route('employees.account.store'), [
            'name' => 'Other Employee',
            'username' => 'shared.username',
            'password' => 'secret-password',
        ]);

        $this->actingAs($owner)->post(route('employees.account.store'), [
            'name' => 'My Employee',
            'username' => 'shared.username',
            'password' => 'secret-password',
        ])->assertRedirect(route('employees.index'));

        $this->actingAs($owner)
            ->get(route('employees.index'))
            ->assertInertia(fn ($page) => $page
                ->has('employees', 1)
                ->where('employees.0.name', 'My Employee'));
    }
}
