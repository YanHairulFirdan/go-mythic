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
 * Dashboard performance card. Income/expense/net always come from the company's
 * non-soft-deleted transactions. The badge is framed two ways: return-on-capital
 * (laba ÷ modal) for an Owner with an active capital entry, otherwise the
 * month-over-month change of net profit. The greeting name is the signed-in user.
 */
class DashboardSummaryWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-15 09:00:00');
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    private function record(User $user, string $type, int $amount, string $date): Transaction
    {
        return Transaction::factory()->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'type' => $type,
            'category_id' => TransactionCategory::factory()->for($user->company)->create(['type' => $type])->id,
            'amount' => $amount,
            'transaction_date' => $date,
        ]);
    }

    private function capital(User $owner, int $initial, string $start, string $end): CapitalEntry
    {
        return CapitalEntry::factory()->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'initial_amount' => $initial,
            'start_date' => $start,
            'end_date' => $end,
        ]);
    }

    /* -------------------------------------------------------------------------
     * AC1 — greeting
     * ---------------------------------------------------------------------- */

    public function test_dashboard_exposes_the_authenticated_user_name(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('auth.user.name', $owner->name));
    }

    /* -------------------------------------------------------------------------
     * Month basis — Employee, or Owner with no active capital
     * ---------------------------------------------------------------------- */

    /** AC2: income, expense and net come from the running calendar month. */
    public function test_month_basis_totals_come_from_current_month_transactions(): void
    {
        $owner = $this->owner();
        $this->record($owner, 'income', 7_100_000, '2026-09-03');
        $this->record($owner, 'expense', 2_800_000, '2026-09-10');

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.basis', 'month')
                ->where('summary.income', 7_100_000)
                ->where('summary.expense', 2_800_000)
                ->where('summary.net_profit', 4_300_000));
    }

    /** AC2 boundary: transactions outside the running month are excluded. */
    public function test_month_basis_excludes_transactions_outside_the_current_month(): void
    {
        $owner = $this->owner();
        $this->record($owner, 'income', 500_000, '2026-08-31');
        $this->record($owner, 'income', 900_000, '2026-10-01');
        $this->record($owner, 'income', 1_000_000, '2026-09-15');

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.income', 1_000_000)
                ->where('summary.net_profit', 1_000_000));
    }

    /** AC3: the badge is the net-profit change vs the previous full month. */
    public function test_month_basis_change_percent_compares_against_the_previous_full_month(): void
    {
        $owner = $this->owner();
        $this->record($owner, 'income', 1_000_000, '2026-08-12'); // Aug net 1,000,000
        $this->record($owner, 'income', 1_128_000, '2026-09-05'); // Sep net 1,128,000 -> +12.8%

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.change_percent', 12.8)
                ->where('summary.baseline_amount', 1_000_000));
    }

    /** AC3: no prior-month baseline -> null (badge and caption hidden). */
    public function test_month_basis_change_percent_is_null_without_a_prior_month_baseline(): void
    {
        $owner = $this->owner();
        $this->record($owner, 'income', 2_000_000, '2026-09-05');

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.change_percent', null)
                ->where('summary.baseline_amount', null));
    }

    /** AC3: a negative prior-month net still yields a signed, finite percentage. */
    public function test_month_basis_change_percent_handles_a_negative_prior_month_net(): void
    {
        $owner = $this->owner();
        $this->record($owner, 'expense', 1_000_000, '2026-08-12'); // Aug net -1,000,000
        $this->record($owner, 'income', 500_000, '2026-09-05');     // Sep net +500,000 -> 150

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.change_percent', 150));
    }

    /** AC4: the bar width is income over (income + expense), rounded. */
    public function test_month_basis_income_ratio_percent_is_income_over_gross(): void
    {
        $owner = $this->owner();
        $this->record($owner, 'income', 7_200_000, '2026-09-03');
        $this->record($owner, 'expense', 2_800_000, '2026-09-10');

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.income_ratio_percent', 72));
    }

    /** AC6: a company with no transactions gets a zeroed, error-free card. */
    public function test_month_basis_is_zeroed_when_the_company_has_no_transactions(): void
    {
        $this->actingAs($this->owner())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.basis', 'month')
                ->where('summary.income', 0)
                ->where('summary.expense', 0)
                ->where('summary.net_profit', 0)
                ->where('summary.income_ratio_percent', 0)
                ->where('summary.change_percent', null));
    }

    /** AC5: soft-deleted transactions are not counted. */
    public function test_month_basis_ignores_soft_deleted_transactions(): void
    {
        $owner = $this->owner();
        $this->record($owner, 'income', 1_000_000, '2026-09-05');
        $this->record($owner, 'income', 500_000, '2026-09-06')->delete();

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.income', 1_000_000));
    }

    /** AC5: only the signed-in company's transactions are summed. */
    public function test_month_basis_only_counts_the_current_company(): void
    {
        $owner = $this->owner();
        $this->record($owner, 'income', 1_000_000, '2026-09-05');

        $other = $this->owner();
        $this->record($other, 'income', 9_000_000, '2026-09-05');

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.income', 1_000_000));
    }

    /* -------------------------------------------------------------------------
     * Capital basis — Owner with an active capital entry
     * ---------------------------------------------------------------------- */

    /** Owner + active modal: card is framed around the capital period. */
    public function test_owner_with_active_capital_uses_the_capital_basis(): void
    {
        $owner = $this->owner();
        $this->capital($owner, 1_000_000, '2026-09-01', '2026-09-30');
        $this->record($owner, 'income', 1_600_000, '2026-09-05');
        $this->record($owner, 'expense', 150_000, '2026-09-10');

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.basis', 'capital')
                ->where('summary.income', 1_600_000)
                ->where('summary.expense', 150_000)
                ->where('summary.net_profit', 1_450_000)
                // laba 1,450,000 ÷ modal 1,000,000 = 145%
                ->where('summary.change_percent', 145)
                ->where('summary.baseline_amount', 1_000_000)
                ->where('summary.period_start', '2026-09-01')
                ->where('summary.period_end', '2026-09-30'));
    }

    /** The window is the entry's date range, not the calendar month. */
    public function test_capital_basis_window_matches_the_entry_dates(): void
    {
        $owner = $this->owner();
        $this->capital($owner, 1_000_000, '2026-09-10', '2026-09-20');
        $this->record($owner, 'income', 400_000, '2026-09-05');  // before entry -> excluded
        $this->record($owner, 'income', 700_000, '2026-09-15');  // inside -> counted
        $this->record($owner, 'income', 900_000, '2026-09-25');  // after entry -> excluded

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.basis', 'capital')
                ->where('summary.income', 700_000)
                ->where('summary.net_profit', 700_000));
    }

    /** AC: top-ups raise the modal baseline the badge is measured against. */
    public function test_capital_basis_includes_topups_in_the_modal_baseline(): void
    {
        $owner = $this->owner();
        $entry = $this->capital($owner, 1_000_000, '2026-09-01', '2026-09-30');
        CapitalTopup::factory()->create([
            'capital_entry_id' => $entry->id,
            'amount' => 250_000,
            'changed_by' => $owner->id,
        ]);
        $this->record($owner, 'income', 1_250_000, '2026-09-05');

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.baseline_amount', 1_250_000)
                // laba 1,250,000 ÷ modal 1,250,000 = 100%
                ->where('summary.change_percent', 100));
    }

    /** Soft-deleted transactions do not count towards the capital-period net. */
    public function test_capital_basis_ignores_soft_deleted_transactions(): void
    {
        $owner = $this->owner();
        $this->capital($owner, 1_000_000, '2026-09-01', '2026-09-30');
        $this->record($owner, 'income', 800_000, '2026-09-05');
        $this->record($owner, 'income', 500_000, '2026-09-06')->delete();

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.income', 800_000));
    }

    /** No active entry -> the card falls back to the month basis. */
    public function test_owner_without_active_capital_falls_back_to_month_basis(): void
    {
        $owner = $this->owner();
        $this->record($owner, 'income', 1_000_000, '2026-09-05');

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('summary.basis', 'month'));
    }

    /** An expired entry is not "active" -> month basis. */
    public function test_expired_capital_falls_back_to_month_basis(): void
    {
        $owner = $this->owner();
        $this->capital($owner, 1_000_000, '2026-08-01', '2026-08-31');
        $this->record($owner, 'income', 1_000_000, '2026-09-05');

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('summary.basis', 'month'));
    }

    /** Employees never see the capital framing, even when the company has one. */
    public function test_employee_always_uses_month_basis_even_with_company_capital(): void
    {
        $owner = $this->owner();
        $this->capital($owner, 1_000_000, '2026-09-01', '2026-09-30');
        $employee = User::factory()->create([
            'role' => 'employee',
            'company_id' => $owner->company_id,
        ]);
        $this->record($owner, 'income', 1_000_000, '2026-09-05');

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('summary.basis', 'month'));
    }
}
