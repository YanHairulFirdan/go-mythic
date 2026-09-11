<?php

namespace Tests\Feature;

use App\Models\CapitalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CapitalEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-01 08:00:00');
    }

    public function test_guest_is_redirected_from_capital_page(): void
    {
        $this->get(route('capital.index'))->assertRedirect(route('login'));
    }

    public function test_employee_cannot_access_capital_page_or_store(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAs($employee)->get(route('capital.index'))->assertForbidden();
        $this->actingAs($employee)->post(route('capital.store'), [
            'duration' => '1_year',
            'initial_amount' => 1000000,
        ])->assertForbidden();
    }

    public function test_owner_sees_set_form_when_no_active_capital(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->get(route('capital.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Capital/Index')
                ->where('activeEntry', null));
    }

    public function test_owner_can_set_capital_for_one_year(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->post(route('capital.store'), [
                'duration' => '1_year',
                'initial_amount' => 3000000,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('capital.index'));

        $this->assertDatabaseHas('capital_entries', [
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'initial_amount' => 3000000,
            'start_date' => '2026-09-01',
            'end_date' => '2027-08-31',
        ]);
    }

    public function test_no_end_duration_stores_a_null_end_date(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->post(route('capital.store'), [
            'duration' => 'no_end',
            'initial_amount' => 500000,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('capital_entries', [
            'company_id' => $owner->company_id,
            'start_date' => '2026-09-01',
            'end_date' => null,
        ]);
    }

    public function test_open_ended_entry_blocks_a_later_overlapping_entry(): void
    {
        $owner = User::factory()->create();
        CapitalEntry::factory()->create([
            'company_id' => $owner->company_id,
            'start_date' => '2026-08-01',
            'end_date' => null,
        ]);

        $this->actingAs($owner)->post(route('capital.store'), [
            'duration' => '1_year',
            'initial_amount' => 1000000,
        ])->assertInvalid('duration');

        $this->assertDatabaseCount('capital_entries', 1);
    }

    public function test_owner_can_set_capital_with_custom_range(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->post(route('capital.store'), [
            'duration' => 'custom',
            'initial_amount' => 750000,
            'start_date' => '2026-09-10',
            'end_date' => '2026-10-05',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('capital_entries', [
            'company_id' => $owner->company_id,
            'start_date' => '2026-09-10',
            'end_date' => '2026-10-05',
        ]);
    }

    public function test_custom_range_requires_valid_dates(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->post(route('capital.store'), [
            'duration' => 'custom',
            'initial_amount' => 750000,
        ])->assertInvalid(['start_date', 'end_date']);

        $this->actingAs($owner)->post(route('capital.store'), [
            'duration' => 'custom',
            'initial_amount' => 750000,
            'start_date' => '2026-09-20',
            'end_date' => '2026-09-10',
        ])->assertInvalid('end_date');
    }

    public function test_nominal_must_be_greater_than_zero(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->post(route('capital.store'), [
            'duration' => '1_year',
            'initial_amount' => 0,
        ])->assertInvalid('initial_amount');

        $this->actingAs($owner)->post(route('capital.store'), [
            'duration' => '1_year',
            'initial_amount' => -50000,
        ])->assertInvalid('initial_amount');

        $this->assertDatabaseCount('capital_entries', 0);
    }

    public function test_cannot_set_capital_when_an_active_entry_exists(): void
    {
        $owner = User::factory()->create();
        CapitalEntry::factory()->create([
            'company_id' => $owner->company_id,
            'start_date' => '2026-08-25',
            'end_date' => '2026-09-25',
        ]);

        $this->actingAs($owner)->post(route('capital.store'), [
            'duration' => '1_year',
            'initial_amount' => 1000000,
        ])->assertInvalid('duration');

        $this->assertDatabaseCount('capital_entries', 1);
    }

    public function test_cannot_set_custom_range_overlapping_existing_entry(): void
    {
        $owner = User::factory()->create();
        CapitalEntry::factory()->create([
            'company_id' => $owner->company_id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);

        $this->actingAs($owner)->post(route('capital.store'), [
            'duration' => 'custom',
            'initial_amount' => 1000000,
            'start_date' => '2026-10-20',
            'end_date' => '2026-11-15',
        ])->assertInvalid('start_date');

        $this->assertDatabaseCount('capital_entries', 1);
    }

    public function test_can_set_capital_when_previous_entry_expired(): void
    {
        $owner = User::factory()->create();
        CapitalEntry::factory()->create([
            'company_id' => $owner->company_id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
        ]);

        $this->actingAs($owner)->post(route('capital.store'), [
            'duration' => '1_year',
            'initial_amount' => 2000000,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('capital_entries', 2);
    }

    public function test_owner_sees_active_state_when_capital_active(): void
    {
        $owner = User::factory()->create();
        CapitalEntry::factory()->create([
            'company_id' => $owner->company_id,
            'initial_amount' => 4200000,
            'start_date' => '2026-08-20',
            'end_date' => '2026-09-19',
        ]);

        $this->actingAs($owner)
            ->get(route('capital.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Capital/Index')
                ->where('activeEntry.initial_amount', 4200000)
                ->where('activeEntry.end_date', '2026-09-19'));
    }

    public function test_active_check_is_scoped_per_company(): void
    {
        $owner = User::factory()->create();
        CapitalEntry::factory()->create([
            'start_date' => '2026-08-25',
            'end_date' => '2026-09-25',
        ]);

        // Another company has an active entry; this owner still has none.
        $this->actingAs($owner)->post(route('capital.store'), [
            'duration' => '1_year',
            'initial_amount' => 1000000,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('capital_entries', [
            'company_id' => $owner->company_id,
            'initial_amount' => 1000000,
        ]);
    }
}
