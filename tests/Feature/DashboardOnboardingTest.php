<?php

namespace Tests\Feature;

use App\Models\CapitalEntry;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_owner_receives_incomplete_onboarding_state(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('onboarding.profile_complete', true)
                ->where('onboarding.capital_complete', false)
                ->where('onboarding.transaction_complete', false)
                ->where('onboarding.show', true));
    }

    public function test_owner_onboarding_updates_from_company_data(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $entry = CapitalEntry::factory()->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(29)->toDateString(),
        ]);
        $category = TransactionCategory::factory()->create([
            'company_id' => $owner->company_id,
            'type' => 'income',
        ]);
        Transaction::factory()->create([
            'company_id' => $owner->company_id,
            'created_by' => $owner->id,
            'category_id' => $category->id,
            'type' => 'income',
            'transaction_date' => now()->toDateString(),
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('onboarding.profile_complete', true)
                ->where('onboarding.capital_complete', true)
                ->where('onboarding.transaction_complete', true)
                ->where('onboarding.show', false));

        $this->assertDatabaseHas('capital_entries', ['id' => $entry->id]);
    }

    public function test_onboarding_is_tenant_scoped(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $other = User::factory()->create(['role' => 'owner']);
        CapitalEntry::factory()->create([
            'company_id' => $other->company_id,
            'created_by' => $other->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(29)->toDateString(),
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('onboarding.capital_complete', false)
                ->where('onboarding.transaction_complete', false));
    }

    public function test_employee_does_not_receive_onboarding(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('onboarding', null));
    }
}
