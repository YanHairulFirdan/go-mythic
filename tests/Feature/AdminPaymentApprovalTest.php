<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPaymentApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_admin_payment_list(): void
    {
        $this->get(route('admin.payments.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_sees_payment_list_with_company_and_status(): void
    {
        $admin = Admin::factory()->create();
        $owner = User::factory()->create();
        Payment::factory()->create([
            'company_id' => $owner->company_id,
            'status' => 'pending',
            'attachment_path' => 'payment-proofs/proof.png',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Payments/Index')
                ->has('payments', 1)
                ->where('payments.0.company.name', $owner->company->name)
                ->where('payments.0.status', 'pending')
                ->where('payments.0.attachment_path', 'payment-proofs/proof.png'));
    }

    public function test_non_admin_cannot_access_admin_payment_list_or_approve(): void
    {
        $owner = User::factory()->create();
        $payment = Payment::factory()->create(['company_id' => $owner->company_id]);

        $this->actingAs($owner)
            ->get(route('admin.payments.index'))
            ->assertRedirect(route('admin.login'));

        $this->actingAs($owner)
            ->post(route('admin.payments.approve', $payment))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_approval_sets_paid_for_free_company_and_records_admin(): void
    {
        $admin = Admin::factory()->create();
        $owner = User::factory()->create();
        $payment = Payment::factory()->create([
            'company_id' => $owner->company_id,
            'status' => 'pending',
        ]);

        $before = now();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.payments.approve', $payment))
            ->assertRedirect(route('admin.payments.index'));

        $payment->refresh();
        $owner->company->refresh();

        $this->assertSame('approved', $payment->status);
        $this->assertSame($admin->id, $payment->approved_by);
        $this->assertNotNull($payment->approved_at);
        $this->assertTrue($payment->approved_at->greaterThanOrEqualTo($before));
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, $owner->company->paid_until->timestamp, 2);
    }

    public function test_admin_approval_extends_active_subscription_by_thirty_days(): void
    {
        $admin = Admin::factory()->create();
        $owner = User::factory()->create();
        $paidUntil = now()->addDays(10);
        $owner->company->update(['paid_until' => $paidUntil]);
        $payment = Payment::factory()->create([
            'company_id' => $owner->company_id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.payments.approve', $payment));

        $this->assertEqualsWithDelta($paidUntil->addDays(30)->timestamp, $owner->company->fresh()->paid_until->timestamp, 2);
    }

    public function test_approved_payment_cannot_be_approved_again(): void
    {
        $admin = Admin::factory()->create();
        $owner = User::factory()->create();
        $payment = Payment::factory()->create([
            'company_id' => $owner->company_id,
            'status' => 'approved',
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);
        $owner->company->update(['paid_until' => now()->addDays(30)]);
        $paidUntil = $owner->company->paid_until;

        $this->actingAs($admin, 'admin')
            ->post(route('admin.payments.approve', $payment))
            ->assertRedirect(route('admin.payments.index'));

        $this->assertEquals($paidUntil->timestamp, $owner->company->fresh()->paid_until->timestamp);
    }
}
