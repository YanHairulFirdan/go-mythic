<?php

namespace Tests\Feature;

use App\Models\CapitalEntry;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * US-TR-02: Mengedit transaksi.
 */
class TransactionEditTest extends TestCase
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
            TransactionCategory::factory()->for($user->company)->income()->create(['name' => 'Penjualan']),
            TransactionCategory::factory()->for($user->company)->expense()->create(['name' => 'Belanja']),
        ];
    }

    private function makeTransaction(User $creator, TransactionCategory $category, array $overrides = []): Transaction
    {
        return Transaction::factory()->create(array_merge([
            'company_id' => $creator->company_id,
            'created_by' => $creator->id,
            'type' => $category->type,
            'category_id' => $category->id,
            'amount' => 100_000,
            'transaction_date' => '2026-09-10',
            'payment_method' => 'cash',
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Transaction $transaction, array $overrides = []): array
    {
        return array_merge([
            'type' => $transaction->type,
            'amount' => (float) $transaction->amount,
            'category_id' => $transaction->category_id,
            'transaction_date' => $transaction->transaction_date,
            'payment_method' => $transaction->payment_method,
            'notes' => $transaction->notes,
        ], $overrides);
    }

    public function test_guest_cannot_edit_or_update(): void
    {
        [$owner, $income] = $this->company();
        $transaction = $this->makeTransaction($owner, $income);

        $this->get(route('transactions.edit', $transaction))->assertRedirect(route('login'));
        $this->put(route('transactions.update', $transaction), $this->payload($transaction))->assertRedirect(route('login'));
    }

    public function test_edit_page_prefills_the_transaction(): void
    {
        [$owner, $income] = $this->company();
        $transaction = $this->makeTransaction($owner, $income, ['amount' => 250_000, 'notes' => 'Awal']);

        $this->actingAs($owner)
            ->get(route('transactions.edit', $transaction))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Edit')
                ->where('transaction.id', $transaction->id)
                ->where('transaction.amount', 250000)
                ->where('transaction.category_id', $income->id)
                ->where('transaction.notes', 'Awal')
                ->has('categories', 2));
    }

    public function test_owner_updates_a_transaction(): void
    {
        [$owner, $income] = $this->company();
        $transaction = $this->makeTransaction($owner, $income);

        $this->actingAs($owner)
            ->put(route('transactions.update', $transaction), $this->payload($transaction, [
                'amount' => 175_000,
                'notes' => 'Revisi nominal',
            ]))
            ->assertRedirect(route('transactions.show', $transaction));

        $transaction->refresh();
        $this->assertSame('175000.00', $transaction->amount);
        $this->assertSame('Revisi nominal', $transaction->notes);
        $this->assertSame($owner->id, $transaction->updated_by);
    }

    public function test_update_writes_an_updated_activity_log(): void
    {
        [$owner, $income] = $this->company();
        $transaction = $this->makeTransaction($owner, $income, ['amount' => 100_000]);

        $this->actingAs($owner)->put(route('transactions.update', $transaction), $this->payload($transaction, [
            'amount' => 200_000,
        ]));

        $activity = Activity::query()
            ->where('log_name', 'transaction')
            ->where('event', 'updated')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($owner->id, $activity->causer_id);
        $this->assertSame('200000.00', (string) $activity->properties['attributes']['amount']);
        $this->assertSame('100000.00', (string) $activity->properties['old']['amount']);
    }

    public function test_employee_can_edit_their_own_transaction(): void
    {
        [$owner, $income] = $this->company();
        $employee = User::factory()->create(['role' => 'employee', 'company_id' => $owner->company_id]);
        $transaction = $this->makeTransaction($employee, $income);

        $this->actingAs($employee)
            ->put(route('transactions.update', $transaction), $this->payload($transaction, ['amount' => 123_000]))
            ->assertRedirect(route('transactions.show', $transaction));

        $this->assertSame('123000.00', $transaction->refresh()->amount);
    }

    public function test_employee_cannot_edit_another_users_transaction(): void
    {
        [$owner, $income] = $this->company();
        $employee = User::factory()->create(['role' => 'employee', 'company_id' => $owner->company_id]);
        $transaction = $this->makeTransaction($owner, $income);

        $this->actingAs($employee)->get(route('transactions.edit', $transaction))->assertNotFound();
        $this->actingAs($employee)->put(route('transactions.update', $transaction), $this->payload($transaction))->assertNotFound();
    }

    public function test_update_is_tenant_scoped(): void
    {
        [$owner, $income] = $this->company();
        [$other, $otherIncome] = $this->company();
        $foreign = $this->makeTransaction($other, $otherIncome);

        $this->actingAs($owner)
            ->put(route('transactions.update', $foreign), $this->payload($foreign))
            ->assertNotFound();
    }

    public function test_update_validation(): void
    {
        [$owner, $income] = $this->company();
        $transaction = $this->makeTransaction($owner, $income);

        $this->actingAs($owner)
            ->put(route('transactions.update', $transaction), $this->payload($transaction, ['amount' => 0]))
            ->assertSessionHasErrors('amount');

        $this->actingAs($owner)
            ->put(route('transactions.update', $transaction), $this->payload($transaction, ['transaction_date' => '2026-09-21']))
            ->assertSessionHasErrors('transaction_date');

        $this->actingAs($owner)
            ->put(route('transactions.update', $transaction), $this->payload($transaction, ['transaction_date' => '2026-08-15']))
            ->assertSessionHasErrors('transaction_date');
    }

    public function test_changing_type_requires_a_matching_category(): void
    {
        [$owner, $income, $expense] = $this->company();
        $transaction = $this->makeTransaction($owner, $income);

        // New type expense but still pointing at the income category.
        $this->actingAs($owner)
            ->put(route('transactions.update', $transaction), $this->payload($transaction, ['type' => 'expense']))
            ->assertSessionHasErrors('category_id');

        // Switch both together.
        $this->actingAs($owner)
            ->put(route('transactions.update', $transaction), $this->payload($transaction, [
                'type' => 'expense',
                'category_id' => $expense->id,
            ]))
            ->assertRedirect(route('transactions.show', $transaction));

        $this->assertSame('expense', $transaction->refresh()->type);
    }

    public function test_updating_the_attachment_replaces_the_old_file(): void
    {
        Storage::fake('local');
        [$owner, $income] = $this->company();
        $transaction = $this->makeTransaction($owner, $income, ['attachment_path' => 'transaction-attachments/old.png']);
        Storage::disk('local')->put('transaction-attachments/old.png', 'x');

        $this->actingAs($owner)->put(route('transactions.update', $transaction), $this->payload($transaction, [
            'attachment' => UploadedFile::fake()->image('new.png'),
        ]));

        $transaction->refresh();
        $this->assertNotSame('transaction-attachments/old.png', $transaction->attachment_path);
        Storage::disk('local')->assertExists($transaction->attachment_path);
        Storage::disk('local')->assertMissing('transaction-attachments/old.png');
    }
}
