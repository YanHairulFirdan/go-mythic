<?php

namespace Tests\Feature;

use App\Models\CapitalEntry;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Support\DailyTransactionQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * US-TR-01B — daily-quota radial indicator (`n/150` per type, colour bands,
 * hidden for Paid) backed by a cached per-type counter that falls back to a
 * database COUNT when the cache store is unreachable.
 */
class QuotaRadialTest extends TestCase
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

    private function seedUsage(User $owner, TransactionCategory $category, int $count): void
    {
        Transaction::factory()->count($count)->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'category_id' => $category->id,
            'type' => $category->type,
            'transaction_date' => '2026-09-15',
            'created_at' => now(),
        ]);
    }

    public function test_create_form_exposes_per_type_radial_quota_for_a_free_company(): void
    {
        [$owner, $income] = $this->company();
        $this->seedUsage($owner, $income, 3);

        $this->actingAs($owner)
            ->get(route('transactions.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Create')
                ->where('quota.limit', 150)
                ->where('quota.income.used', 3)
                ->where('quota.income.remaining', 147)
                ->where('quota.income.state', 'normal')
                ->where('quota.expense.used', 0)
                ->where('quota.expense.state', 'normal'));
    }

    public function test_create_form_hides_the_radial_for_a_paid_company(): void
    {
        [$owner, $income] = $this->company(paid: true);
        $this->seedUsage($owner, $income, 3);

        $this->actingAs($owner)
            ->get(route('transactions.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Create')
                ->where('quota', null));
    }

    public function test_state_bands_follow_the_design_tokens(): void
    {
        [$owner, $income] = $this->company();

        $stateAfter = function (int $used) use ($owner, $income): string {
            Transaction::where('company_id', $owner->company_id)->forceDelete();
            $this->seedUsage($owner, $income, $used);

            return DailyTransactionQuota::for($owner->company->fresh())->state('income');
        };

        $this->assertSame('normal', $stateAfter(119));  // < 80%  -> Indigo
        $this->assertSame('warning', $stateAfter(120)); // 80%    -> Amber
        $this->assertSame('warning', $stateAfter(149)); // 99%    -> Amber
        $this->assertSame('full', $stateAfter(150));    // 100%   -> Rose
    }

    public function test_count_is_cached_and_dropped_on_the_next_write(): void
    {
        [$owner, $income] = $this->company();
        $key = "company:{$owner->company_id}:txn_count:income:20260920";
        $this->seedUsage($owner, $income, 5);

        $this->assertSame(5, DailyTransactionQuota::for($owner->company->fresh())->used('income'));
        $this->assertTrue(Cache::has($key));

        // Transaction::booted drops the key on the next save -> recompute from DB.
        $this->seedUsage($owner, $income, 1);
        $this->assertFalse(Cache::has($key));
        $this->assertSame(6, DailyTransactionQuota::for($owner->company->fresh())->used('income'));
    }

    public function test_it_falls_back_to_a_database_count_when_the_cache_store_fails(): void
    {
        [$owner, $income, $expense] = $this->company();
        $this->seedUsage($owner, $income, 7);
        $this->seedUsage($owner, $expense, 2);

        Cache::shouldReceive('remember')->andThrow(new \RuntimeException('cache store down'));

        $quota = DailyTransactionQuota::for($owner->company->fresh());

        $this->assertSame(7, $quota->used('income'));
        $this->assertSame(2, $quota->used('expense'));
    }
}
