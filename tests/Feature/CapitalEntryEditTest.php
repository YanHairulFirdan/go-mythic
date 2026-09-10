<?php

namespace Tests\Feature;

use App\Models\CapitalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CapitalEntryEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-15 08:00:00');
    }

    private function entryFor(User $owner, array $attributes = []): CapitalEntry
    {
        return CapitalEntry::factory()->create(array_merge([
            'company_id' => $owner->company_id,
            'initial_amount' => 3_000_000,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ], $attributes));
    }

    public function test_guest_cannot_update_or_delete_capital_entry(): void
    {
        $entry = CapitalEntry::factory()->create();

        $this->patch(route('capital.update', $entry), [
            'initial_amount' => 5_000_000,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ])->assertRedirect(route('login'));

        $this->delete(route('capital.destroy', $entry))->assertRedirect(route('login'));
    }

    public function test_employee_cannot_update_capital_entry(): void
    {
        $owner = User::factory()->create();
        $employee = User::factory()->create(['company_id' => $owner->company_id, 'role' => 'employee']);
        $entry = $this->entryFor($owner);

        $this->actingAs($employee)->patch(route('capital.update', $entry), [
            'initial_amount' => 9_000_000,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ])->assertForbidden();

        $this->assertDatabaseHas('capital_entries', ['id' => $entry->id, 'initial_amount' => 3_000_000]);
    }

    public function test_employee_cannot_delete_capital_entry(): void
    {
        $owner = User::factory()->create();
        $employee = User::factory()->create(['company_id' => $owner->company_id, 'role' => 'employee']);
        $entry = $this->entryFor($owner);

        $this->actingAs($employee)->delete(route('capital.destroy', $entry))->assertForbidden();

        $this->assertNotSoftDeleted($entry);
    }

    public function test_owner_can_update_amount_and_period(): void
    {
        $owner = User::factory()->create();
        $entry = $this->entryFor($owner);

        $this->actingAs($owner)->patch(route('capital.update', $entry), [
            'initial_amount' => 7_500_000,
            'start_date' => '2026-09-05',
            'end_date' => '2026-10-04',
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('capital.index'));

        $this->assertDatabaseHas('capital_entries', [
            'id' => $entry->id,
            'initial_amount' => 7_500_000,
            'start_date' => '2026-09-05',
            'end_date' => '2026-10-04',
        ]);
    }

    public function test_update_rejects_non_positive_amount(): void
    {
        $owner = User::factory()->create();
        $entry = $this->entryFor($owner);

        $this->actingAs($owner)->patch(route('capital.update', $entry), [
            'initial_amount' => 0,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ])->assertInvalid('initial_amount');

        $this->actingAs($owner)->patch(route('capital.update', $entry), [
            'initial_amount' => -1000,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ])->assertInvalid('initial_amount');
    }

    public function test_update_rejects_end_before_start(): void
    {
        $owner = User::factory()->create();
        $entry = $this->entryFor($owner);

        $this->actingAs($owner)->patch(route('capital.update', $entry), [
            'initial_amount' => 3_000_000,
            'start_date' => '2026-09-20',
            'end_date' => '2026-09-10',
        ])->assertInvalid('end_date');
    }

    public function test_update_may_change_own_range_freely(): void
    {
        $owner = User::factory()->create();
        $entry = $this->entryFor($owner);

        // New range overlaps only the entry's own previous range — allowed.
        $this->actingAs($owner)->patch(route('capital.update', $entry), [
            'initial_amount' => 3_000_000,
            'start_date' => '2026-09-15',
            'end_date' => '2026-12-31',
        ])->assertSessionHasNoErrors();
    }

    public function test_update_rejects_range_overlapping_another_entry(): void
    {
        $owner = User::factory()->create();
        $entry = $this->entryFor($owner);
        $this->entryFor($owner, ['start_date' => '2026-10-01', 'end_date' => '2026-10-31']);

        $this->actingAs($owner)->patch(route('capital.update', $entry), [
            'initial_amount' => 3_000_000,
            'start_date' => '2026-09-01',
            'end_date' => '2026-10-15',
        ])->assertInvalid('start_date');

        $this->assertDatabaseHas('capital_entries', ['id' => $entry->id, 'end_date' => '2026-09-30']);
    }

    public function test_owner_cannot_update_entry_from_another_company(): void
    {
        $owner = User::factory()->create();
        $foreign = CapitalEntry::factory()->create();

        $this->actingAs($owner)->patch(route('capital.update', $foreign), [
            'initial_amount' => 1_000_000,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ])->assertForbidden();
    }

    public function test_owner_can_delete_capital_entry(): void
    {
        $owner = User::factory()->create();
        $entry = $this->entryFor($owner);

        $this->actingAs($owner)->delete(route('capital.destroy', $entry))
            ->assertRedirect(route('capital.index'));

        $this->assertSoftDeleted($entry);
    }

    public function test_owner_cannot_delete_entry_from_another_company(): void
    {
        $owner = User::factory()->create();
        $foreign = CapitalEntry::factory()->create();

        $this->actingAs($owner)->delete(route('capital.destroy', $foreign))->assertForbidden();

        $this->assertNotSoftDeleted($foreign);
    }

    public function test_capital_page_has_no_active_entry_after_delete(): void
    {
        $owner = User::factory()->create();
        $entry = $this->entryFor($owner, ['start_date' => '2026-09-10', 'end_date' => '2026-10-10']);

        $this->actingAs($owner)->delete(route('capital.destroy', $entry));

        $this->actingAs($owner)
            ->get(route('capital.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Capital/Index')
                ->where('activeEntry', null));
    }
}
