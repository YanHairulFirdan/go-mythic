<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_employee_edit(): void
    {
        $employee = Employee::factory()->create();

        $this->get(route('employees.edit', $employee))->assertRedirect(route('login'));
    }

    public function test_owner_can_open_employee_edit_page(): void
    {
        $owner = User::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $owner->company_id, 'name' => 'Old Name']);

        $this->actingAs($owner)
            ->get(route('employees.edit', $employee))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employees/Edit')
                ->where('employee.id', $employee->id)
                ->where('employee.name', 'Old Name'));
    }

    public function test_owner_can_update_any_employee_name_in_their_company(): void
    {
        $owner = User::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $owner->company_id, 'name' => 'Old Name']);

        $this->actingAs($owner)
            ->patch(route('employees.update', $employee), ['name' => 'New Name'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('employees.show', $employee));

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'name' => 'New Name']);
    }

    public function test_employee_name_is_required_and_maximum_255_characters(): void
    {
        $owner = User::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $owner->company_id, 'name' => 'Unchanged']);

        $this->actingAs($owner)->patch(route('employees.update', $employee), ['name' => ''])->assertInvalid('name');
        $this->actingAs($owner)->patch(route('employees.update', $employee), ['name' => str_repeat('x', 256)])->assertInvalid('name');
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'name' => 'Unchanged']);
    }

    public function test_employee_role_cannot_edit_or_update_employee(): void
    {
        $employeeUser = User::factory()->create(['role' => 'employee']);
        $employee = Employee::factory()->create(['company_id' => $employeeUser->company_id]);

        $this->actingAs($employeeUser)->get(route('employees.edit', $employee))->assertForbidden();
        $this->actingAs($employeeUser)->patch(route('employees.update', $employee), ['name' => 'Nope'])->assertForbidden();
    }

    public function test_owner_cannot_edit_or_update_another_companys_employee(): void
    {
        $owner = User::factory()->create();
        $employee = Employee::factory()->create(['name' => 'Protected']);

        $this->actingAs($owner)->get(route('employees.edit', $employee))->assertNotFound();
        $this->actingAs($owner)->patch(route('employees.update', $employee), ['name' => 'Nope'])->assertNotFound();
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'name' => 'Protected']);
    }
}
