<?php

namespace Tests\Feature;

use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PRD 3.2: master data Kategori transaksi — preset default + kategori custom
 * yang dikelola Owner. Belum ada baris user story tersendiri; scope disepakati
 * dengan user (full master-data slice, Owner-only).
 */
class TransactionCategoryTest extends TestCase
{
    use RefreshDatabase;

    private function ownerWithPresets(): User
    {
        $owner = User::factory()->create(['role' => 'owner']);
        TransactionCategory::seedDefaultsFor($owner->company);

        return $owner;
    }

    private function presetCount(): int
    {
        return count(TransactionCategory::PRESETS['income']) + count(TransactionCategory::PRESETS['expense']);
    }

    public function test_registering_an_owner_seeds_the_preset_categories(): void
    {
        $this->post('/register', [
            'business_name' => 'Warung Bu Sari',
            'owner_name' => 'Bu Sari',
            'email' => 'busari@example.com',
            'whatsapp' => '628123456789',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $companyId = User::where('email', 'busari@example.com')->value('company_id');

        $this->assertSame($this->presetCount(), TransactionCategory::where('company_id', $companyId)->count());
        $this->assertSame(
            $this->presetCount(),
            TransactionCategory::where('company_id', $companyId)->where('is_default', true)->count(),
        );
        $this->assertDatabaseHas('transaction_categories', [
            'company_id' => $companyId,
            'name' => 'Penjualan',
            'type' => 'income',
            'is_default' => true,
        ]);
    }

    public function test_index_defaults_to_the_income_tab(): void
    {
        $owner = $this->ownerWithPresets();

        $this->actingAs($owner)
            ->get(route('transaction-categories.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('TransactionCategories/Index')
                ->where('filters.type', 'income')
                ->where('filters.search', '')
                ->has('categories.data', count(TransactionCategory::PRESETS['income']))
                ->where('categories.data.0.type', 'income'));
    }

    public function test_index_can_switch_to_the_expense_tab(): void
    {
        $owner = $this->ownerWithPresets();

        $this->actingAs($owner)
            ->get(route('transaction-categories.index', ['type' => 'expense']))
            ->assertInertia(fn ($page) => $page
                ->where('filters.type', 'expense')
                ->has('categories.data', count(TransactionCategory::PRESETS['expense']))
                ->where('categories.data.0.type', 'expense'));
    }

    public function test_index_rejects_an_invalid_type(): void
    {
        $owner = $this->ownerWithPresets();

        $this->actingAs($owner)
            ->get(route('transaction-categories.index', ['type' => 'savings']))
            ->assertSessionHasErrors('type');
    }

    public function test_index_search_filters_by_name(): void
    {
        $owner = $this->ownerWithPresets();

        $this->actingAs($owner)
            ->get(route('transaction-categories.index', ['type' => 'income', 'search' => 'Penjualan']))
            ->assertInertia(fn ($page) => $page
                ->where('filters.search', 'Penjualan')
                ->has('categories.data', 1)
                ->where('categories.data.0.name', 'Penjualan'));
    }

    public function test_index_paginates_within_a_tab(): void
    {
        $owner = $this->ownerWithPresets();
        TransactionCategory::factory()->for($owner->company)->income()->count(25)->create();

        $expectedTotal = 25 + count(TransactionCategory::PRESETS['income']);

        $this->actingAs($owner)
            ->get(route('transaction-categories.index', ['type' => 'income']))
            ->assertInertia(fn ($page) => $page
                ->has('categories.data', 20)
                ->where('categories.current_page', 1)
                ->where('categories.total', $expectedTotal));

        $this->actingAs($owner)
            ->get(route('transaction-categories.index', ['type' => 'income', 'page' => 2]))
            ->assertInertia(fn ($page) => $page
                ->has('categories.data', $expectedTotal - 20)
                ->where('categories.current_page', 2));
    }

    public function test_each_row_carries_a_transactions_count(): void
    {
        $owner = $this->ownerWithPresets();

        $this->actingAs($owner)
            ->get(route('transaction-categories.index'))
            ->assertInertia(fn ($page) => $page->has('categories.data.0.transactions_count'));
    }

    public function test_index_rejects_employee(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAs($employee)
            ->get(route('transaction-categories.index'))
            ->assertForbidden();
    }

    public function test_index_redirects_guest_to_login(): void
    {
        $this->get(route('transaction-categories.index'))->assertRedirect(route('login'));
    }

    public function test_index_is_tenant_scoped(): void
    {
        $owner = $this->ownerWithPresets();
        $other = $this->ownerWithPresets();
        TransactionCategory::factory()->for($other->company)->income()->create(['name' => 'Rahasia Tetangga']);

        $this->actingAs($owner)
            ->get(route('transaction-categories.index', ['type' => 'income']))
            ->assertInertia(fn ($page) => $page
                ->has('categories.data', count(TransactionCategory::PRESETS['income']))
                ->where('categories.data', fn ($rows) => collect($rows)->doesntContain('name', 'Rahasia Tetangga')));
    }

    public function test_owner_adds_a_custom_category(): void
    {
        $owner = $this->ownerWithPresets();

        $this->actingAs($owner)
            ->post(route('transaction-categories.store'), ['name' => 'Katering', 'type' => 'income'])
            ->assertRedirect(route('transaction-categories.index'));

        $this->assertDatabaseHas('transaction_categories', [
            'company_id' => $owner->company_id,
            'name' => 'Katering',
            'type' => 'income',
            'is_default' => false,
        ]);
    }

    public function test_store_validates_input(): void
    {
        $owner = $this->ownerWithPresets();

        $this->actingAs($owner)
            ->post(route('transaction-categories.store'), ['type' => 'income'])
            ->assertSessionHasErrors('name');

        $this->actingAs($owner)
            ->post(route('transaction-categories.store'), ['name' => 'X', 'type' => 'savings'])
            ->assertSessionHasErrors('type');

        // Duplicate of an existing preset (same company + type + name).
        $this->actingAs($owner)
            ->post(route('transaction-categories.store'), ['name' => 'Penjualan', 'type' => 'income'])
            ->assertSessionHasErrors('name');
    }

    public function test_same_name_is_allowed_across_different_types(): void
    {
        $owner = $this->ownerWithPresets();

        $this->actingAs($owner)
            ->post(route('transaction-categories.store'), ['name' => 'Penjualan', 'type' => 'expense'])
            ->assertRedirect(route('transaction-categories.index'));

        $this->assertDatabaseHas('transaction_categories', [
            'company_id' => $owner->company_id,
            'name' => 'Penjualan',
            'type' => 'expense',
        ]);
    }

    public function test_store_rejects_employee(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAs($employee)
            ->post(route('transaction-categories.store'), ['name' => 'X', 'type' => 'income'])
            ->assertForbidden();
    }

    public function test_store_returns_json_for_quick_create(): void
    {
        $owner = $this->ownerWithPresets();

        $response = $this->actingAs($owner)
            ->postJson(route('transaction-categories.store'), ['name' => 'Konsinyasi', 'type' => 'income']);

        $response->assertCreated()
            ->assertJsonPath('category.name', 'Konsinyasi')
            ->assertJsonPath('category.type', 'income')
            ->assertJsonStructure(['category' => ['id', 'name', 'type']]);
    }

    public function test_owner_renames_a_custom_category(): void
    {
        $owner = $this->ownerWithPresets();
        $category = TransactionCategory::factory()->for($owner->company)->create(['name' => 'Lama', 'type' => 'expense']);

        $this->actingAs($owner)
            ->put(route('transaction-categories.update', $category), ['name' => 'Baru', 'type' => 'expense'])
            ->assertRedirect(route('transaction-categories.index'));

        $this->assertSame('Baru', $category->fresh()->name);
    }

    public function test_preset_categories_cannot_be_edited_or_deleted(): void
    {
        $owner = $this->ownerWithPresets();
        $preset = TransactionCategory::where('company_id', $owner->company_id)->where('is_default', true)->first();

        $this->actingAs($owner)
            ->put(route('transaction-categories.update', $preset), ['name' => 'Ganti', 'type' => $preset->type])
            ->assertForbidden();

        $this->actingAs($owner)
            ->delete(route('transaction-categories.destroy', $preset))
            ->assertForbidden();

        $this->assertDatabaseHas('transaction_categories', ['id' => $preset->id, 'name' => $preset->name]);
    }

    public function test_owner_soft_deletes_a_custom_category(): void
    {
        $owner = $this->ownerWithPresets();
        $category = TransactionCategory::factory()->for($owner->company)->create();

        $this->actingAs($owner)
            ->delete(route('transaction-categories.destroy', $category))
            ->assertRedirect(route('transaction-categories.index'));

        $this->assertSoftDeleted($category);
    }

    public function test_update_and_delete_are_tenant_scoped(): void
    {
        $owner = $this->ownerWithPresets();
        $other = $this->ownerWithPresets();
        $foreign = TransactionCategory::factory()->for($other->company)->create();

        $this->actingAs($owner)
            ->put(route('transaction-categories.update', $foreign), ['name' => 'Z', 'type' => $foreign->type])
            ->assertNotFound();

        $this->actingAs($owner)
            ->delete(route('transaction-categories.destroy', $foreign))
            ->assertNotFound();
    }

    public function test_delete_rejects_employee(): void
    {
        $owner = $this->ownerWithPresets();
        $category = TransactionCategory::factory()->for($owner->company)->create();
        $employee = User::factory()->create(['role' => 'employee', 'company_id' => $owner->company_id]);

        $this->actingAs($employee)
            ->delete(route('transaction-categories.destroy', $category))
            ->assertForbidden();
    }
}
