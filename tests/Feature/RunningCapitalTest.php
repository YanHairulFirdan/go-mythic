<?php

namespace Tests\Feature;

use App\Models\CapitalEntry;
use App\Models\CapitalTopup;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * US-MK-06: "Total Modal Saat Ini" (Periode Ini + income − expense in the
 * period) surfaced on the dashboard widget, Capital/Index and the top-up modal.
 * Plus US-MK-05: the transaction form receives the capital periods so it can
 * disable submit for an uncovered date.
 */
class RunningCapitalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-20 09:00:00');
    }

    private function entryFor(User $owner): CapitalEntry
    {
        return CapitalEntry::factory()->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'initial_amount' => 1_000_000,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);
    }

    private function txn(User $owner, string $type, int $amount, string $date): void
    {
        $category = TransactionCategory::factory()->for($owner->company)->create(['type' => $type]);
        Transaction::factory()->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'type' => $type,
            'category_id' => $category->id,
            'amount' => $amount,
            'transaction_date' => $date,
        ]);
    }

    public function test_current_total_equals_period_total_without_transactions(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $entry = $this->entryFor($owner);

        $this->assertSame(1_000_000.0, $entry->currentTotal());
    }

    public function test_current_total_adds_income_and_subtracts_expense_within_the_period(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $entry = $this->entryFor($owner);

        $this->txn($owner, 'income', 400_000, '2026-09-10');
        $this->txn($owner, 'expense', 150_000, '2026-09-12');

        // 1_000_000 + 400_000 − 150_000
        $this->assertSame(1_250_000.0, $entry->fresh()->currentTotal());
    }

    public function test_current_total_ignores_transactions_outside_the_period(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $entry = $this->entryFor($owner);

        $this->txn($owner, 'expense', 999_000, '2026-08-31'); // before start_date

        $this->assertSame(1_000_000.0, $entry->fresh()->currentTotal());
    }

    public function test_current_total_excludes_soft_deleted_transactions(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $entry = $this->entryFor($owner);

        $this->txn($owner, 'expense', 300_000, '2026-09-10');
        Transaction::query()->latest('id')->first()->delete();

        $this->assertSame(1_000_000.0, $entry->fresh()->currentTotal());
    }

    public function test_current_total_can_be_negative(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $entry = $this->entryFor($owner);

        $this->txn($owner, 'expense', 1_500_000, '2026-09-15');

        $this->assertSame(-500_000.0, $entry->fresh()->currentTotal());
    }

    public function test_current_total_includes_top_ups(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $entry = $this->entryFor($owner);
        CapitalTopup::factory()->for($entry)->create(['amount' => 500_000]);

        $this->txn($owner, 'expense', 200_000, '2026-09-10');

        // (1_000_000 + 500_000) − 200_000
        $this->assertSame(1_300_000.0, $entry->fresh()->currentTotal());
    }

    public function test_dashboard_widget_exposes_current_total(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->entryFor($owner);
        $this->txn($owner, 'expense', 250_000, '2026-09-11');

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('capitalWidget.period_total', 1000000)
                ->where('capitalWidget.current_total', 750000));
    }

    public function test_capital_index_exposes_current_total(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->entryFor($owner);
        $this->txn($owner, 'income', 300_000, '2026-09-05');

        $this->actingAs($owner)
            ->get(route('capital.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('activeEntry.current_total', 1300000));
    }

    public function test_transaction_create_page_receives_capital_periods(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->entryFor($owner);

        $this->actingAs($owner)
            ->get(route('transactions.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('capitalPeriods', 1)
                ->where('capitalPeriods.0.start_date', '2026-09-01')
                ->where('capitalPeriods.0.end_date', '2026-09-30'));
    }

    public function test_transaction_edit_page_receives_capital_periods(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $entry = $this->entryFor($owner);
        $category = TransactionCategory::factory()->for($owner->company)->income()->create();
        $transaction = Transaction::factory()->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'type' => 'income',
            'category_id' => $category->id,
            'transaction_date' => '2026-09-10',
        ]);

        $this->actingAs($owner)
            ->get(route('transactions.edit', $transaction))
            ->assertInertia(fn (Assert $page) => $page->has('capitalPeriods', 1));
    }
}
