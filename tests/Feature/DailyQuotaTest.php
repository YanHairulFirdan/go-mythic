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
 * US-SUB-01 — daily transaction quota for Free companies (150 per type per UTC
 * day): dashboard usage indicator (AC1), soft warning at 80% (AC2), hard block
 * at 100% with an upgrade CTA and a 00:00 UTC reset (AC3).
 */
class DailyQuotaTest extends TestCase
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
            TransactionCategory::factory()->for($user->company)->income()->create(['name' => 'Penjualan']),
            TransactionCategory::factory()->for($user->company)->expense()->create(['name' => 'Belanja']),
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

    /**
     * @return array<string, mixed>
     */
    private function payload(TransactionCategory $category, array $overrides = []): array
    {
        return array_merge([
            'type' => $category->type,
            'amount' => 25_000,
            'category_id' => $category->id,
            'transaction_date' => '2026-09-15',
            'payment_method' => 'cash',
        ], $overrides);
    }

    public function test_dashboard_shows_per_type_quota_usage_for_a_free_company(): void
    {
        [$owner, $income, $expense] = $this->company();
        $this->seedUsage($owner, $income, 3);
        $this->seedUsage($owner, $expense, 1);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('quotaWidget.limit', 150)
                ->where('quotaWidget.income.used', 3)
                ->where('quotaWidget.income.remaining', 147)
                ->where('quotaWidget.expense.used', 1)
                ->where('quotaWidget.income.reached', false)
                ->where('quotaWidget.income.near_limit', false));
    }

    public function test_quota_usage_counts_only_todays_utc_transactions(): void
    {
        [$owner, $income] = $this->company();
        $this->seedUsage($owner, $income, 2, now());
        $this->seedUsage($owner, $income, 5, now()->subDay());
        $this->seedUsage($owner, $income, 4, now()->addDay());

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('quotaWidget.income.used', 2));
    }

    public function test_quota_usage_excludes_soft_deleted_transactions(): void
    {
        [$owner, $income] = $this->company();
        $this->seedUsage($owner, $income, 3);
        Transaction::query()->latest('id')->first()->delete();

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('quotaWidget.income.used', 2));
    }

    public function test_quota_indicator_is_hidden_for_a_paid_company(): void
    {
        [$owner, $income] = $this->company(paid: true);
        $this->seedUsage($owner, $income, 3);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('quotaWidget', null));
    }

    public function test_soft_warning_flags_the_type_at_eighty_percent_without_blocking(): void
    {
        [$owner, $income] = $this->company();
        $this->seedUsage($owner, $income, 120);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('quotaWidget.income.near_limit', true)
                ->where('quotaWidget.income.reached', false));

        // AC2: non-blocking — a transaction still records at 80%.
        $this->actingAs($owner)
            ->post(route('transactions.store'), $this->payload($income))
            ->assertSessionHasNoErrors();
    }

    public function test_income_transaction_is_blocked_once_the_daily_quota_is_reached(): void
    {
        [$owner, $income] = $this->company();
        $this->seedUsage($owner, $income, DailyTransactionQuota::LIMIT);

        $this->actingAs($owner)
            ->post(route('transactions.store'), $this->payload($income))
            ->assertSessionHasErrors('quota');

        $this->assertDatabaseCount('transactions', DailyTransactionQuota::LIMIT);
    }

    public function test_the_block_message_points_to_the_upgrade_page(): void
    {
        [$owner, $income] = $this->company();
        $this->seedUsage($owner, $income, DailyTransactionQuota::LIMIT);

        $this->actingAs($owner)
            ->post(route('transactions.store'), $this->payload($income))
            ->assertSessionHasErrors('quota');

        $this->assertStringContainsString('Upgrade ke Paid', session('errors')->get('quota')[0]);
    }

    public function test_reaching_the_income_quota_does_not_block_expense(): void
    {
        [$owner, $income, $expense] = $this->company();
        $this->seedUsage($owner, $income, DailyTransactionQuota::LIMIT);

        $this->actingAs($owner)
            ->post(route('transactions.store'), $this->payload($expense))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('transactions', DailyTransactionQuota::LIMIT + 1);
    }

    public function test_the_quota_block_does_not_apply_to_a_paid_company(): void
    {
        [$owner, $income] = $this->company(paid: true);
        $this->seedUsage($owner, $income, DailyTransactionQuota::LIMIT);

        $this->actingAs($owner)
            ->post(route('transactions.store'), $this->payload($income))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('transactions', DailyTransactionQuota::LIMIT + 1);
    }

    /**
     * US-SUB-05 AC2: a company that was Paid then expired follows Free rules
     * live (150/day quota applies), same as a never-paid Free company.
     */
    public function test_daily_quota_applies_to_a_company_degraded_from_paid(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $owner->company->update(['paid_until' => now()->subSecond()]);
        CapitalEntry::factory()->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);
        $income = TransactionCategory::factory()->for($owner->company)->income()->create(['name' => 'Penjualan']);
        $this->seedUsage($owner, $income, 10);

        $quota = DailyTransactionQuota::for($owner->company->fresh());

        $this->assertTrue($quota->applies);
        $this->assertSame(10, $quota->used('income'));
        $this->assertSame(DailyTransactionQuota::LIMIT - 10, $quota->remaining('income'));

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertRedirect(route('subscription.index'));
    }

    public function test_the_quota_resets_on_the_next_utc_day(): void
    {
        [$owner, $income] = $this->company();
        $this->seedUsage($owner, $income, DailyTransactionQuota::LIMIT, now()->subDay());

        $this->actingAs($owner)
            ->post(route('transactions.store'), $this->payload($income))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('transactions', DailyTransactionQuota::LIMIT + 1);
    }
}
