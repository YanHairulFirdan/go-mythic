<?php

namespace Tests\Feature;

use App\Models\CapitalEntry;
use App\Models\CapitalTopup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CapitalHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-15 09:00:00');
    }

    public function test_guest_is_redirected_from_capital_history(): void
    {
        $this->get(route('capital.history'))->assertRedirect(route('login'));
    }

    public function test_employee_cannot_access_capital_history(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAs($employee)->get(route('capital.history'))->assertForbidden();
    }

    public function test_history_is_empty_when_no_entries(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->get(route('capital.history'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Capital/History')
                ->has('entries', 0));
    }

    public function test_history_lists_entries_with_final_amount_dates_and_status(): void
    {
        $owner = User::factory()->create();
        $active = CapitalEntry::factory()->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'initial_amount' => 1_000_000,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);
        CapitalTopup::factory()->for($active)->create(['amount' => 500_000]);

        $this->actingAs($owner)
            ->get(route('capital.history'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Capital/History')
                ->has('entries', 1)
                ->where('entries.0.id', $active->id)
                ->where('entries.0.final_amount', 1500000)
                ->where('entries.0.start_date', '2026-09-01')
                ->where('entries.0.end_date', '2026-09-30')
                ->where('entries.0.status', 'Aktif'));
    }

    public function test_entry_status_reflects_active_or_expired(): void
    {
        $owner = User::factory()->create();
        CapitalEntry::factory()->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'created_at' => '2026-07-01 08:00:00',
        ]);
        CapitalEntry::factory()->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'start_date' => '2026-09-10',
            'end_date' => '2026-10-10',
            'created_at' => '2026-09-10 08:00:00',
        ]);

        $this->actingAs($owner)
            ->get(route('capital.history'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('entries', 2)
                ->where('entries.0.status', 'Aktif')
                ->where('entries.1.status', 'Kadaluarsa'));
    }

    public function test_each_entry_includes_its_top_up_history(): void
    {
        $owner = User::factory()->create();
        $entry = CapitalEntry::factory()->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);
        CapitalTopup::factory()->for($entry)->create([
            'amount' => 250_000,
            'changed_at' => '2026-09-05 10:00:00',
            'extended_end_date' => null,
        ]);
        CapitalTopup::factory()->for($entry)->create([
            'amount' => 750_000,
            'changed_at' => '2026-09-12 14:00:00',
            'extended_end_date' => '2026-10-15',
        ]);

        $this->actingAs($owner)
            ->get(route('capital.history'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('entries.0.topups', 2)
                ->where('entries.0.topups.0.amount', 250000)
                ->where('entries.0.topups.0.extended_end_date', null)
                ->where('entries.0.topups.1.amount', 750000)
                ->where('entries.0.topups.1.extended_end_date', '2026-10-15'));
    }

    public function test_entries_are_ordered_by_created_at_desc(): void
    {
        $owner = User::factory()->create();
        $older = CapitalEntry::factory()->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'created_at' => '2026-08-01 08:00:00',
        ]);
        $newer = CapitalEntry::factory()->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'created_at' => '2026-09-10 08:00:00',
        ]);

        $this->actingAs($owner)
            ->get(route('capital.history'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('entries.0.id', $newer->id)
                ->where('entries.1.id', $older->id));
    }

    public function test_history_is_scoped_to_current_company(): void
    {
        $owner = User::factory()->create();
        CapitalEntry::factory()->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
        ]);
        CapitalEntry::factory()->create();

        $this->actingAs($owner)
            ->get(route('capital.history'))
            ->assertInertia(fn (Assert $page) => $page->has('entries', 1));
    }
}
