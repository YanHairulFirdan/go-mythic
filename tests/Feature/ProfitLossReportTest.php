<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfitLossReportTest extends TestCase
{
    use RefreshDatabase;

    private function transaction(int $companyId, string $type, float $amount, string $date, ?int $createdBy = null, ?TransactionCategory $category = null): Transaction
    {
        $category ??= TransactionCategory::factory()->for(Company::find($companyId))->create(['type' => $type]);

        return Transaction::factory()->create([
            'company_id' => $companyId,
            'created_by' => $createdBy ?? User::factory()->create(['company_id' => $companyId])->id,
            'category_id' => $category->id,
            'type' => $type,
            'amount' => $amount,
            'transaction_date' => $date,
        ]);
    }

    public function test_guest_is_redirected_from_report(): void
    {
        $this->get(route('reports.profit-loss'))->assertRedirect(route('login'));
    }

    public function test_employee_is_forbidden(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAs($employee)->get(route('reports.profit-loss'))->assertForbidden();
    }

    public function test_owner_month_totals_and_net(): void
    {
        Carbon::setTestNow('2026-09-15');
        $owner = User::factory()->create(['role' => 'owner']);
        $this->transaction($owner->company_id, 'income', 1_000_000, '2026-09-01');
        $this->transaction($owner->company_id, 'income', 500_000, '2026-09-15');
        $this->transaction($owner->company_id, 'expense', 300_000, '2026-09-10');

        $this->actingAs($owner)
            ->get(route('reports.profit-loss', ['period' => 'month']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/ProfitLoss')
                ->where('report.income', 1_500_000)
                ->where('report.expense', 300_000)
                ->where('report.net', 1_200_000));

        Carbon::setTestNow();
    }

    public function test_default_period_is_current_month(): void
    {
        Carbon::setTestNow('2026-09-15');
        $owner = User::factory()->create(['role' => 'owner']);
        $this->transaction($owner->company_id, 'income', 100_000, '2026-09-01');
        $this->transaction($owner->company_id, 'income', 999_000, '2026-08-31');

        $this->actingAs($owner)
            ->get(route('reports.profit-loss'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.period', 'month')
                ->where('report.period_label', 'September 2026')
                ->where('report.income', 100_000));

        Carbon::setTestNow();
    }

    public function test_summary_updates_immediately_after_new_transaction(): void
    {
        Carbon::setTestNow('2026-09-15');
        $owner = User::factory()->create(['role' => 'owner']);
        $this->transaction($owner->company_id, 'income', 100_000, '2026-09-15');

        $this->actingAs($owner)
            ->get(route('reports.profit-loss'))
            ->assertInertia(fn (Assert $page) => $page->where('report.income', 100_000));

        $this->transaction($owner->company_id, 'income', 200_000, '2026-09-15');

        $this->actingAs($owner)
            ->get(route('reports.profit-loss'))
            ->assertInertia(fn (Assert $page) => $page->where('report.income', 300_000));

        Carbon::setTestNow();
    }

    public function test_summary_is_text_only_without_chart_data(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('reports.profit-loss'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/ProfitLoss')
                ->has('report.income')
                ->has('report.expense')
                ->has('report.net')
                ->missing('report.chart'));
    }

    public function test_today_period_only_counts_today(): void
    {
        Carbon::setTestNow('2026-09-15');
        $owner = User::factory()->create(['role' => 'owner']);
        $this->transaction($owner->company_id, 'income', 100_000, '2026-09-15');
        $this->transaction($owner->company_id, 'income', 999_000, '2026-09-14');

        $this->actingAs($owner)
            ->get(route('reports.profit-loss', ['period' => 'today']))
            ->assertInertia(fn (Assert $page) => $page->where('report.income', 100_000));

        Carbon::setTestNow();
    }

    public function test_week_period_boundaries_inclusive(): void
    {
        // Tuesday, week start Monday 2026-09-14
        Carbon::setTestNow('2026-09-15');
        $owner = User::factory()->create(['role' => 'owner']);
        $this->transaction($owner->company_id, 'income', 10_000, '2026-09-14'); // Monday, inside
        $this->transaction($owner->company_id, 'income', 20_000, '2026-09-13'); // Sunday, outside

        $this->actingAs($owner)
            ->get(route('reports.profit-loss', ['period' => 'week']))
            ->assertInertia(fn (Assert $page) => $page->where('report.income', 10_000));

        Carbon::setTestNow();
    }

    public function test_custom_range_is_inclusive_of_both_boundaries(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->transaction($owner->company_id, 'income', 10_000, '2026-08-01');
        $this->transaction($owner->company_id, 'income', 20_000, '2026-08-31');
        $this->transaction($owner->company_id, 'income', 99_000, '2026-07-31');

        $this->actingAs($owner)
            ->get(route('reports.profit-loss', [
                'period' => 'custom', 'date_from' => '2026-08-01', 'date_to' => '2026-08-31',
            ]))
            ->assertInertia(fn (Assert $page) => $page->where('report.income', 30_000));
    }

    public function test_custom_range_requires_dates_and_valid_order(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('reports.profit-loss', ['period' => 'custom']))
            ->assertSessionHasErrors(['date_from', 'date_to']);

        $this->actingAs($owner)
            ->get(route('reports.profit-loss', [
                'period' => 'custom', 'date_from' => '2026-08-31', 'date_to' => '2026-08-01',
            ]))
            ->assertSessionHasErrors('date_to');
    }

    public function test_invalid_period_is_rejected(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('reports.profit-loss', ['period' => 'year']))
            ->assertSessionHasErrors('period');
    }

    public function test_report_excludes_other_companys_transactions(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $other = User::factory()->create(['role' => 'owner']);
        $this->transaction($other->company_id, 'income', 500_000, now()->toDateString());

        $this->actingAs($owner)
            ->get(route('reports.profit-loss'))
            ->assertInertia(fn (Assert $page) => $page->where('report.income', 0));
    }

    public function test_soft_deleted_transaction_is_excluded_and_summary_is_real_time(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $tx = $this->transaction($owner->company_id, 'income', 200_000, now()->toDateString());

        $this->actingAs($owner)
            ->get(route('reports.profit-loss'))
            ->assertInertia(fn (Assert $page) => $page->where('report.income', 200_000));

        $tx->delete();

        $this->actingAs($owner)
            ->get(route('reports.profit-loss'))
            ->assertInertia(fn (Assert $page) => $page->where('report.income', 0));
    }

    private function transactionInCategory(int $companyId, string $type, float $amount, string $date, string $categoryName): Transaction
    {
        $category = TransactionCategory::factory()
            ->for(Company::find($companyId))
            ->create(['type' => $type, 'name' => $categoryName]);

        return Transaction::factory()->create([
            'company_id' => $companyId,
            'created_by' => User::factory()->create(['company_id' => $companyId])->id,
            'category_id' => $category->id,
            'type' => $type,
            'amount' => $amount,
            'transaction_date' => $date,
        ]);
    }

    public function test_breakdown_lists_categories_sorted_descending_by_total(): void
    {
        Carbon::setTestNow('2026-09-15');
        $owner = User::factory()->create(['role' => 'owner']);
        $kecil = TransactionCategory::factory()->for($owner->company)->create(['type' => 'income', 'name' => 'Kecil']);
        $this->transaction($owner->company_id, 'income', 100_000, '2026-09-01', category: $kecil);
        $this->transaction($owner->company_id, 'income', 100_000, '2026-09-05', category: $kecil);
        $this->transactionInCategory($owner->company_id, 'income', 700_000, '2026-09-10', 'Besar');
        $this->transactionInCategory($owner->company_id, 'income', 100_000, '2026-09-12', 'Sedang');

        $this->actingAs($owner)
            ->get(route('reports.profit-loss'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('report.incomeBreakdown', 3)
                ->where('report.incomeBreakdown.0.label', 'Besar')
                ->where('report.incomeBreakdown.0.amount', 700_000)
                ->where('report.incomeBreakdown.0.percent', 70)
                ->where('report.incomeBreakdown.1.label', 'Kecil')
                ->where('report.incomeBreakdown.1.amount', 200_000)
                ->where('report.incomeBreakdown.1.percent', 20)
                ->where('report.incomeBreakdown.2.label', 'Sedang')
                ->where('report.incomeBreakdown.2.percent', 10));

        Carbon::setTestNow();
    }

    public function test_expense_breakdown_lists_categories_sorted_descending_by_total(): void
    {
        Carbon::setTestNow('2026-09-15');
        $owner = User::factory()->create(['role' => 'owner']);
        $this->transactionInCategory($owner->company_id, 'expense', 400_000, '2026-09-05', 'Bahan Baku');
        $this->transactionInCategory($owner->company_id, 'expense', 110_000, '2026-09-03', 'Sewa');
        $this->transactionInCategory($owner->company_id, 'expense', 100_000, '2026-09-06', 'Transport');

        $this->actingAs($owner)
            ->get(route('reports.profit-loss'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('report.expenseBreakdown', 3)
                ->where('report.expenseBreakdown.0.label', 'Bahan Baku')
                ->where('report.expenseBreakdown.0.amount', 400_000)
                ->where('report.expenseBreakdown.0.percent', 65.6)
                ->where('report.expenseBreakdown.1.label', 'Sewa')
                ->where('report.expenseBreakdown.1.amount', 110_000)
                ->where('report.expenseBreakdown.1.percent', 18)
                ->where('report.expenseBreakdown.2.label', 'Transport')
                ->where('report.expenseBreakdown.2.amount', 100_000)
                ->where('report.expenseBreakdown.2.percent', 16.4));

        Carbon::setTestNow();
    }

    public function test_income_and_expense_breakdowns_are_separate_lists(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->transactionInCategory($owner->company_id, 'income', 300_000, now()->toDateString(), 'Jasa');
        $this->transactionInCategory($owner->company_id, 'expense', 100_000, now()->toDateString(), 'Upah');

        $this->actingAs($owner)
            ->get(route('reports.profit-loss'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('report.incomeBreakdown', 1)
                ->where('report.incomeBreakdown.0.label', 'Jasa')
                ->has('report.expenseBreakdown', 1)
                ->where('report.expenseBreakdown.0.label', 'Upah'));
    }

    public function test_category_with_no_transactions_in_period_is_absent_from_breakdown(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->transactionInCategory($owner->company_id, 'income', 100_000, '2026-09-15', 'Ada');
        TransactionCategory::factory()
            ->for($owner->company)
            ->create(['type' => 'income', 'name' => 'Kosong']);

        Carbon::setTestNow('2026-09-15');

        $this->actingAs($owner)
            ->get(route('reports.profit-loss'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('report.incomeBreakdown', 1)
                ->where('report.incomeBreakdown.0.label', 'Ada'));

        Carbon::setTestNow();
    }

    public function test_breakdown_respects_same_period_filter_as_summary(): void
    {
        // Tuesday; week = Mon 2026-09-14 .. Sun 2026-09-20
        Carbon::setTestNow('2026-09-15');
        $owner = User::factory()->create(['role' => 'owner']);
        $this->transactionInCategory($owner->company_id, 'income', 50_000, '2026-09-14', 'Dalam Minggu');
        $this->transactionInCategory($owner->company_id, 'income', 500_000, '2026-09-13', 'Luar Minggu');

        $this->actingAs($owner)
            ->get(route('reports.profit-loss', ['period' => 'week']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('report.incomeBreakdown', 1)
                ->where('report.incomeBreakdown.0.label', 'Dalam Minggu')
                ->where('report.incomeBreakdown.0.amount', 50_000)
                ->where('report.incomeBreakdown.0.percent', 100));

        Carbon::setTestNow();
    }

    public function test_breakdown_excludes_soft_deleted_transactions(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $tx = $this->transactionInCategory($owner->company_id, 'income', 200_000, now()->toDateString(), 'Jasa');

        $this->actingAs($owner)
            ->get(route('reports.profit-loss'))
            ->assertInertia(fn (Assert $page) => $page->has('report.incomeBreakdown', 1));

        $tx->delete();

        $this->actingAs($owner)
            ->get(route('reports.profit-loss'))
            ->assertInertia(fn (Assert $page) => $page->has('report.incomeBreakdown', 0));
    }
}
