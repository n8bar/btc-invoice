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

    public function test_registration_screen_includes_password_visibility_toggles(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSee('Show password');
        $response->assertSee('Show password confirmation');
        $response->assertSee("x-bind:type=\"showPassword ? 'text' : 'password'\"", false);
        $response->assertSee("x-bind:type=\"showPasswordConfirmation ? 'text' : 'password'\"", false);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('getting-started.welcome'));
    }

    public function test_mixed_case_email_is_accepted_and_preserved_on_registration(): void
    {
        $email = 'Test.User+Signup@Example.COM';

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => $email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('getting-started.welcome'));

        $this->assertDatabaseHas('users', [
            'email' => $email,
        ]);

        $this->assertTrue(User::where('email', $email)->exists());
    }
}
