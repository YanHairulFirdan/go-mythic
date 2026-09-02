<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeRosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_employee_roster(): void
    {
        $this->get(route('employees.index'))->assertRedirect(route('login'));
    }

    public function test_owner_can_view_the_employee_roster(): void
    {
        $owner = User::factory()->create();
        $worker = Employee::factory()->create(['company_id' => $owner->company_id]);

        $this->actingAs($owner)
            ->get(route('employees.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employees/Index')
                ->has('employees', 1)
                ->where('employees.0.id', $worker->id)
                ->where('employees.0.has_access_to_system', false));
    }

    public function test_owner_can_add_a_worker_without_creating_a_login_account(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->post(route('employees.store'), ['name' => 'Made Wirawan'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('employees.index'));

        $this->assertDatabaseHas('employees', [
            'company_id' => $owner->company_id,
            'name' => 'Made Wirawan',
            'user_id' => null,
            'has_access_to_system' => false,
        ]);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_worker_name_is_required(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->from(route('employees.index'))
            ->post(route('employees.store'), [])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('employees', 0);
    }

    public function test_owner_can_add_workers_for_free_or_paid_companies(): void
    {
        $freeOwner = User::factory()->create();
        $paidOwner = User::factory()->create([
            'company_id' => Company::factory()->create(['paid_until' => now()->addMonth()])->id,
        ]);

        $this->actingAs($freeOwner)->post(route('employees.store'), ['name' => 'Free Worker']);
        $this->actingAs($paidOwner)->post(route('employees.store'), ['name' => 'Paid Worker']);

        $this->assertDatabaseHas('employees', ['company_id' => $freeOwner->company_id, 'name' => 'Free Worker']);
        $this->assertDatabaseHas('employees', ['company_id' => $paidOwner->company_id, 'name' => 'Paid Worker']);
    }

    public function test_employee_cannot_access_or_create_roster_rows(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAs($employee)->get(route('employees.index'))->assertForbidden();
        $this->actingAs($employee)->post(route('employees.store'), ['name' => 'No Access'])->assertForbidden();
    }

    public function test_roster_is_scoped_to_the_authenticated_company(): void
    {
        $owner = User::factory()->create();
        $otherWorker = Employee::factory()->create();
        $ownWorker = Employee::factory()->create(['company_id' => $owner->company_id]);

        $this->actingAs($owner)
            ->get(route('employees.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('employees', 1)
                ->where('employees.0.id', $ownWorker->id));
    }
}
