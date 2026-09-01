<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * US-TR-03: Menghapus transaksi.
 */
class TransactionDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function makeTransaction(User $creator, array $attributes = []): Transaction
    {
        return Transaction::factory()->create(array_merge([
            'company_id' => $creator->company_id,
            'created_by' => $creator->id,
        ], $attributes));
    }

    public function test_guest_cannot_delete(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $transaction = $this->makeTransaction($owner);

        $this->delete(route('transactions.destroy', $transaction))->assertRedirect(route('login'));
        $this->assertNotSoftDeleted($transaction);
    }

    public function test_owner_soft_deletes_a_transaction(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $transaction = $this->makeTransaction($owner);

        $this->actingAs($owner)
            ->delete(route('transactions.destroy', $transaction))
            ->assertRedirect(route('transactions.index'));

        $this->assertSoftDeleted($transaction);
    }

    public function test_delete_writes_a_deleted_activity_log_with_a_snapshot(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $transaction = $this->makeTransaction($owner, ['amount' => 340_000]);

        $this->actingAs($owner)->delete(route('transactions.destroy', $transaction));

        $activity = Activity::query()
            ->where('log_name', 'transaction')
            ->where('event', 'deleted')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($owner->id, $activity->causer_id);
        $this->assertSame($transaction->id, $activity->subject_id);
        // spatie stores the pre-delete snapshot under `old` for the deleted event.
        $this->assertSame('340000.00', (string) $activity->properties['old']['amount']);
    }

    public function test_employee_can_delete_their_own_transaction(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $employee = User::factory()->create(['role' => 'employee', 'company_id' => $owner->company_id]);
        $transaction = $this->makeTransaction($employee);

        $this->actingAs($employee)
            ->delete(route('transactions.destroy', $transaction))
            ->assertRedirect(route('transactions.index'));

        $this->assertSoftDeleted($transaction);
    }

    public function test_employee_cannot_delete_another_users_transaction(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $employee = User::factory()->create(['role' => 'employee', 'company_id' => $owner->company_id]);
        $transaction = $this->makeTransaction($owner);

        $this->actingAs($employee)
            ->delete(route('transactions.destroy', $transaction))
            ->assertNotFound();

        $this->assertNotSoftDeleted($transaction);
    }

    public function test_delete_is_tenant_scoped(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $foreign = $this->makeTransaction(User::factory()->create(['role' => 'owner']));

        $this->actingAs($owner)
            ->delete(route('transactions.destroy', $foreign))
            ->assertNotFound();

        $this->assertNotSoftDeleted($foreign);
    }

    public function test_deleted_transaction_disappears_from_the_list(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $kept = $this->makeTransaction($owner);
        $removed = $this->makeTransaction($owner);

        $this->actingAs($owner)->delete(route('transactions.destroy', $removed));

        $this->actingAs($owner)
            ->get(route('transactions.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('transactions.data', 1)
                ->where('transactions.data.0.id', $kept->id));
    }
}
