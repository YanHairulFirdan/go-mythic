<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function itemsPayload(): array
    {
        return [
            ['description' => 'Cleaning ruang tamu', 'amount' => 900000],
            ['description' => 'Cleaning 2 kamar mandi', 'amount' => 600000],
        ];
    }

    public function test_guest_is_redirected_from_invoices_index(): void
    {
        $this->get(route('invoices.index'))->assertRedirect(route('login'));
    }

    public function test_index_shows_only_current_company_invoices(): void
    {
        $owner = User::factory()->create();
        $mine = Invoice::factory()
            ->for(Customer::factory()->state(['company_id' => $owner->company_id]))
            ->create(['company_id' => $owner->company_id, 'created_by' => $owner->id]);
        Invoice::factory()->create();

        $this->actingAs($owner)
            ->get(route('invoices.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Invoices/Index')
                ->has('invoices', 1)
                ->where('invoices.0.id', $mine->id));
    }

    public function test_owner_can_create_an_invoice_with_items(): void
    {
        $owner = User::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $owner->company_id]);
        $employee = Employee::factory()->create(['company_id' => $owner->company_id]);

        $this->actingAs($owner)
            ->post(route('invoices.store'), [
                'customer_id' => $customer->id,
                'employee_id' => $employee->id,
                'items' => $this->itemsPayload(),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('invoices.index'));

        $this->assertDatabaseHas('invoices', [
            'company_id' => $owner->company_id,
            'customer_id' => $customer->id,
            'employee_id' => $employee->id,
            'created_by' => $owner->id,
        ]);
        $this->assertDatabaseCount('invoice_items', 2);
        $this->assertDatabaseHas('invoice_items', ['description' => 'Cleaning ruang tamu', 'amount' => 900000]);
    }

    public function test_employee_can_create_an_invoice(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $customer = Customer::factory()->create(['company_id' => $employee->company_id]);

        $this->actingAs($employee)
            ->post(route('invoices.store'), [
                'customer_id' => $customer->id,
                'items' => [['description' => 'Jasa', 'amount' => 100000]],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('invoices.index'));

        $this->assertDatabaseHas('invoices', [
            'company_id' => $employee->company_id,
            'created_by' => $employee->id,
        ]);
    }

    public function test_customer_is_required(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->post(route('invoices.store'), ['items' => [['description' => 'x', 'amount' => 1000]]])
            ->assertInvalid('customer_id');
    }

    public function test_cannot_use_another_companys_customer(): void
    {
        $owner = User::factory()->create();
        $foreignCustomer = Customer::factory()->create();

        $this->actingAs($owner)
            ->post(route('invoices.store'), [
                'customer_id' => $foreignCustomer->id,
                'items' => [['description' => 'x', 'amount' => 1000]],
            ])
            ->assertInvalid('customer_id');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_employee_id_is_optional(): void
    {
        $owner = User::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $owner->company_id]);

        $this->actingAs($owner)
            ->post(route('invoices.store'), [
                'customer_id' => $customer->id,
                'items' => [['description' => 'x', 'amount' => 1000]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('invoices', ['customer_id' => $customer->id, 'employee_id' => null]);
    }

    public function test_cannot_use_another_companys_employee(): void
    {
        $owner = User::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $owner->company_id]);
        $foreignEmployee = Employee::factory()->create();

        $this->actingAs($owner)
            ->post(route('invoices.store'), [
                'customer_id' => $customer->id,
                'employee_id' => $foreignEmployee->id,
                'items' => [['description' => 'x', 'amount' => 1000]],
            ])
            ->assertInvalid('employee_id');
    }

    public function test_at_least_one_item_is_required(): void
    {
        $owner = User::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $owner->company_id]);

        $this->actingAs($owner)
            ->post(route('invoices.store'), ['customer_id' => $customer->id, 'items' => []])
            ->assertInvalid('items');
    }

    public function test_item_needs_description_and_positive_amount(): void
    {
        $owner = User::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $owner->company_id]);

        $this->actingAs($owner)
            ->post(route('invoices.store'), [
                'customer_id' => $customer->id,
                'items' => [['description' => '', 'amount' => 0]],
            ])
            ->assertInvalid(['items.0.description', 'items.0.amount']);

        $this->actingAs($owner)
            ->post(route('invoices.store'), [
                'customer_id' => $customer->id,
                'items' => [['description' => 'x', 'amount' => -5]],
            ])
            ->assertInvalid('items.0.amount');
    }

    public function test_nominal_total_is_the_sum_of_items_and_not_client_supplied(): void
    {
        $owner = User::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $owner->company_id]);

        $this->actingAs($owner)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'nominal_total' => 999999,
            'items' => $this->itemsPayload(),
        ])->assertSessionHasNoErrors();

        $invoice = Invoice::first();

        $this->assertSame(1500000.0, (float) $invoice->nominalTotal());
        $this->assertFalse(Schema::hasColumn('invoices', 'nominal_total'));
    }

    public function test_invoices_table_has_no_status_column(): void
    {
        $this->assertFalse(Schema::hasColumn('invoices', 'status'));
    }

    public function test_user_can_view_own_invoice_detail_with_items_and_total(): void
    {
        $owner = User::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $owner->company_id]);
        $invoice = Invoice::factory()
            ->for($customer)
            ->hasItems(2, ['amount' => 250000])
            ->create(['company_id' => $owner->company_id, 'created_by' => $owner->id]);

        $this->actingAs($owner)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Invoices/Show')
                ->where('invoice.id', $invoice->id)
                ->where('invoice.nominal_total', 500000)
                ->has('invoice.items', 2));
    }

    public function test_cannot_view_another_companys_invoice(): void
    {
        $owner = User::factory()->create();
        $foreign = Invoice::factory()->create();

        $this->actingAs($owner)
            ->get(route('invoices.show', $foreign))
            ->assertNotFound();
    }

    public function test_owner_can_update_invoice_and_replace_items(): void
    {
        $owner = User::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $owner->company_id]);
        $newCustomer = Customer::factory()->create(['company_id' => $owner->company_id]);
        $invoice = Invoice::factory()
            ->for($customer)
            ->hasItems(3)
            ->create(['company_id' => $owner->company_id, 'created_by' => $owner->id]);

        $this->actingAs($owner)
            ->patch(route('invoices.update', $invoice), [
                'customer_id' => $newCustomer->id,
                'items' => [['description' => 'Item baru', 'amount' => 123000]],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('invoices.index'));

        $invoice->refresh();
        $this->assertSame($newCustomer->id, $invoice->customer_id);
        $this->assertDatabaseCount('invoice_items', 1);
        $this->assertDatabaseHas('invoice_items', ['invoice_id' => $invoice->id, 'description' => 'Item baru']);
    }

    public function test_cannot_update_another_companys_invoice(): void
    {
        $owner = User::factory()->create();
        $foreign = Invoice::factory()->create();

        $this->actingAs($owner)
            ->patch(route('invoices.update', $foreign), [
                'customer_id' => Customer::factory()->create(['company_id' => $owner->company_id])->id,
                'items' => [['description' => 'x', 'amount' => 1000]],
            ])
            ->assertNotFound();
    }

    public function test_owner_can_soft_delete_an_invoice(): void
    {
        $owner = User::factory()->create();
        $invoice = Invoice::factory()
            ->for(Customer::factory()->state(['company_id' => $owner->company_id]))
            ->create(['company_id' => $owner->company_id, 'created_by' => $owner->id]);

        $this->actingAs($owner)
            ->delete(route('invoices.destroy', $invoice))
            ->assertRedirect(route('invoices.index'));

        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
    }

    public function test_invoice_without_linked_transactions_is_not_frozen(): void
    {
        // US-INV-01 AC4: freeze triggers once a non-soft-deleted linked
        // transaction exists; the transactions table lands in Feature 4, so
        // for now every invoice is editable.
        $invoice = Invoice::factory()->create();

        $this->assertFalse($invoice->isFrozen());
    }
}
