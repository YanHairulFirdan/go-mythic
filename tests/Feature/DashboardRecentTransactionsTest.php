<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Dashboard "Transaksi terbaru" list: the newest transactions the viewer may
 * see, scoped to the company, with Employees limited to their own rows
 * (US-TR-04 AC1).
 */
class DashboardRecentTransactionsTest extends TestCase
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

    private function record(User $user, string $type, int $amount, string $date, ?string $category = null): Transaction
    {
        $categoryAttributes = ['type' => $type];
        if ($category !== null) {
            $categoryAttributes['name'] = $category;
        }

        return Transaction::factory()->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'type' => $type,
            'category_id' => TransactionCategory::factory()->for($user->company)->create($categoryAttributes)->id,
            'amount' => $amount,
            'transaction_date' => $date,
        ]);
    }

    public function test_list_is_empty_when_the_company_has_no_transactions(): void
    {
        $this->actingAs($this->owner())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('recentTransactions', []));
    }

    public function test_list_is_newest_first_and_capped_at_five(): void
    {
        $owner = $this->owner();
        foreach (['2026-09-01', '2026-09-02', '2026-09-03', '2026-09-04', '2026-09-05', '2026-09-06'] as $i => $date) {
            $this->record($owner, 'income', ($i + 1) * 100_000, $date);
        }

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('recentTransactions', 5)
                ->where('recentTransactions.0.transaction_date', '2026-09-06')
                ->where('recentTransactions.4.transaction_date', '2026-09-02'));
    }

    public function test_same_day_rows_break_the_tie_by_newest_id(): void
    {
        $owner = $this->owner();
        $first = $this->record($owner, 'income', 100_000, '2026-09-10');
        $second = $this->record($owner, 'expense', 200_000, '2026-09-10');

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('recentTransactions.0.id', $second->id)
                ->where('recentTransactions.1.id', $first->id));
    }

    public function test_each_row_carries_the_fields_the_card_renders(): void
    {
        $owner = $this->owner();
        $this->record($owner, 'income', 850_000, '2026-09-12', 'Jasa Cleaning');

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('recentTransactions.0.type', 'income')
                ->where('recentTransactions.0.amount', 850_000)
                ->where('recentTransactions.0.transaction_date', '2026-09-12')
                ->where('recentTransactions.0.category', 'Jasa Cleaning'));
    }

    public function test_soft_deleted_transactions_are_excluded(): void
    {
        $owner = $this->owner();
        $this->record($owner, 'income', 100_000, '2026-09-05');
        $this->record($owner, 'income', 500_000, '2026-09-06')->delete();

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('recentTransactions', 1)
                ->where('recentTransactions.0.amount', 100_000));
    }

    public function test_only_the_current_company_rows_are_listed(): void
    {
        $owner = $this->owner();
        $this->record($owner, 'income', 100_000, '2026-09-05');

        $other = $this->owner();
        $this->record($other, 'income', 999_000, '2026-09-06');

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('recentTransactions', 1)
                ->where('recentTransactions.0.amount', 100_000));
    }

    public function test_employee_only_sees_their_own_rows(): void
    {
        $owner = $this->owner();
        $employee = User::factory()->create(['role' => 'employee', 'company_id' => $owner->company_id]);

        $this->record($owner, 'income', 700_000, '2026-09-05');
        $this->record($employee, 'expense', 120_000, '2026-09-06');

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('recentTransactions', 1)
                ->where('recentTransactions.0.amount', 120_000));
    }

    public function test_owner_sees_rows_recorded_by_employees_too(): void
    {
        $owner = $this->owner();
        $employee = User::factory()->create(['role' => 'employee', 'company_id' => $owner->company_id]);

        $this->record($employee, 'expense', 120_000, '2026-09-06');

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('recentTransactions', 1)
                ->where('recentTransactions.0.amount', 120_000));
    }
}
