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
 * US-INV-03 (shortcut dari detail Invoice), US-INV-04 (progress on-the-fly),
 * US-INV-05 (list Invoice + filter customer + akses Owner & Employee).
 */
class InvoiceProgressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-20 09:00:00');
    }

    /**
     * @return array{0: User, 1: Invoice, 2: TransactionCategory}
     */
    private function ownerWithInvoice(int $total = 1_000_000, string $customerName = 'Toko Melati'): array
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $customer = Customer::factory()->for($owner->company)->create(['name' => $customerName]);
        $invoice = Invoice::factory()->create([
            'company_id' => $owner->company_id,
            'customer_id' => $customer->id,
            'created_by' => $owner->id,
        ]);
        InvoiceItem::factory()->for($invoice)->create(['amount' => $total]);

        return [$owner, $invoice, TransactionCategory::factory()->for($owner->company)->income()->create()];
    }

    private function linkTransaction(User $owner, Invoice $invoice, TransactionCategory $category, int $amount): Transaction
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

    public function test_invoice_detail_exposes_on_the_fly_progress(): void
    {
        [$owner, $invoice, $category] = $this->ownerWithInvoice(1_000_000);
        $this->linkTransaction($owner, $invoice, $category, 400_000);

        $this->actingAs($owner)
            ->get(route('invoices.show', $invoice))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invoice.nominal_total', 1000000)
                ->where('invoice.linked_total', 400000)
                ->where('invoice.remaining', 600000));
    }

    public function test_invoice_list_exposes_progress_per_row(): void
    {
        [$owner, $invoice, $category] = $this->ownerWithInvoice(1_000_000);
        $this->linkTransaction($owner, $invoice, $category, 250_000);

        $this->actingAs($owner)
            ->get(route('invoices.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Invoices/Index')
                ->where('invoices.0.nominal_total', 1000000)
                ->where('invoices.0.linked_total', 250000));
    }

    public function test_progress_ignores_soft_deleted_transactions(): void
    {
        [$owner, $invoice, $category] = $this->ownerWithInvoice(1_000_000);
        $this->linkTransaction($owner, $invoice, $category, 300_000);
        $this->linkTransaction($owner, $invoice, $category, 200_000)->delete();

        $this->actingAs($owner)
            ->get(route('invoices.show', $invoice))
            ->assertInertia(fn (Assert $page) => $page->where('invoice.linked_total', 300000));
    }

    public function test_invoice_list_filters_by_customer_name(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        foreach (['Warung Sari', 'Toko Bintang', 'Kios Sari Rasa'] as $name) {
            $customer = Customer::factory()->for($owner->company)->create(['name' => $name]);
            $invoice = Invoice::factory()->create([
                'company_id' => $owner->company_id,
                'customer_id' => $customer->id,
                'created_by' => $owner->id,
            ]);
            InvoiceItem::factory()->for($invoice)->create(['amount' => 100_000]);
        }

        $this->actingAs($owner)
            ->get(route('invoices.index', ['search' => 'sari']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', 'sari')
                ->has('invoices', 2));
    }

    public function test_employee_can_open_invoice_list_and_detail(): void
    {
        [$owner, $invoice] = $this->ownerWithInvoice();
        $employee = User::factory()->create(['role' => 'employee', 'company_id' => $owner->company_id]);

        $this->actingAs($employee)->get(route('invoices.index'))->assertOk();
        $this->actingAs($employee)->get(route('invoices.show', $invoice))->assertOk();
    }

    public function test_transaction_form_prefills_invoice_from_the_query(): void
    {
        [$owner, $invoice] = $this->ownerWithInvoice();

        $this->actingAs($owner)
            ->get(route('transactions.create', ['invoice_id' => $invoice->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Create')
                ->where('prefill.invoice_id', $invoice->id));
    }

    public function test_transaction_form_ignores_a_foreign_invoice_id(): void
    {
        [$owner] = $this->ownerWithInvoice();
        [$other, $foreignInvoice] = $this->ownerWithInvoice(customerName: 'Lain');

        $this->actingAs($owner)
            ->get(route('transactions.create', ['invoice_id' => $foreignInvoice->id]))
            ->assertInertia(fn (Assert $page) => $page->where('prefill.invoice_id', null));
    }

    public function test_transaction_form_prefill_is_null_without_a_query(): void
    {
        [$owner] = $this->ownerWithInvoice();

        $this->actingAs($owner)
            ->get(route('transactions.create'))
            ->assertInertia(fn (Assert $page) => $page->where('prefill.invoice_id', null));
    }

    public function test_an_invoice_linked_transaction_redirects_back_to_the_invoice(): void
    {
        [$owner, $invoice, $category] = $this->ownerWithInvoice(1_000_000);
        CapitalEntry::factory()->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);

        $payload = [
            'type' => 'income',
            'amount' => 250_000,
            'category_id' => $category->id,
            'transaction_date' => '2026-09-15',
            'payment_method' => 'cash',
        ];

        $this->actingAs($owner)
            ->post(route('transactions.store'), [...$payload, 'invoice_id' => $invoice->id])
            ->assertRedirect(route('invoices.show', $invoice));

        $this->actingAs($owner)
            ->post(route('transactions.store'), $payload)
            ->assertRedirect(route('transactions.index'));
    }
}
