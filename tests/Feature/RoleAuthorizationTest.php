<?php

namespace Tests\Feature;

use App\Models\CapitalEntry;
use App\Models\Employee;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_more_page_hides_owner_modules(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAs($employee)
            ->get(route('more.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('More/Index')
                ->where('auth.user.role', 'employee'));
    }

    public function test_owner_more_page_shows_owner_modules(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('more.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('More/Index')
                ->where('auth.user.role', 'owner'));
    }

    public function test_owner_can_open_the_analytic_report(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('reports.profit-loss'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Reports/ProfitLoss'));
    }

    public function test_employee_can_access_transaction_input_and_only_own_list(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $employee = User::factory()->create([
            'company_id' => $owner->company_id,
            'role' => 'employee',
        ]);
        $category = TransactionCategory::factory()->for($owner->company)->income()->create();
        Transaction::factory()->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'category_id' => $category->id,
            'type' => 'income',
        ]);
        Transaction::factory()->create([
            'company_id' => $owner->company_id,
            'created_by' => $employee->id,
            'category_id' => $category->id,
            'type' => 'income',
        ]);

        $this->actingAs($employee)->get(route('transactions.create'))->assertOk();
        $this->actingAs($employee)
            ->get(route('transactions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Index')
                ->has('transactions.data', 1));
    }

    public function test_employee_is_forbidden_from_owner_endpoints(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $employee = User::factory()->create([
            'company_id' => $owner->company_id,
            'role' => 'employee',
        ]);
        $capital = CapitalEntry::factory()->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->subWeek()->toDateString(),
        ]);

        $requests = [
            fn () => $this->actingAs($employee)->get(route('reports.profit-loss')),
            fn () => $this->actingAs($employee)->get(route('employees.index')),
            fn () => $this->actingAs($employee)->post(route('employees.store'), ['name' => 'Worker']),
            fn () => $this->actingAs($employee)->post(route('employees.account.store'), [
                'name' => 'Employee', 'username' => 'employee', 'password' => 'password',
            ]),
            fn () => $this->actingAs($employee)->get(route('subscription.index')),
            fn () => $this->actingAs($employee)->post(route('subscription.payment.store')),
            fn () => $this->actingAs($employee)->get(route('capital.index')),
            fn () => $this->actingAs($employee)->get(route('capital.history')),
            fn () => $this->actingAs($employee)->post(route('capital.store'), [
                'duration' => '1_day', 'initial_amount' => 100,
            ]),
            fn () => $this->actingAs($employee)->patch(route('capital.top-up', $capital), [
                'amount' => 100,
            ]),
            fn () => $this->actingAs($employee)->get(route('transaction-categories.index')),
        ];

        foreach ($requests as $index => $request) {
            $this->assertSame(403, $request()->status(), 'Owner endpoint #'.($index + 1));
        }
    }

    public function test_daily_worker_is_data_only_not_an_authorization_actor(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $worker = Employee::factory()->create([
            'company_id' => $owner->company_id,
            'user_id' => null,
            'has_access_to_system' => false,
        ]);

        $this->actingAs($owner)->get(route('more.index'))->assertOk();

        $this->assertDatabaseHas('employees', [
            'id' => $worker->id,
            'user_id' => null,
            'has_access_to_system' => false,
        ]);
        $this->assertDatabaseMissing('users', ['id' => $worker->user_id]);
    }
}
