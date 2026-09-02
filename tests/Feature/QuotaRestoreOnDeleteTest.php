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
use App\Support\DailyTransactionQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * US-TR-03 AC5 — soft-deleting a transaction returns its daily-quota slot and
 * drops the row from every aggregation: quota count, Invoice progress/freeze,
 * Customer & Employee breakdowns, and the running-capital total.
 */
class QuotaRestoreOnDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-20 09:00:00');
    }

    /**
     * @return array{0: User, 1: TransactionCategory, 2: TransactionCategory}
     */
    private function company(): array
    {
        $user = User::factory()->create(['role' => 'owner']);

        CapitalEntry::factory()->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);

        return [
            $user,
            TransactionCategory::factory()->for($user->company)->income()->create(),
            TransactionCategory::factory()->for($user->company)->expense()->create(),
        ];
    }

    private function seedUsage(User $owner, TransactionCategory $category, int $count): void
    {
        Transaction::factory()->count($count)->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'category_id' => $category->id,
            'type' => $category->type,
            'transaction_date' => '2026-09-15',
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function createPayload(TransactionCategory $category): array
    {
        return [
            'type' => $category->type,
            'amount' => 25_000,
            'category_id' => $category->id,
            'transaction_date' => '2026-09-15',
            'payment_method' => 'cash',
        ];
    }

    public function test_soft_delete_returns_a_daily_quota_slot(): void
    {
        [$owner, $income] = $this->company();
        $this->seedUsage($owner, $income, DailyTransactionQuota::LIMIT);

        // At the limit, a new income transaction is blocked.
        $this->actingAs($owner)
            ->post(route('transactions.store'), $this->createPayload($income))
            ->assertSessionHasErrors('quota');

        $this->actingAs($owner)->delete(route('transactions.destroy',
            Transaction::where('company_id', $owner->company_id)->firstOrFail(),
        ));

        $this->assertSame(DailyTransactionQuota::LIMIT - 1, DailyTransactionQuota::for($owner->company->fresh())->used('income'));

        // The freed slot can be used again.
        $this->actingAs($owner)
            ->post(route('transactions.store'), $this->createPayload($income))
            ->assertSessionHasNoErrors();
    }

    public function test_soft_deleted_rows_are_excluded_from_the_quota_count(): void
    {
        [$owner, $income] = $this->company();
        $this->seedUsage($owner, $income, 5);

        Transaction::where('company_id', $owner->company_id)->limit(2)->get()
            ->each(fn (Transaction $t) => $t->delete());

        $this->assertSame(3, DailyTransactionQuota::for($owner->company->fresh())->used('income'));
    }

    public function test_soft_deleted_transaction_is_excluded_from_invoice_progress(): void
    {
        [$owner, $income] = $this->company();
        $customer = Customer::factory()->for($owner->company)->create();
        $invoice = Invoice::factory()->create([
            'company_id' => $owner->company_id,
            'customer_id' => $customer->id,
            'created_by' => $owner->id,
        ]);
        InvoiceItem::factory()->for($invoice)->create(['amount' => 1_000_000]);

        $kept = $this->link($owner, $invoice, $income, 400_000);
        $removed = $this->link($owner, $invoice, $income, 250_000);

        $this->assertSame(650_000.0, $invoice->linkedTotal());

        $removed->delete();

        $this->assertSame(400_000.0, $invoice->refresh()->linkedTotal());
        $this->assertSame(600_000.0, $invoice->remainingBalance());
        $this->assertTrue($kept->exists());
    }

    public function test_soft_deleting_the_last_linked_transaction_unfreezes_the_invoice(): void
    {
        [$owner, $income] = $this->company();
        $customer = Customer::factory()->for($owner->company)->create();
        $invoice = Invoice::factory()->create([
            'company_id' => $owner->company_id,
            'customer_id' => $customer->id,
            'created_by' => $owner->id,
        ]);
        InvoiceItem::factory()->for($invoice)->create(['amount' => 500_000]);

        $transaction = $this->link($owner, $invoice, $income, 300_000);
        $this->assertTrue($invoice->isFrozen());

        $transaction->delete();

        $this->assertFalse($invoice->refresh()->isFrozen());
    }

    public function test_soft_deleted_transaction_is_excluded_from_customer_breakdown(): void
    {
        [$owner, $income] = $this->company();
        $customer = Customer::factory()->for($owner->company)->create();

        $this->makeIncomeFor($owner, $income, ['customer_id' => $customer->id, 'amount' => 200_000]);
        $removed = $this->makeIncomeFor($owner, $income, ['customer_id' => $customer->id, 'amount' => 150_000]);

        $removed->delete();

        $this->actingAs($owner)
            ->get(route('customers.show', $customer))
            ->assertInertia(fn (Assert $page) => $page
                ->where('breakdown.total', 200000)
                ->where('breakdown.count', 1));
    }

    public function test_soft_deleted_transaction_is_excluded_from_employee_breakdown(): void
    {
        [$owner, $income] = $this->company();
        $worker = Employee::factory()->for($owner->company)->create();

        $this->makeIncomeFor($owner, $income, ['employee_id' => $worker->id, 'amount' => 500_000]);
        $removed = $this->makeIncomeFor($owner, $income, ['employee_id' => $worker->id, 'amount' => 120_000]);

        $removed->delete();

        $this->actingAs($owner)
            ->get(route('employees.show', $worker))
            ->assertInertia(fn (Assert $page) => $page
                ->where('breakdown.total', 500000)
                ->where('breakdown.count', 1));
    }

    public function test_soft_deleted_transaction_is_excluded_from_the_running_capital_total(): void
    {
        [$owner, $income, $expense] = $this->company();
        $entry = CapitalEntry::query()->where('company_id', $owner->company_id)->firstOrFail();
        $entry->update(['initial_amount' => 1_000_000]);

        $this->makeIncomeFor($owner, $income, ['amount' => 300_000, 'transaction_date' => '2026-09-10']);
        $removedExpense = Transaction::factory()->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'category_id' => $expense->id,
            'type' => 'expense',
            'amount' => 400_000,
            'transaction_date' => '2026-09-10',
        ]);

        $this->assertSame(900_000.0, $entry->fresh()->currentTotal()); // 1,000,000 + 300,000 - 400,000

        $removedExpense->delete();

        $this->assertSame(1_300_000.0, $entry->fresh()->currentTotal()); // expense no longer counted
    }

    private function link(User $owner, Invoice $invoice, TransactionCategory $category, int $amount): Transaction
    {
        return Transaction::factory()->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'type' => 'income',
            'category_id' => $category->id,
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'amount' => $amount,
            'transaction_date' => '2026-09-15',
        ]);
    }

    private function makeIncomeFor(User $owner, TransactionCategory $category, array $overrides): Transaction
    {
        return Transaction::factory()->create(array_merge([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'type' => 'income',
            'category_id' => $category->id,
            'transaction_date' => '2026-09-15',
        ], $overrides));
    }
}
