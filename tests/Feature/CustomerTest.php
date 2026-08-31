<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_customers_index(): void
    {
        $this->get(route('customers.index'))->assertRedirect(route('login'));
    }

    public function test_index_shows_only_current_company_customers(): void
    {
        $owner = User::factory()->create();
        $mine = Customer::factory()->create(['company_id' => $owner->company_id, 'name' => 'Toko Bintang']);
        Customer::factory()->create(['name' => 'Customer Lain']);

        $this->actingAs($owner)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Customers/Index')
                ->has('customers', 1)
                ->where('customers.0.id', $mine->id)
                ->where('customers.0.name', 'Toko Bintang'));
    }

    public function test_owner_can_create_a_customer(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->post(route('customers.store'), [
                'name' => 'Bali Coffee Co.',
                'contact' => '0819-0000-1122',
                'address' => 'Jl. Teuku Umar No.21, Denpasar',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('customers.index'));

        $this->assertDatabaseHas('customers', [
            'company_id' => $owner->company_id,
            'name' => 'Bali Coffee Co.',
            'contact' => '0819-0000-1122',
            'address' => 'Jl. Teuku Umar No.21, Denpasar',
        ]);
    }

    public function test_employee_can_create_a_customer(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAs($employee)
            ->post(route('customers.store'), ['name' => 'Pak Wayan'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('customers.index'));

        $this->assertDatabaseHas('customers', [
            'company_id' => $employee->company_id,
            'name' => 'Pak Wayan',
        ]);
    }

    public function test_name_is_required(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->post(route('customers.store'), ['name' => '', 'contact' => '08123'])
            ->assertInvalid('name');

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_contact_and_address_are_optional(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->post(route('customers.store'), ['name' => 'Minim'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('customers', [
            'company_id' => $owner->company_id,
            'name' => 'Minim',
            'contact' => null,
            'address' => null,
        ]);
    }

    public function test_store_returns_json_for_xhr_quick_create(): void
    {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)
            ->postJson(route('customers.store'), ['name' => 'Quick Add']);

        $response->assertCreated()
            ->assertJsonPath('customer.name', 'Quick Add')
            ->assertJsonStructure(['customer' => ['id', 'name', 'contact', 'address']]);

        $this->assertDatabaseHas('customers', [
            'id' => $response->json('customer.id'),
            'company_id' => $owner->company_id,
            'name' => 'Quick Add',
        ]);
    }

    public function test_new_customer_belongs_to_actor_company(): void
    {
        $owner = User::factory()->create();
        $otherCompany = Company::factory()->create();

        $this->actingAs($owner)
            ->post(route('customers.store'), [
                'name' => 'Spoof',
                'company_id' => $otherCompany->id,
            ]);

        $this->assertDatabaseHas('customers', ['name' => 'Spoof', 'company_id' => $owner->company_id]);
        $this->assertDatabaseMissing('customers', ['name' => 'Spoof', 'company_id' => $otherCompany->id]);
    }

    public function test_user_can_view_own_customer(): void
    {
        $owner = User::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $owner->company_id]);

        $this->actingAs($owner)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Customers/Show')
                ->where('customer.id', $customer->id)
                ->where('customer.name', $customer->name));
    }

    public function test_cannot_view_another_companys_customer(): void
    {
        $owner = User::factory()->create();
        $foreign = Customer::factory()->create();

        $this->actingAs($owner)
            ->get(route('customers.show', $foreign))
            ->assertNotFound();
    }

    public function test_owner_can_update_a_customer(): void
    {
        $owner = User::factory()->create();
        $customer = Customer::factory()->create([
            'company_id' => $owner->company_id,
            'name' => 'Lama',
        ]);

        $this->actingAs($owner)
            ->patch(route('customers.update', $customer), [
                'name' => 'Baru',
                'contact' => '0812-9999',
                'address' => null,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('customers.index'));

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Baru',
            'contact' => '0812-9999',
        ]);
    }

    public function test_cannot_update_another_companys_customer(): void
    {
        $owner = User::factory()->create();
        $foreign = Customer::factory()->create(['name' => 'Asing']);

        $this->actingAs($owner)
            ->patch(route('customers.update', $foreign), ['name' => 'Diubah'])
            ->assertNotFound();

        $this->assertDatabaseHas('customers', ['id' => $foreign->id, 'name' => 'Asing']);
    }

    public function test_owner_can_soft_delete_a_customer(): void
    {
        $owner = User::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $owner->company_id]);

        $this->actingAs($owner)
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'));

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_cannot_delete_another_companys_customer(): void
    {
        $owner = User::factory()->create();
        $foreign = Customer::factory()->create();

        $this->actingAs($owner)
            ->delete(route('customers.destroy', $foreign))
            ->assertNotFound();

        $this->assertDatabaseHas('customers', ['id' => $foreign->id]);
    }
}
