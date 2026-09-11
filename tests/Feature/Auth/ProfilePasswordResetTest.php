<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProfilePasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_request_a_reset_link_from_profile(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('profile.password-reset-link'));

        $response->assertSessionHas('status', __('passwords.sent'))->assertRedirect(route('profile.edit'));
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_profile_reset_request_ignores_a_submitted_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->post(route('profile.password-reset-link'), ['email' => $other->email]);

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertNotSentTo($other, ResetPassword::class);
    }

    public function test_guest_cannot_request_a_reset_link_from_profile(): void
    {
        Notification::fake();

        $this->post(route('profile.password-reset-link'))
            ->assertRedirect(route('login'));

        Notification::assertNothingSent();
    }

    public function test_profile_displays_reset_password_action(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('profile.edit'))
            ->assertInertia(fn ($page) => $page
                ->component('Profile/Edit')
                ->where('status', null));
    }
}
