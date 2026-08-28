<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_subscription_page(): void
    {
        $this->get(route('subscription.index'))
            ->assertRedirect(route('login'));
    }

    public function test_owner_can_view_subscription_page(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->get(route('subscription.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Subscription/Index')
                ->where('paid', false)
                ->where('pendingPayment', null));
    }

    public function test_employee_cannot_view_or_submit_subscription_payment(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAs($employee)
            ->get(route('subscription.index'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->post(route('subscription.payment.store'), [
                'proof' => UploadedFile::fake()->image('proof.png'),
            ])
            ->assertForbidden();
    }

    public function test_owner_uploads_valid_proof_as_pending_payment(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->post(route('subscription.payment.store'), [
                'proof' => UploadedFile::fake()->image('transfer.png'),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('subscription.index'));

        $payment = Payment::query()->firstOrFail();

        $this->assertSame($owner->company_id, $payment->company_id);
        $this->assertEquals(99000, $payment->amount);
        $this->assertSame('pending', $payment->status);
        $this->assertNotEmpty($payment->attachment_path);
        Storage::disk('local')->assertExists($payment->attachment_path);
    }

    public function test_payment_fields_are_owned_by_the_server(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $otherCompany = Company::factory()->create();

        $this->actingAs($owner)
            ->post(route('subscription.payment.store'), [
                'company_id' => $otherCompany->id,
                'amount' => 1,
                'status' => 'approved',
                'proof' => UploadedFile::fake()->image('transfer.png'),
            ])
            ->assertRedirect(route('subscription.index'));

        $this->assertDatabaseHas('payments', [
            'company_id' => $owner->company_id,
            'amount' => 99000,
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('payments', [
            'company_id' => $otherCompany->id,
        ]);
    }

    public function test_gif_and_non_image_proofs_are_rejected(): void
    {
        $owner = User::factory()->create();

        foreach ([
            UploadedFile::fake()->image('transfer.gif'),
            UploadedFile::fake()->create('transfer.pdf', 100, 'application/pdf'),
        ] as $proof) {
            $this->actingAs($owner)
                ->post(route('subscription.payment.store'), ['proof' => $proof])
                ->assertSessionHasErrors('proof');
        }

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_proof_larger_than_one_megabyte_is_rejected(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->post(route('subscription.payment.store'), [
                'proof' => UploadedFile::fake()->create('large.jpg', 1025, 'image/jpeg'),
            ])
            ->assertSessionHasErrors('proof');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_pending_payment_does_not_change_paid_until(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $paidUntil = now()->addDays(10);
        $owner->company->update(['paid_until' => $paidUntil]);

        $this->actingAs($owner)
            ->post(route('subscription.payment.store'), [
                'proof' => UploadedFile::fake()->image('renewal.png'),
            ])
            ->assertRedirect(route('subscription.index'));

        $this->assertEquals($paidUntil->getTimestamp(), $owner->company->fresh()->paid_until->getTimestamp());
    }

    public function test_paid_owner_can_submit_renewal_proof(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $owner->company->update(['paid_until' => now()->addDays(10)]);

        $this->actingAs($owner)
            ->post(route('subscription.payment.store'), [
                'proof' => UploadedFile::fake()->image('renewal.png'),
            ])
            ->assertRedirect(route('subscription.index'));

        $this->assertDatabaseHas('payments', [
            'company_id' => $owner->company_id,
            'status' => 'pending',
        ]);
    }
}
