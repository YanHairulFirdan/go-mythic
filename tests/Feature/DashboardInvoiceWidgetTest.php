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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * US-INV-06: dashboard card summarising invoices not yet fully covered by
 * linked transactions. Both counts are on-the-fly (US-INV-04 AC1); no stored
 * status or due-date exists.
 */
class DashboardInvoiceWidgetTest extends TestCase
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
    private function ownerWithIncomeCategory(): array
    {
        $owner = User::factory()->create(['role' => 'owner']);

        return [$owner, TransactionCategory::factory()->for($owner->company)->income()->create()];
    }

    private function invoiceFor(User $owner, int $total): Invoice
    {
        $customer = Customer::factory()->for($owner->company)->create();
        $invoice = Invoice::factory()->create([
            'company_id' => $owner->company_id,
            'customer_id' => $customer->id,
            'created_by' => $owner->id,
        ]);
        InvoiceItem::factory()->for($invoice)->create(['amount' => $total]);

        return $invoice;
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

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    /** AC1 + AC2: a partially covered invoice is both outstanding and partial. */
    public function test_widget_counts_a_partially_covered_invoice(): void
    {
        [$owner, $category] = $this->ownerWithIncomeCategory();
        $invoice = $this->invoiceFor($owner, 1_000_000);
        $this->link($owner, $invoice, $category, 400_000);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('invoiceReminderWidget.outstanding', 1)
                ->where('invoiceReminderWidget.partial', 1));
    }

    /** AC2: an invoice with no linked transaction is outstanding but not partial. */
    public function test_widget_separates_untouched_from_partially_used_invoices(): void
    {
        [$owner, $category] = $this->ownerWithIncomeCategory();
        $this->invoiceFor($owner, 500_000);
        $partiallyUsed = $this->invoiceFor($owner, 1_000_000);
        $this->link($owner, $partiallyUsed, $category, 250_000);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invoiceReminderWidget.outstanding', 2)
                ->where('invoiceReminderWidget.partial', 1));
    }

    /** AC1: soft-deleted transactions do not count towards coverage. */
    public function test_widget_ignores_soft_deleted_transactions(): void
    {
        [$owner, $category] = $this->ownerWithIncomeCategory();
        $invoice = $this->invoiceFor($owner, 1_000_000);
        $this->link($owner, $invoice, $category, 1_000_000)->delete();

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invoiceReminderWidget.outstanding', 1)
                ->where('invoiceReminderWidget.partial', 0));
    }

    /** AC3: card hidden once every invoice is fully covered. */
    public function test_widget_is_null_when_every_invoice_is_fully_covered(): void
    {
        [$owner, $category] = $this->ownerWithIncomeCategory();
        $invoice = $this->invoiceFor($owner, 1_000_000);
        $this->link($owner, $invoice, $category, 600_000);
        $this->link($owner, $invoice, $category, 400_000);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('invoiceReminderWidget', null));
    }

    /** AC3: card hidden when the company has no invoices at all. */
    public function test_widget_is_null_without_any_invoice(): void
    {
        [$owner] = $this->ownerWithIncomeCategory();

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('invoiceReminderWidget', null));
    }

    /** AC1: an invoice without items (nominal_total 0) is not "outstanding". */
    public function test_widget_skips_invoices_without_items(): void
    {
        [$owner] = $this->ownerWithIncomeCategory();
        Invoice::factory()->create([
            'company_id' => $owner->company_id,
            'customer_id' => Customer::factory()->for($owner->company)->create()->id,
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('invoiceReminderWidget', null));
    }

    /** AC4: Employee sees the same card as the Owner. */
    public function test_employee_sees_the_invoice_widget(): void
    {
        [$owner, $category] = $this->ownerWithIncomeCategory();
        $invoice = $this->invoiceFor($owner, 1_000_000);
        $this->link($owner, $invoice, $category, 300_000);
        $employee = User::factory()->create(['role' => 'employee', 'company_id' => $owner->company_id]);

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invoiceReminderWidget.outstanding', 1)
                ->where('invoiceReminderWidget.partial', 1));
    }

    /** AC4: only the signed-in company's invoices are counted. */
    public function test_widget_only_counts_current_company_invoices(): void
    {
        [$owner] = $this->ownerWithIncomeCategory();
        [$other, $otherCategory] = $this->ownerWithIncomeCategory();
        $foreign = $this->invoiceFor($other, 1_000_000);
        $this->link($other, $foreign, $otherCategory, 200_000);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('invoiceReminderWidget', null));
    }
}
