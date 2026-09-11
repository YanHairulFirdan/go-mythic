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

class CapitalWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-20 09:00:00');
    }

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_dashboard_shows_capital_widget_when_an_entry_is_active(): void
    {
        $owner = User::factory()->create();
        CapitalEntry::factory()->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'initial_amount' => 3_000_000,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('capitalWidget.period_total', 3000000)
                ->where('capitalWidget.end_date', '2026-09-30'));
    }

    public function test_widget_handles_an_open_ended_entry(): void
    {
        $owner = User::factory()->create();
        CapitalEntry::factory()->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'initial_amount' => 2_000_000,
            'start_date' => '2026-09-01',
            'end_date' => null,
        ]);

        $category = TransactionCategory::factory()->for($owner->company)->create(['type' => 'income']);
        Transaction::factory()->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'type' => 'income',
            'category_id' => $category->id,
            'amount' => 400_000,
            'transaction_date' => '2026-09-15',
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('capitalWidget.period_total', 2000000)
                // current total counts income up to "today" (2026-09-20)
                ->where('capitalWidget.current_total', 2400000)
                ->where('capitalWidget.end_date', null));
    }

    public function test_widget_period_total_includes_top_ups(): void
    {
        $owner = User::factory()->create();
        $entry = CapitalEntry::factory()->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'initial_amount' => 1_000_000,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);
        CapitalTopup::factory()->for($entry)->create(['amount' => 500_000]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('capitalWidget.period_total', 1500000));
    }

    public function test_widget_is_null_when_no_active_entry(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('capitalWidget', null));
    }

    public function test_widget_is_null_when_entry_is_expired(): void
    {
        $owner = User::factory()->create();
        CapitalEntry::factory()->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('capitalWidget', null));
    }

    public function test_employee_dashboard_has_no_capital_widget(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        CapitalEntry::factory()->create([
            'company_id' => $employee->company_id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('capitalWidget', null));
    }

    public function test_widget_reflects_only_current_company_capital(): void
    {
        $owner = User::factory()->create();
        CapitalEntry::factory()->create([
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('capitalWidget', null));
    }
}
