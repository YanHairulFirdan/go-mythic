<?php

namespace Tests\Feature;

use App\Models\CapitalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * US-MK-05: global non-removable "belum ada modal aktif" banner. The banner is
 * driven by the shared `capitalActive` Inertia prop; these tests pin that prop.
 */
class CapitalAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-20 09:00:00');
    }

    private function activeEntryFor(User $user): void
    {
        CapitalEntry::factory()->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);
    }

    public function test_flag_is_false_for_owner_when_no_active_capital(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('capitalActive', false)
                ->where('auth.user.role', 'owner'));
    }

    public function test_flag_is_false_for_employee_when_no_active_capital(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('capitalActive', false)
                ->where('auth.user.role', 'employee'));
    }

    public function test_flag_is_true_when_active_capital_exists(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->activeEntryFor($owner);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('capitalActive', true));
    }

    public function test_flag_is_true_for_employee_when_active_capital_exists(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $this->activeEntryFor($employee);

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('capitalActive', true));
    }

    /** AC1: the flag is shared on every page, not only the dashboard. */
    public function test_flag_is_shared_on_non_dashboard_pages(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('capitalActive', false));

        $this->activeEntryFor($owner);

        $this->actingAs($owner)
            ->get(route('customers.index'))
            ->assertInertia(fn (Assert $page) => $page->where('capitalActive', true));
    }

    /** AC4: still flagged inactive when the only entry has expired. */
    public function test_flag_is_false_when_only_capital_has_expired(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        CapitalEntry::factory()->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('capitalActive', false));
    }

    public function test_flag_reflects_only_own_company_capital(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        CapitalEntry::factory()->create([
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('capitalActive', false));
    }

    public function test_guest_request_does_not_error(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('capitalActive', false));
    }
}
