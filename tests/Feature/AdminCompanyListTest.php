<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminCompanyListTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_admin_company_list(): void
    {
        $this->get(route('admin.companies.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_owner_user_cannot_access_admin_company_list(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->get(route('admin.companies.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_sees_companies_with_name_owner_status_paid_until(): void
    {
        $admin = Admin::factory()->create();
        $paid = Company::factory()->create([
            'name' => 'Toko Bintang',
            'owner_name' => 'Budi Santoso',
            'paid_until' => now()->addDays(20),
        ]);
        $free = Company::factory()->create([
            'name' => 'Warung Melati',
            'owner_name' => 'Siti Aminah',
            'paid_until' => null,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.companies.index'))
            ->assertOk()
            // Ordered by name: Toko Bintang before Warung Melati.
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Companies/Index')
                ->has('companies', 2)
                ->where('companies.0.name', 'Toko Bintang')
                ->where('companies.0.owner_name', 'Budi Santoso')
                ->where('companies.0.subscription_status', 'Paid')
                ->where('companies.0.paid_until', $paid->paid_until->toDateString())
                ->where('companies.1.name', 'Warung Melati')
                ->where('companies.1.owner_name', 'Siti Aminah')
                ->where('companies.1.subscription_status', 'Free')
                ->where('companies.1.paid_until', null));
    }

    public function test_company_status_is_free_when_paid_until_null_or_past(): void
    {
        $admin = Admin::factory()->create();
        Company::factory()->create(['name' => 'Null Co', 'paid_until' => null]);
        Company::factory()->create(['name' => 'Expired Co', 'paid_until' => now()->subDay()]);
        Company::factory()->create(['name' => 'Active Co', 'paid_until' => now()->addDay()]);

        // Ordered by name: Active Co, Expired Co, Null Co.
        $this->actingAs($admin, 'admin')
            ->get(route('admin.companies.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('companies', 3)
                ->where('companies.0.name', 'Active Co')
                ->where('companies.0.subscription_status', 'Paid')
                ->where('companies.1.name', 'Expired Co')
                ->where('companies.1.subscription_status', 'Free')
                ->where('companies.2.name', 'Null Co')
                ->where('companies.2.subscription_status', 'Free'));
    }

    public function test_admin_can_search_companies_by_business_name(): void
    {
        $admin = Admin::factory()->create();
        Company::factory()->create(['name' => 'Kopi Senja']);
        Company::factory()->create(['name' => 'Bengkel Jaya']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.companies.index', ['search' => 'senja']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('companies', 1)
                ->where('companies.0.name', 'Kopi Senja'));
    }

    public function test_admin_can_search_companies_by_owner_email(): void
    {
        $admin = Admin::factory()->create();
        Company::factory()->create(['name' => 'Match Co', 'email' => 'owner@match.test']);
        Company::factory()->create(['name' => 'Other Co', 'email' => 'someone@other.test']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.companies.index', ['search' => 'owner@match.test']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('companies', 1)
                ->where('companies.0.name', 'Match Co'));
    }

    public function test_admin_can_filter_companies_by_paid_status(): void
    {
        $admin = Admin::factory()->create();
        Company::factory()->create(['name' => 'Paid Co', 'paid_until' => now()->addDays(5)]);
        Company::factory()->create(['name' => 'Free Co', 'paid_until' => null]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.companies.index', ['status' => 'paid']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('companies', 1)
                ->where('companies.0.name', 'Paid Co'));

        $this->actingAs($admin, 'admin')
            ->get(route('admin.companies.index', ['status' => 'free']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('companies', 1)
                ->where('companies.0.name', 'Free Co'));
    }

    public function test_invalid_status_filter_is_rejected(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.companies.index', ['status' => 'platinum']))
            ->assertSessionHasErrors('status');
    }
}
