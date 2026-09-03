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

    private function transaction(int $companyId, string $type, float $amount, string $date, ?int $createdBy = null): Transaction
    {
        $category = TransactionCategory::factory()->for(Company::find($companyId))->create(['type' => $type]);

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
}
