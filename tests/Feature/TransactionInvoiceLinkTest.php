<?php

namespace Tests\Feature;

use App\Models\CapitalEntry;
use App\Models\Customer;
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
 * US-INV-02: Mengaitkan transaksi ke Invoice.
 */
class TransactionInvoiceLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-20 09:00:00');
    }

    /**
     * @return array{0: User, 1: TransactionCategory}
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

        return [$user, TransactionCategory::factory()->for($user->company)->income()->create()];
    }

    private function invoiceWorth(User $owner, int $total, ?Customer $customer = null): Invoice
    {
        $customer ??= Customer::factory()->for($owner->company)->create();
        $invoice = Invoice::factory()->create([
            'company_id' => $owner->company_id,
            'customer_id' => $customer->id,
            'created_by' => $owner->id,
        ]);
        InvoiceItem::factory()->for($invoice)->create(['amount' => $total]);

        return $invoice;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(TransactionCategory $category, array $overrides = []): array
    {
        return array_merge([
            'type' => 'income',
            'amount' => 500_000,
            'category_id' => $category->id,
            'transaction_date' => '2026-09-15',
            'payment_method' => 'cash',
        ], $overrides);
    }

    public function test_income_transaction_links_to_an_invoice_and_inherits_its_customer(): void
    {
        [$owner, $category] = $this->company();
        $invoice = $this->invoiceWorth($owner, 1_000_000);

        $this->actingAs($owner)
            ->post(route('transactions.store'), $this->payload($category, [
                'amount' => 600_000,
                'invoice_id' => $invoice->id,
            ]))
            // US-INV-03: an invoice-linked transaction returns to that invoice.
            ->assertRedirect(route('invoices.show', $invoice));

        $this->assertDatabaseHas('transactions', [
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'amount' => 600_000,
        ]);
    }

    public function test_expense_transaction_rejects_an_invoice_link(): void
    {
        [$owner, $income] = $this->company();
        $expenseCategory = TransactionCategory::factory()->for($owner->company)->expense()->create();
        $invoice = $this->invoiceWorth($owner, 1_000_000);

        $this->actingAs($owner)
            ->post(route('transactions.store'), $this->payload($income, [
                'type' => 'expense',
                'category_id' => $expenseCategory->id,
                'invoice_id' => $invoice->id,
            ]))
            ->assertSessionHasErrors('invoice_id');
    }

    public function test_invoice_must_belong_to_the_company(): void
    {
        [$owner, $category] = $this->company();
        [$other] = $this->company();
        $foreignInvoice = $this->invoiceWorth($other, 1_000_000);

        $this->actingAs($owner)
            ->post(route('transactions.store'), $this->payload($category, ['invoice_id' => $foreignInvoice->id]))
            ->assertSessionHasErrors('invoice_id');
    }

    public function test_link_exceeding_the_remaining_balance_is_rejected(): void
    {
        [$owner, $category] = $this->company();
        $invoice = $this->invoiceWorth($owner, 1_000_000);

        $this->actingAs($owner)
            ->post(route('transactions.store'), $this->payload($category, [
                'amount' => 1_200_000,
                'invoice_id' => $invoice->id,
            ]))
            ->assertSessionHasErrors('invoice_id');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_transactions_may_fill_the_invoice_up_to_its_total(): void
    {
        [$owner, $category] = $this->company();
        $invoice = $this->invoiceWorth($owner, 1_000_000);

        $this->actingAs($owner)->post(route('transactions.store'), $this->payload($category, [
            'amount' => 600_000, 'invoice_id' => $invoice->id,
        ]))->assertRedirect();

        $this->actingAs($owner)->post(route('transactions.store'), $this->payload($category, [
            'amount' => 400_000, 'invoice_id' => $invoice->id,
        ]))->assertRedirect();

        // Invoice is now full.
        $this->actingAs($owner)->post(route('transactions.store'), $this->payload($category, [
            'amount' => 1, 'invoice_id' => $invoice->id,
        ]))->assertSessionHasErrors('invoice_id');

        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_edit_recomputes_the_balance_excluding_the_transaction_itself(): void
    {
        [$owner, $category] = $this->company();
        $invoice = $this->invoiceWorth($owner, 1_000_000);

        $this->actingAs($owner)->post(route('transactions.store'), $this->payload($category, [
            'amount' => 600_000, 'invoice_id' => $invoice->id,
        ]));
        $transaction = Transaction::sole();

        // Raising to the full total is fine — its own 600k is excluded.
        $this->actingAs($owner)
            ->put(route('transactions.update', $transaction), $this->payload($category, [
                'amount' => 1_000_000, 'invoice_id' => $invoice->id,
            ]))
            ->assertRedirect(route('transactions.show', $transaction));

        // One rupiah over the total is rejected.
        $this->actingAs($owner)
            ->put(route('transactions.update', $transaction), $this->payload($category, [
                'amount' => 1_000_001, 'invoice_id' => $invoice->id,
            ]))
            ->assertSessionHasErrors('invoice_id');
    }

    public function test_unlinking_on_edit_clears_the_invoice_derived_customer(): void
    {
        [$owner, $category] = $this->company();
        $invoice = $this->invoiceWorth($owner, 1_000_000);

        $this->actingAs($owner)->post(route('transactions.store'), $this->payload($category, [
            'amount' => 500_000, 'invoice_id' => $invoice->id,
        ]));
        $transaction = Transaction::sole();
        $this->assertSame($invoice->customer_id, $transaction->customer_id);

        $this->actingAs($owner)->put(route('transactions.update', $transaction), $this->payload($category, [
            'amount' => 500_000, 'invoice_id' => null,
        ]));

        $transaction->refresh();
        $this->assertNull($transaction->invoice_id);
        $this->assertNull($transaction->customer_id);
    }

    public function test_moving_to_another_invoice_revalidates_against_the_new_one(): void
    {
        [$owner, $category] = $this->company();
        $invoiceA = $this->invoiceWorth($owner, 1_000_000);
        $invoiceB = $this->invoiceWorth($owner, 500_000);

        $this->actingAs($owner)->post(route('transactions.store'), $this->payload($category, [
            'amount' => 900_000, 'invoice_id' => $invoiceA->id,
        ]));
        $transaction = Transaction::sole();

        // 900k does not fit invoice B (500k).
        $this->actingAs($owner)
            ->put(route('transactions.update', $transaction), $this->payload($category, [
                'amount' => 900_000, 'invoice_id' => $invoiceB->id,
            ]))
            ->assertSessionHasErrors('invoice_id');

        // Lowered to fit — moves and inherits invoice B's customer.
        $this->actingAs($owner)->put(route('transactions.update', $transaction), $this->payload($category, [
            'amount' => 400_000, 'invoice_id' => $invoiceB->id,
        ]));

        $transaction->refresh();
        $this->assertSame($invoiceB->id, $transaction->invoice_id);
        $this->assertSame($invoiceB->customer_id, $transaction->customer_id);
    }

    public function test_create_page_exposes_invoices_with_remaining_balance(): void
    {
        [$owner, $category] = $this->company();
        $invoice = $this->invoiceWorth($owner, 1_000_000);
        $this->actingAs($owner)->post(route('transactions.store'), $this->payload($category, [
            'amount' => 300_000, 'invoice_id' => $invoice->id,
        ]));

        $this->actingAs($owner)
            ->get(route('transactions.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('invoices', 1)
                ->where('invoices.0.id', $invoice->id)
                ->where('invoices.0.nominal_total', 1000000)
                ->where('invoices.0.remaining', 700000));
    }

    public function test_invoice_becomes_frozen_once_a_transaction_is_linked(): void
    {
        [$owner, $category] = $this->company();
        $invoice = $this->invoiceWorth($owner, 1_000_000);

        $this->assertFalse($invoice->isFrozen());

        $this->actingAs($owner)->post(route('transactions.store'), $this->payload($category, [
            'amount' => 100_000, 'invoice_id' => $invoice->id,
        ]));

        $this->assertTrue($invoice->fresh()->isFrozen());

        // US-INV-01 AC4: a frozen invoice can no longer be edited or deleted.
        $this->actingAs($owner)->get(route('invoices.edit', $invoice))->assertForbidden();
        $this->actingAs($owner)->delete(route('invoices.destroy', $invoice))->assertForbidden();
    }
}
