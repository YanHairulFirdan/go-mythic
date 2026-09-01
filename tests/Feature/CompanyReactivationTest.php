<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * US-SUB-07 AC4: reaktivasi company setelah approval. The Owner self-service
 * close flow (which produces status=closed + inactive_reason=company_closed) is a
 * separate task; here that state is set up directly.
 */
class CompanyReactivationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Admin, 1: User, 2: User, 3: Payment}
     */
    private function closedCompanyWithPendingPayment(): array
    {
        $admin = Admin::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);
        $owner->company->update(['status' => 'closed', 'paid_until' => null]);

        $owner->update(['status' => 'inactive', 'inactive_reason' => 'company_closed']);
        $employee = User::factory()->create([
            'role' => 'employee',
            'company_id' => $owner->company_id,
            'status' => 'inactive',
            'inactive_reason' => 'company_closed',
        ]);

        $payment = Payment::factory()->create([
            'company_id' => $owner->company_id,
            'status' => 'pending',
        ]);

        return [$admin, $owner, $employee, $payment];
    }

    public function test_approving_a_payment_reactivates_a_closed_company(): void
    {
        [$admin, $owner, $employee, $payment] = $this->closedCompanyWithPendingPayment();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.payments.approve', $payment))
            ->assertRedirect(route('admin.payments.index'));

        $company = $owner->company->fresh();
        $this->assertSame('active', $company->status);
        $this->assertNotNull($company->paid_until);

        foreach ([$owner, $employee] as $user) {
            $user->refresh();
            $this->assertSame('active', $user->status);
            $this->assertNull($user->inactive_reason);
        }
    }

    public function test_reactivation_only_restores_company_closed_accounts(): void
    {
        [$admin, $owner, , $payment] = $this->closedCompanyWithPendingPayment();
        $banned = User::factory()->create([
            'role' => 'employee',
            'company_id' => $owner->company_id,
            'status' => 'inactive',
            'inactive_reason' => 'admin_ban',
        ]);

        $this->actingAs($admin, 'admin')->post(route('admin.payments.approve', $payment));

        $banned->refresh();
        $this->assertSame('inactive', $banned->status);
        $this->assertSame('admin_ban', $banned->inactive_reason);
    }

    public function test_approving_for_an_active_company_leaves_user_status_alone(): void
    {
        $admin = Admin::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);
        $employee = User::factory()->create(['role' => 'employee', 'company_id' => $owner->company_id]);
        $payment = Payment::factory()->create(['company_id' => $owner->company_id, 'status' => 'pending']);

        $this->actingAs($admin, 'admin')->post(route('admin.payments.approve', $payment));

        $this->assertSame('active', $owner->company->fresh()->status);
        $this->assertSame('active', $employee->fresh()->status);
        $this->assertSame('active', $owner->fresh()->status);
    }
}
