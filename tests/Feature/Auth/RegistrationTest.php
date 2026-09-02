<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_registration_requires_company_owner_email_whatsapp_and_password(): void
    {
        $response = $this->post('/register', []);

        $response->assertSessionHasErrors([
            'business_name',
            'owner_name',
            'email',
            'whatsapp',
            'password',
        ]);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'owner@example.com',
        ]);

        $response = $this->post('/register', [
            'business_name' => 'New Business',
            'owner_name' => 'New Owner',
            'email' => $existingUser->email,
            'whatsapp' => '+628123456789',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('companies', 1);
    }

    public function test_registration_creates_company_and_owner_without_email_verification(): void
    {
        $response = $this->post('/register', [
            'business_name' => 'Kedai Dibya',
            'owner_name' => 'Dibya Owner',
            'email' => 'owner@example.com',
            'whatsapp' => '+628123456789',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::query()->where('email', 'owner@example.com')->firstOrFail();

        $this->assertDatabaseHas('companies', [
            'id' => $user->company_id,
            'name' => 'Kedai Dibya',
            'owner_name' => 'Dibya Owner',
            'email' => 'owner@example.com',
            'phone' => '+628123456789',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'company_id' => $user->company_id,
            'name' => 'Dibya Owner',
            'role' => 'owner',
            'status' => 'active',
            'email_verified_at' => null,
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_owner_is_authenticated_and_can_open_dashboard_without_verification(): void
    {
        $response = $this->post('/register', [
            'business_name' => 'Kedai Dibya',
            'owner_name' => 'Dibya Owner',
            'email' => 'owner@example.com',
            'whatsapp' => '+628123456789',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_registration_rejects_password_shorter_than_eight_characters(): void
    {
        $response = $this->post('/register', [
            'business_name' => 'Kedai Dibya',
            'owner_name' => 'Dibya Owner',
            'email' => 'owner@example.com',
            'whatsapp' => '+628123456789',
            'password' => '1234567',
            'password_confirmation' => '1234567',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('companies', 0);
    }
}
