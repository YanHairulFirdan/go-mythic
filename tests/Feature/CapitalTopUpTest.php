<?php

namespace Tests\Feature;

use App\Models\CapitalEntry;
use App\Models\CapitalTopup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CapitalTopUpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-10 09:00:00');
    }

    private function activeEntryFor(User $owner, array $overrides = []): CapitalEntry
    {
        return CapitalEntry::factory()->create(array_merge([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'initial_amount' => 3_000_000,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ], $overrides));
    }

    public function test_employee_cannot_top_up(): void
    {
        $owner = User::factory()->create();
        $entry = $this->activeEntryFor($owner);
        $employee = User::factory()->create(['role' => 'employee', 'company_id' => $owner->company_id]);

        $this->actingAs($employee)
            ->patch(route('capital.top-up', $entry), ['amount' => 500_000])
            ->assertForbidden();

        $this->assertDatabaseCount('capital_topups', 0);
    }

    public function test_owner_can_top_up_the_active_capital_entry(): void
    {
        $owner = User::factory()->create();
        $entry = $this->activeEntryFor($owner);

        $this->actingAs($owner)
            ->patch(route('capital.top-up', $entry), ['amount' => 2_500_000])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('capital.index'));

        $this->assertDatabaseHas('capital_topups', [
            'capital_entry_id' => $entry->id,
            'amount' => 2_500_000,
            'changed_by' => $owner->id,
            'extended_end_date' => null,
        ]);
        $this->assertNotNull($entry->topups()->first()->changed_at);

        // initial_amount stays the original; running total is derived.
        $entry->refresh();
        $this->assertSame(3_000_000.0, (float) $entry->initial_amount);
        $this->assertSame(5_500_000.0, $entry->periodTotal());
    }

    public function test_index_active_entry_exposes_period_total(): void
    {
        $owner = User::factory()->create();
        $entry = $this->activeEntryFor($owner);
        CapitalTopup::factory()->for($entry)->create(['amount' => 1_000_000]);
        CapitalTopup::factory()->for($entry)->create(['amount' => 250_000]);

        $this->actingAs($owner)
            ->get(route('capital.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Capital/Index')
                ->where('activeEntry.initial_amount', 3000000)
                ->where('activeEntry.period_total', 4250000));
    }

    public function test_multiple_top_ups_accumulate(): void
    {
        $owner = User::factory()->create();
        $entry = $this->activeEntryFor($owner);

        $this->actingAs($owner)->patch(route('capital.top-up', $entry), ['amount' => 1_000_000]);
        $this->actingAs($owner)->patch(route('capital.top-up', $entry), ['amount' => 500_000]);

        $this->assertDatabaseCount('capital_topups', 2);
        $this->assertSame(4_500_000.0, $entry->fresh()->periodTotal());
    }

    public function test_extend_end_date_updates_entry_and_is_recorded(): void
    {
        $owner = User::factory()->create();
        $entry = $this->activeEntryFor($owner);

        $this->actingAs($owner)
            ->patch(route('capital.top-up', $entry), [
                'amount' => 1_000_000,
                'extended_end_date' => '2026-10-15',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-10-15', $entry->fresh()->end_date);
        $this->assertDatabaseHas('capital_topups', [
            'capital_entry_id' => $entry->id,
            'extended_end_date' => '2026-10-15',
        ]);
    }

    public function test_top_up_without_extend_keeps_end_date(): void
    {
        $owner = User::factory()->create();
        $entry = $this->activeEntryFor($owner);

        $this->actingAs($owner)
            ->patch(route('capital.top-up', $entry), ['amount' => 1_000_000])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-09-30', $entry->fresh()->end_date);
    }

    public function test_extended_end_date_must_be_after_current_end_date(): void
    {
        $owner = User::factory()->create();
        $entry = $this->activeEntryFor($owner);

        $this->actingAs($owner)
            ->patch(route('capital.top-up', $entry), [
                'amount' => 1_000_000,
                'extended_end_date' => '2026-09-30',
            ])
            ->assertInvalid('extended_end_date');

        $this->actingAs($owner)
            ->patch(route('capital.top-up', $entry), [
                'amount' => 1_000_000,
                'extended_end_date' => '2026-09-20',
            ])
            ->assertInvalid('extended_end_date');
    }

    public function test_top_up_amount_must_be_positive(): void
    {
        $owner = User::factory()->create();
        $entry = $this->activeEntryFor($owner);

        $this->actingAs($owner)->patch(route('capital.top-up', $entry), ['amount' => 0])->assertInvalid('amount');
        $this->actingAs($owner)->patch(route('capital.top-up', $entry), ['amount' => -1000])->assertInvalid('amount');

        $this->assertDatabaseCount('capital_topups', 0);
    }

    public function test_cannot_top_up_another_companys_entry(): void
    {
        $owner = User::factory()->create();
        $foreignEntry = CapitalEntry::factory()->create([
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);

        $this->actingAs($owner)
            ->patch(route('capital.top-up', $foreignEntry), ['amount' => 1_000_000])
            ->assertForbidden();

        $this->assertDatabaseCount('capital_topups', 0);
    }

    public function test_cannot_top_up_an_expired_entry(): void
    {
        $owner = User::factory()->create();
        $expired = $this->activeEntryFor($owner, [
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
        ]);

        $this->actingAs($owner)
            ->patch(route('capital.top-up', $expired), ['amount' => 1_000_000])
            ->assertInvalid('amount');

        $this->assertDatabaseCount('capital_topups', 0);
    }
}
