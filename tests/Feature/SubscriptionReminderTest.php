<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SubscriptionExpiring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

class SubscriptionReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_sends_reminder_to_owner_three_days_before_expiry(): void
    {
        NotificationFacade::fake();
        $today = now()->startOfDay();
        $owner = User::factory()->create();
        $owner->company->update(['paid_until' => $today->copy()->addDays(3)->setTime(12, 0)]);

        $this->travelTo($today);
        $this->artisan('subscription:remind')->assertSuccessful();

        NotificationFacade::assertSentTo($owner, SubscriptionExpiring::class, function (SubscriptionExpiring $notification) use ($owner): bool {
            return $notification->company->is($owner->company)
                && $notification->toMail($owner)->actionUrl === route('subscription.index');
        });

        $this->travelBack();
    }

    public function test_command_skips_companies_not_expiring_in_three_days(): void
    {
        NotificationFacade::fake();
        $today = now()->startOfDay();
        $matchingOwner = User::factory()->create();
        $matchingOwner->company->update(['paid_until' => $today->copy()->addDays(3)]);
        $otherOwner = User::factory()->create();
        $otherOwner->company->update(['paid_until' => $today->copy()->addDays(2)]);
        $freeOwner = User::factory()->create();

        $this->travelTo($today);
        $this->artisan('subscription:remind')->assertSuccessful();

        NotificationFacade::assertSentTo($matchingOwner, SubscriptionExpiring::class);
        NotificationFacade::assertNotSentTo($otherOwner, SubscriptionExpiring::class);
        NotificationFacade::assertNotSentTo($freeOwner, SubscriptionExpiring::class);

        $this->travelBack();
    }

    public function test_mail_failure_does_not_fail_command_or_change_subscription_access_state(): void
    {
        $today = now()->startOfDay();
        $owner = User::factory()->create();
        $paidUntil = $today->copy()->addDays(3);
        $owner->company->update(['paid_until' => $paidUntil]);

        app()->make(ChannelManager::class)->extend('mail', fn () => new class
        {
            public function send($notifiable, Notification $notification): void
            {
                throw new \RuntimeException('mail transport unavailable');
            }
        });

        $this->travelTo($today);
        $this->artisan('subscription:remind')->assertSuccessful();

        $this->assertSame('active', $owner->fresh()->status);
        $this->assertEquals($paidUntil->timestamp, $owner->company->fresh()->paid_until->timestamp);
        $this->actingAs($owner->fresh())->get(route('dashboard'))->assertOk();

        $this->travelBack();
    }

    public function test_command_skips_closed_companies(): void
    {
        NotificationFacade::fake();
        $today = now()->startOfDay();
        $owner = User::factory()->create();
        $owner->company->update([
            'paid_until' => $today->copy()->addDays(3),
            'status' => 'closed',
        ]);

        $this->travelTo($today);
        $this->artisan('subscription:remind')->assertSuccessful();

        NotificationFacade::assertNotSentTo($owner, SubscriptionExpiring::class);
        $this->travelBack();
    }
}
