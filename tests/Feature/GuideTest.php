<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_guide(): void
    {
        $this->get(route('guide'))->assertRedirect(route('login'));
    }

    public function test_owner_can_view_guide(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('guide'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Guide'));
    }

    public function test_employee_can_view_guide(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAs($employee)
            ->get(route('guide'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Guide'));
    }
}
