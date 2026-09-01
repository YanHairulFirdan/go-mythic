<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * US-TR-04: Melihat daftar transaksi.
 */
class TransactionListTest extends TestCase
{
    use RefreshDatabase;

    private function makeTransaction(User $creator, array $attributes = []): Transaction
    {
        return Transaction::factory()->create(array_merge([
            'company_id' => $creator->company_id,
            'created_by' => $creator->id,
        ], $attributes));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('transactions.index'))->assertRedirect(route('login'));
    }

    public function test_owner_sees_all_company_transactions(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $employee = User::factory()->create(['role' => 'employee', 'company_id' => $owner->company_id]);

        $this->makeTransaction($owner);
        $this->makeTransaction($employee);

        $this->actingAs($owner)
            ->get(route('transactions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Index')
                ->has('transactions.data', 2));
    }

    public function test_employee_sees_only_their_own_transactions(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $employee = User::factory()->create(['role' => 'employee', 'company_id' => $owner->company_id]);

        $mine = $this->makeTransaction($employee);
        $this->makeTransaction($owner);

        $this->actingAs($employee)
            ->get(route('transactions.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('transactions.data', 1)
                ->where('transactions.data.0.id', $mine->id));
    }

    public function test_list_is_tenant_scoped(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->makeTransaction($owner);
        $this->makeTransaction(User::factory()->create(['role' => 'owner'])); // other company

        $this->actingAs($owner)
            ->get(route('transactions.index'))
            ->assertInertia(fn (Assert $page) => $page->has('transactions.data', 1));
    }

    public function test_filter_by_type(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->makeTransaction($owner, ['type' => 'income']);
        $this->makeTransaction($owner, ['type' => 'expense']);
        $this->makeTransaction($owner, ['type' => 'expense']);

        $this->actingAs($owner)
            ->get(route('transactions.index', ['type' => 'expense']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.type', 'expense')
                ->has('transactions.data', 2)
                ->where('transactions.data.0.type', 'expense'));
    }

    public function test_filter_by_category(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $category = TransactionCategory::factory()->for($owner->company)->income()->create();
        $this->makeTransaction($owner, ['type' => 'income', 'category_id' => $category->id]);
        $this->makeTransaction($owner, ['type' => 'income']);

        $this->actingAs($owner)
            ->get(route('transactions.index', ['category_id' => $category->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.category_id', $category->id)
                ->has('transactions.data', 1));
    }

    public function test_filter_by_date_range_is_inclusive(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->makeTransaction($owner, ['transaction_date' => '2026-09-01']);
        $this->makeTransaction($owner, ['transaction_date' => '2026-09-15']);
        $this->makeTransaction($owner, ['transaction_date' => '2026-09-30']);

        $this->actingAs($owner)
            ->get(route('transactions.index', ['date_from' => '2026-09-15', 'date_to' => '2026-09-30']))
            ->assertInertia(fn (Assert $page) => $page->has('transactions.data', 2));
    }

    public function test_results_are_paginated(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        Transaction::factory()->count(25)->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('transactions.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('transactions.data', 20)
                ->where('transactions.current_page', 1)
                ->where('transactions.total', 25));

        $this->actingAs($owner)
            ->get(route('transactions.index', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->has('transactions.data', 5));
    }

    public function test_results_are_newest_date_first(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->makeTransaction($owner, ['transaction_date' => '2026-09-05']);
        $newest = $this->makeTransaction($owner, ['transaction_date' => '2026-09-20']);
        $this->makeTransaction($owner, ['transaction_date' => '2026-09-12']);

        $this->actingAs($owner)
            ->get(route('transactions.index'))
            ->assertInertia(fn (Assert $page) => $page->where('transactions.data.0.id', $newest->id));
    }

    public function test_category_options_are_provided_for_the_filter(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        TransactionCategory::factory()->for($owner->company)->count(3)->create();

        $this->actingAs($owner)
            ->get(route('transactions.index'))
            ->assertInertia(fn (Assert $page) => $page->has('categories', 3));
    }

    public function test_invalid_type_filter_is_rejected(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('transactions.index', ['type' => 'savings']))
            ->assertSessionHasErrors('type');
    }
}
