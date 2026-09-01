<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * US-TR-05: Melihat detail transaksi.
 */
class TransactionDetailTest extends TestCase
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
        $owner = User::factory()->create(['role' => 'owner']);
        $transaction = $this->makeTransaction($owner);

        $this->get(route('transactions.show', $transaction))->assertRedirect(route('login'));
    }

    public function test_owner_sees_the_transaction_detail(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'name' => 'Bu Sari']);
        $transaction = $this->makeTransaction($owner, [
            'type' => 'expense',
            'amount' => 275_000,
            'transaction_date' => '2026-09-12',
            'payment_method' => 'transfer',
            'notes' => 'Beli kemasan',
        ]);

        $this->actingAs($owner)
            ->get(route('transactions.show', $transaction))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Show')
                ->where('transaction.id', $transaction->id)
                ->where('transaction.type', 'expense')
                ->where('transaction.amount', 275000)
                ->where('transaction.transaction_date', '2026-09-12')
                ->where('transaction.payment_method', 'transfer')
                ->where('transaction.notes', 'Beli kemasan')
                ->where('transaction.recorded_by', 'Bu Sari')
                ->where('transaction.last_updated_by', null)
                ->where('transaction.last_updated_at', null)
                ->where('transaction.attachment_url', null));
    }

    public function test_last_updated_by_is_shown_only_after_an_edit(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $editor = User::factory()->create(['role' => 'owner', 'name' => 'Pak Budi', 'company_id' => $owner->company_id]);
        $transaction = $this->makeTransaction($owner);

        $transaction->forceFill([
            'updated_by' => $editor->id,
            'updated_at' => Carbon::parse('2026-09-18 10:30:00'),
        ])->saveQuietly();

        $this->actingAs($owner)
            ->get(route('transactions.show', $transaction))
            ->assertInertia(fn (Assert $page) => $page
                ->where('transaction.last_updated_by', 'Pak Budi')
                ->whereNot('transaction.last_updated_at', null));
    }

    public function test_employee_can_view_their_own_transaction(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $employee = User::factory()->create(['role' => 'employee', 'company_id' => $owner->company_id]);
        $transaction = $this->makeTransaction($employee);

        $this->actingAs($employee)
            ->get(route('transactions.show', $transaction))
            ->assertOk();
    }

    public function test_employee_cannot_view_another_users_transaction(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $employee = User::factory()->create(['role' => 'employee', 'company_id' => $owner->company_id]);
        $othersTransaction = $this->makeTransaction($owner);

        $this->actingAs($employee)
            ->get(route('transactions.show', $othersTransaction))
            ->assertNotFound();
    }

    public function test_detail_is_tenant_scoped(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $foreign = $this->makeTransaction(User::factory()->create(['role' => 'owner']));

        $this->actingAs($owner)
            ->get(route('transactions.show', $foreign))
            ->assertNotFound();
    }

    public function test_attachment_url_is_present_only_when_a_file_exists(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $withFile = $this->makeTransaction($owner, ['attachment_path' => 'transaction-attachments/bukti.png']);

        $this->actingAs($owner)
            ->get(route('transactions.show', $withFile))
            ->assertInertia(fn (Assert $page) => $page
                ->where('transaction.attachment_url', route('transactions.attachment', $withFile)));
    }
}
