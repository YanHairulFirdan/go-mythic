<?php

namespace Tests\Feature;

use App\Models\CapitalEntry;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * US-CUST-02 (customer di transaksi income), US-CUST-03 (detail Customer +
 * breakdown), US-CUST-04 (breakdown Employee/Worker).
 */
class CustomerEmployeeLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-20 09:00:00');
    }

    /**
     * @return array{0: User, 1: TransactionCategory, 2: Customer, 3: Employee}
     */
    private function company(string $role = 'owner'): array
    {
        $user = User::factory()->create(['role' => $role]);
        CapitalEntry::factory()->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);

        return [
            $user,
            TransactionCategory::factory()->for($user->company)->income()->create(),
            Customer::factory()->for($user->company)->create(['name' => 'Toko Melati']),
            Employee::factory()->create(['company_id' => $user->company_id, 'name' => 'Pak Made']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(TransactionCategory $category, array $overrides = []): array
    {
        return array_merge([
            'type' => 'income',
            'amount' => 300_000,
            'category_id' => $category->id,
            'transaction_date' => '2026-09-15',
            'payment_method' => 'cash',
        ], $overrides);
    }

    private function txn(User $owner, array $overrides = []): Transaction
    {
        return Transaction::factory()->create(array_merge([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'type' => 'income',
            'transaction_date' => '2026-09-15',
        ], $overrides));
    }

    // ----- US-CUST-02 -----

    public function test_income_transaction_can_link_a_customer(): void
    {
        [$owner, $category, $customer] = $this->company();

        $this->actingAs($owner)
            ->post(route('transactions.store'), $this->payload($category, ['customer_id' => $customer->id]))
            ->assertRedirect(route('transactions.index'));

        $this->assertDatabaseHas('transactions', [
            'customer_id' => $customer->id,
            'type' => 'income',
        ]);
    }

    public function test_expense_transaction_rejects_a_customer(): void
    {
        [$owner, $income, $customer] = $this->company();
        $expenseCategory = TransactionCategory::factory()->for($owner->company)->expense()->create();

        $this->actingAs($owner)
            ->post(route('transactions.store'), $this->payload($income, [
                'type' => 'expense',
                'category_id' => $expenseCategory->id,
                'customer_id' => $customer->id,
            ]))
            ->assertSessionHasErrors('customer_id');
    }

    public function test_customer_must_belong_to_the_company(): void
    {
        [$owner, $category] = $this->company();
        $foreign = Customer::factory()->create();

        $this->actingAs($owner)
            ->post(route('transactions.store'), $this->payload($category, ['customer_id' => $foreign->id]))
            ->assertSessionHasErrors('customer_id');
    }

    public function test_employee_pelaksana_can_be_linked_on_either_type(): void
    {
        [$owner, $income, , $employee] = $this->company();
        $expenseCategory = TransactionCategory::factory()->for($owner->company)->expense()->create();

        $this->actingAs($owner)->post(route('transactions.store'), $this->payload($income, [
            'employee_id' => $employee->id,
        ]))->assertRedirect();

        $this->actingAs($owner)->post(route('transactions.store'), $this->payload($income, [
            'type' => 'expense',
            'category_id' => $expenseCategory->id,
            'employee_id' => $employee->id,
        ]))->assertRedirect();

        $this->assertSame(2, Transaction::where('employee_id', $employee->id)->count());
    }

    public function test_pelaksana_must_belong_to_the_company(): void
    {
        [$owner, $category] = $this->company();
        $foreign = Employee::factory()->create();

        $this->actingAs($owner)
            ->post(route('transactions.store'), $this->payload($category, ['employee_id' => $foreign->id]))
            ->assertSessionHasErrors('employee_id');
    }

    public function test_a_linked_invoice_overrides_a_submitted_customer(): void
    {
        [$owner, $category] = $this->company();
        $invoiceCustomer = Customer::factory()->for($owner->company)->create(['name' => 'Dari Invoice']);
        $otherCustomer = Customer::factory()->for($owner->company)->create(['name' => 'Manual']);
        $invoice = Invoice::factory()->create([
            'company_id' => $owner->company_id,
            'customer_id' => $invoiceCustomer->id,
            'created_by' => $owner->id,
        ]);
        InvoiceItem::factory()->for($invoice)->create(['amount' => 1_000_000]);

        $this->actingAs($owner)->post(route('transactions.store'), $this->payload($category, [
            'invoice_id' => $invoice->id,
            'customer_id' => $otherCustomer->id,
        ]));

        $this->assertDatabaseHas('transactions', [
            'invoice_id' => $invoice->id,
            'customer_id' => $invoiceCustomer->id,
        ]);
    }

    public function test_transaction_create_page_lists_customers_and_employees(): void
    {
        [$owner] = $this->company();

        $this->actingAs($owner)
            ->get(route('transactions.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('customers', 1)
                ->has('employees', 1));
    }

    // ----- US-CUST-03 -----

    public function test_customer_detail_lists_related_income_and_a_breakdown(): void
    {
        [$owner, $category, $customer] = $this->company();
        $this->txn($owner, ['customer_id' => $customer->id, 'category_id' => $category->id, 'amount' => 400_000, 'transaction_date' => '2026-09-10']);
        $this->txn($owner, ['customer_id' => $customer->id, 'category_id' => $category->id, 'amount' => 100_000, 'transaction_date' => '2026-09-18']);

        $this->actingAs($owner)
            ->get(route('customers.show', $customer))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Customers/Show')
                ->has('transactions', 2)
                ->where('transactions.0.transaction_date', '2026-09-18')
                ->where('breakdown.total', 500000)
                ->where('breakdown.count', 2)
                ->where('breakdown.last_date', '2026-09-18'));
    }

    public function test_customer_breakdown_excludes_soft_deleted_transactions(): void
    {
        [$owner, $category, $customer] = $this->company();
        $this->txn($owner, ['customer_id' => $customer->id, 'category_id' => $category->id, 'amount' => 200_000]);
        $this->txn($owner, ['customer_id' => $customer->id, 'category_id' => $category->id, 'amount' => 999_000])->delete();

        $this->actingAs($owner)
            ->get(route('customers.show', $customer))
            ->assertInertia(fn (Assert $page) => $page
                ->has('transactions', 1)
                ->where('breakdown.total', 200000));
    }

    public function test_customer_detail_is_empty_without_transactions(): void
    {
        [$owner, , $customer] = $this->company();

        $this->actingAs($owner)
            ->get(route('customers.show', $customer))
            ->assertInertia(fn (Assert $page) => $page
                ->has('transactions', 0)
                ->where('breakdown.total', 0)
                ->where('breakdown.count', 0)
                ->where('breakdown.last_date', null));
    }

    // ----- US-CUST-04 -----

    public function test_employee_detail_shows_a_transaction_breakdown(): void
    {
        [$owner, $category, , $employee] = $this->company();
        $this->txn($owner, ['employee_id' => $employee->id, 'category_id' => $category->id, 'amount' => 250_000]);
        $expenseCategory = TransactionCategory::factory()->for($owner->company)->expense()->create();
        $this->txn($owner, ['employee_id' => $employee->id, 'category_id' => $expenseCategory->id, 'type' => 'expense', 'amount' => 50_000]);

        $this->actingAs($owner)
            ->get(route('employees.show', $employee))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employees/Show')
                ->where('employee.name', 'Pak Made')
                ->where('breakdown.total', 300000)
                ->where('breakdown.count', 2));
    }

    public function test_employee_detail_works_for_a_worker_without_an_account(): void
    {
        [$owner, $category] = $this->company();
        $worker = Employee::factory()->create([
            'company_id' => $owner->company_id,
            'name' => 'Worker Harian',
            'user_id' => null,
            'has_access_to_system' => false,
        ]);
        $this->txn($owner, ['employee_id' => $worker->id, 'category_id' => $category->id, 'amount' => 120_000]);

        $this->actingAs($owner)
            ->get(route('employees.show', $worker))
            ->assertInertia(fn (Assert $page) => $page
                ->where('breakdown.total', 120000)
                ->where('breakdown.count', 1));
    }

    public function test_employee_detail_is_owner_only(): void
    {
        [$owner, , , $employee] = $this->company();
        $staff = User::factory()->create(['role' => 'employee', 'company_id' => $owner->company_id]);

        $this->get(route('employees.show', $employee))->assertRedirect(route('login'));
        $this->actingAs($staff)->get(route('employees.show', $employee))->assertForbidden();
    }

    public function test_employee_detail_is_tenant_scoped(): void
    {
        [$owner] = $this->company();
        $foreign = Employee::factory()->create();

        $this->actingAs($owner)->get(route('employees.show', $foreign))->assertNotFound();
    }
}
