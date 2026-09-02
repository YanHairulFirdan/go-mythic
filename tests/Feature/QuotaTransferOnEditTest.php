<?php

namespace Tests\Feature;

use App\Models\CapitalEntry;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Support\DailyTransactionQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * US-TR-02 AC4 — an income<->expense edit moves the day's quota between the two
 * types; the flip is rejected (row untouched) when the target type is already
 * full on a Free plan. A same-type edit is never quota-limited.
 */
class QuotaTransferOnEditTest extends TestCase
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
    private function company(bool $paid = false): array
    {
        $user = User::factory()->create(['role' => 'owner']);
        $user->company->update(['paid_until' => $paid ? now()->addDays(30) : null]);

        CapitalEntry::factory()->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);

        return [
            $user,
            TransactionCategory::factory()->for($user->company)->income()->create(),
            TransactionCategory::factory()->for($user->company)->expense()->create(),
        ];
    }

    private function seedUsage(User $owner, TransactionCategory $category, int $count, ?Carbon $createdAt = null): void
    {
        Transaction::factory()->count($count)->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'category_id' => $category->id,
            'type' => $category->type,
            'transaction_date' => '2026-09-15',
            'created_at' => $createdAt ?? now(),
        ]);
    }

    private function makeTransaction(User $owner, TransactionCategory $category, ?Carbon $createdAt = null): Transaction
    {
        return Transaction::factory()->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'category_id' => $category->id,
            'type' => $category->type,
            'amount' => 100_000,
            'transaction_date' => $createdAt ? '2026-09-17' : '2026-09-15',
            'payment_method' => 'cash',
            'notes' => 'Awal',
            'created_at' => $createdAt ?? now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function flipPayload(Transaction $transaction, TransactionCategory $targetCategory): array
    {
        return [
            'type' => $targetCategory->type,
            'amount' => (float) $transaction->amount,
            'category_id' => $targetCategory->id,
            'transaction_date' => $transaction->transaction_date,
            'payment_method' => $transaction->payment_method,
            'notes' => $transaction->notes,
        ];
    }

    public function test_flipping_type_moves_the_daily_quota_between_types(): void
    {
        [$owner, $income, $expense] = $this->company();
        $this->seedUsage($owner, $income, 4);
        $this->seedUsage($owner, $expense, 2);
        $transaction = $this->makeTransaction($owner, $income);

        $this->assertSame(5, DailyTransactionQuota::for($owner->company->fresh())->used('income'));

        $this->actingAs($owner)
            ->put(route('transactions.update', $transaction), $this->flipPayload($transaction, $expense))
            ->assertRedirect(route('transactions.show', $transaction));

        $quota = DailyTransactionQuota::for($owner->company->fresh());
        $this->assertSame(4, $quota->used('income'));
        $this->assertSame(3, $quota->used('expense'));
        $this->assertSame('expense', $transaction->refresh()->type);
    }

    public function test_flip_is_rejected_and_row_untouched_when_target_type_is_full(): void
    {
        [$owner, $income, $expense] = $this->company();
        $this->seedUsage($owner, $expense, DailyTransactionQuota::LIMIT);
        $transaction = $this->makeTransaction($owner, $income);

        $this->actingAs($owner)
            ->put(route('transactions.update', $transaction), $this->flipPayload($transaction, $expense))
            ->assertSessionHasErrors('quota');

        $transaction->refresh();
        $this->assertSame('income', $transaction->type);
        $this->assertSame('100000.00', $transaction->amount);
        $this->assertSame(DailyTransactionQuota::LIMIT, DailyTransactionQuota::for($owner->company->fresh())->used('expense'));
        $this->assertSame(1, DailyTransactionQuota::for($owner->company->fresh())->used('income'));
    }

    public function test_flip_is_allowed_into_the_last_free_slot(): void
    {
        [$owner, $income, $expense] = $this->company();
        $this->seedUsage($owner, $expense, DailyTransactionQuota::LIMIT - 1);
        $transaction = $this->makeTransaction($owner, $income);

        $this->actingAs($owner)
            ->put(route('transactions.update', $transaction), $this->flipPayload($transaction, $expense))
            ->assertSessionHasNoErrors();

        $this->assertSame('expense', $transaction->refresh()->type);
        $this->assertSame(DailyTransactionQuota::LIMIT, DailyTransactionQuota::for($owner->company->fresh())->used('expense'));
    }

    public function test_a_same_type_edit_is_never_blocked_by_a_full_quota(): void
    {
        [$owner, $income] = $this->company();
        $this->seedUsage($owner, $income, DailyTransactionQuota::LIMIT);
        $transaction = Transaction::where('company_id', $owner->company_id)->firstOrFail();

        $this->actingAs($owner)
            ->put(route('transactions.update', $transaction), [
                'type' => 'income',
                'amount' => 175_000,
                'category_id' => $transaction->category_id,
                'transaction_date' => $transaction->transaction_date,
                'payment_method' => 'cash',
                'notes' => 'Revisi',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('175000.00', $transaction->refresh()->amount);
    }

    public function test_paid_company_can_flip_type_regardless_of_counts(): void
    {
        [$owner, $income, $expense] = $this->company(paid: true);
        $this->seedUsage($owner, $expense, DailyTransactionQuota::LIMIT);
        $transaction = $this->makeTransaction($owner, $income);

        $this->actingAs($owner)
            ->put(route('transactions.update', $transaction), $this->flipPayload($transaction, $expense))
            ->assertSessionHasNoErrors();

        $this->assertSame('expense', $transaction->refresh()->type);
    }

    public function test_flip_on_a_row_created_before_today_is_not_quota_limited(): void
    {
        [$owner, $income, $expense] = $this->company();
        $this->seedUsage($owner, $expense, DailyTransactionQuota::LIMIT);
        $transaction = $this->makeTransaction($owner, $income, Carbon::parse('2026-09-17 09:00:00'));

        $this->actingAs($owner)
            ->put(route('transactions.update', $transaction), $this->flipPayload($transaction, $expense))
            ->assertSessionHasNoErrors();

        $this->assertSame('expense', $transaction->refresh()->type);
    }

    public function test_edit_page_exposes_the_quota_widget_for_free_and_hides_it_for_paid(): void
    {
        [$free, $freeIncome] = $this->company();
        $freeTransaction = $this->makeTransaction($free, $freeIncome);

        $this->actingAs($free)
            ->get(route('transactions.edit', $freeTransaction))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Edit')
                ->where('quota.limit', 150)
                ->where('quota.income.used', 1));

        [$paid, $paidIncome] = $this->company(paid: true);
        $paidTransaction = $this->makeTransaction($paid, $paidIncome);

        $this->actingAs($paid)
            ->get(route('transactions.edit', $paidTransaction))
            ->assertInertia(fn (Assert $page) => $page->where('quota', null));
    }
}
