<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvoicePaymentStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-18 09:00:00');
    }

    private function invoiceFor(User $user, int $total = 1_000_000, ?string $dueDate = null): Invoice
    {
        $customer = Customer::factory()->for($user->company)->create();
        $invoice = Invoice::factory()->create([
            'company_id' => $user->company_id,
            'customer_id' => $customer->id,
            'created_by' => $user->id,
            'due_date' => $dueDate,
        ]);
        InvoiceItem::factory()->for($invoice)->create(['amount' => $total]);

        return $invoice;
    }

    private function link(User $user, Invoice $invoice, int $amount): Transaction
    {
        $category = TransactionCategory::factory()->for($user->company)->income()->create();

        return Transaction::factory()->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'type' => 'income',
            'category_id' => $category->id,
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'amount' => $amount,
            'transaction_date' => '2026-09-15',
        ]);
    }

    public function test_due_date_is_optional_and_can_be_created_and_updated_before_freeze(): void
    {
        $owner = User::factory()->create();
        $customer = Customer::factory()->for($owner->company)->create();
        $payload = [
            'customer_id' => $customer->id,
            'due_date' => '2026-09-30',
            'items' => [['description' => 'Jasa', 'amount' => 100000]],
        ];

        $this->actingAs($owner)->post(route('invoices.store'), $payload)->assertSessionHasNoErrors();
        $invoice = Invoice::sole();
        $this->assertSame('2026-09-30', $invoice->due_date->toDateString());

        $this->actingAs($owner)->patch(route('invoices.update', $invoice), [...$payload, 'due_date' => '2026-10-01'])
            ->assertSessionHasNoErrors();
        $this->assertSame('2026-10-01', $invoice->fresh()->due_date->toDateString());
    }

    public function test_due_date_must_be_a_date(): void
    {
        $owner = User::factory()->create();
        $customer = Customer::factory()->for($owner->company)->create();

        $this->actingAs($owner)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'due_date' => 'not-a-date',
            'items' => [['description' => 'Jasa', 'amount' => 100000]],
        ])->assertInvalid('due_date');
    }

    public function test_status_is_derived_from_active_linked_transactions(): void
    {
        $owner = User::factory()->create();
        $unpaid = $this->invoiceFor($owner);
        $partial = $this->invoiceFor($owner);
        $paid = $this->invoiceFor($owner);
        $this->link($owner, $partial, 400000);
        $this->link($owner, $paid, 1000000);

        $this->actingAs($owner)->get(route('invoices.index', ['status' => 'lunas']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('invoices', 1)
                ->where('invoices.0.id', $paid->id)
                ->where('invoices.0.status_key', 'lunas'));
        $this->actingAs($owner)->get(route('invoices.index', ['status' => 'dp']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('invoices', 1)
                ->where('invoices.0.id', $partial->id)
                ->where('invoices.0.status_key', 'dp'));
        $this->actingAs($owner)->get(route('invoices.index', ['status' => 'belum_dibayar']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('invoices', 1)
                ->where('invoices.0.id', $unpaid->id)
                ->where('invoices.0.status_key', 'belum_dibayar'));

        $this->assertSame('belum_dibayar', $unpaid->paymentStatus());
        $this->assertSame('dp', $partial->paymentStatus());
        $this->assertSame('lunas', $paid->paymentStatus());
    }

    public function test_overdue_requires_a_past_due_date_and_remaining_balance(): void
    {
        $owner = User::factory()->create();
        $overdue = $this->invoiceFor($owner, dueDate: '2026-09-17');
        $paid = $this->invoiceFor($owner, dueDate: '2026-09-17');
        $today = $this->invoiceFor($owner, dueDate: '2026-09-18');
        $this->link($owner, $paid, 1000000);

        $this->assertTrue($overdue->isOverdue());
        $this->assertFalse($paid->isOverdue());
        $this->assertFalse($today->isOverdue());
    }

    public function test_index_and_detail_expose_status_due_date_remaining_and_overdue(): void
    {
        $owner = User::factory()->create();
        $invoice = $this->invoiceFor($owner, dueDate: '2026-09-17');
        $this->link($owner, $invoice, 250000);

        $this->actingAs($owner)->get(route('invoices.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invoices.0.status', 'DP')
                ->where('invoices.0.due_date', '2026-09-17')
                ->where('invoices.0.remaining', 750000)
                ->where('invoices.0.is_overdue', true));

        $this->actingAs($owner)->get(route('invoices.show', $invoice))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invoice.status', 'DP')
                ->where('invoice.due_date', '2026-09-17')
                ->where('invoice.remaining', 750000)
                ->where('invoice.is_overdue', true));
    }

    public function test_index_filters_payment_status_and_overdue_invoices(): void
    {
        $owner = User::factory()->create();
        $unpaid = $this->invoiceFor($owner);
        $partial = $this->invoiceFor($owner, dueDate: '2026-09-17');
        $paid = $this->invoiceFor($owner);
        $this->link($owner, $partial, 400000);
        $this->link($owner, $paid, 1000000);

        $this->actingAs($owner)->get(route('invoices.index', ['status' => 'dp']))
            ->assertInertia(fn (Assert $page) => $page->has('invoices', 1)->where('invoices.0.id', $partial->id));
        $this->actingAs($owner)->get(route('invoices.index', ['status' => 'jatuh_tempo']))
            ->assertInertia(fn (Assert $page) => $page->has('invoices', 1)->where('invoices.0.id', $partial->id));
        $this->assertNotSame($unpaid->id, $paid->id);
    }

    public function test_detail_lists_linked_transactions_newest_first(): void
    {
        $owner = User::factory()->create();
        $invoice = $this->invoiceFor($owner);
        $first = $this->link($owner, $invoice, 400000);
        $second = Transaction::factory()->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'type' => 'income',
            'category_id' => $first->category_id,
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'amount' => 600000,
            'transaction_date' => '2026-09-17',
        ]);

        $this->actingAs($owner)->get(route('invoices.show', $invoice))
            ->assertInertia(fn (Assert $page) => $page
                ->has('transactions', 2)
                ->where('transactions.0.id', $second->id)
                ->where('transactions.0.amount', 600000)
                ->where('transactions.1.id', $first->id));
    }

    public function test_detail_transaction_list_excludes_other_invoices_and_is_empty_when_unpaid(): void
    {
        $owner = User::factory()->create();
        $invoice = $this->invoiceFor($owner);
        $otherInvoice = $this->invoiceFor($owner);
        $this->link($owner, $otherInvoice, 500000);

        $this->actingAs($owner)->get(route('invoices.show', $invoice))
            ->assertInertia(fn (Assert $page) => $page->has('transactions', 0));
    }

    public function test_status_and_total_are_not_stored_columns(): void
    {
        $this->assertFalse(Schema::hasColumn('invoices', 'status'));
        $this->assertFalse(Schema::hasColumn('invoices', 'total'));
        $this->assertTrue(Schema::hasColumn('invoices', 'due_date'));
    }

    /** US-INV-07 AC5: dashboard exposes DP, overdue and outstanding counts. */
    public function test_dashboard_widget_exposes_overdue_count(): void
    {
        $owner = User::factory()->create();
        $this->invoiceFor($owner, dueDate: '2026-09-17');
        $partialOverdue = $this->invoiceFor($owner, dueDate: '2026-09-10');
        $this->link($owner, $partialOverdue, 400000);
        $paid = $this->invoiceFor($owner, dueDate: '2026-09-17');
        $this->link($owner, $paid, 1000000);

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invoiceReminderWidget.outstanding', 2)
                ->where('invoiceReminderWidget.partial', 1)
                ->where('invoiceReminderWidget.overdue', 2));
    }
}
