<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\WalletSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('CryptoZing - Login', false);
    }

    public function test_incomplete_users_are_redirected_to_getting_started_on_login(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('getting-started.start'));
    }

    public function test_users_with_wallet_but_incomplete_onboarding_are_redirected_to_getting_started_on_login(): void
    {
        $user = User::factory()->create();
        WalletSetting::create([
            'user_id' => $user->id,
            'network' => 'testnet',
            'bip84_xpub' => 'vpub-test-key',
            'next_derivation_index' => 0,
            'onboarded_at' => now(),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('getting-started.start'));
    }

    public function test_completed_users_are_redirected_to_dashboard_on_login(): void
    {
        $user = User::factory()->create([
            'getting_started_completed_at' => now(),
            'getting_started_dismissed' => false,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_replay_users_are_redirected_to_getting_started_on_login(): void
    {
        $user = User::factory()->create([
            'getting_started_completed_at' => null,
            'getting_started_dismissed' => false,
            'getting_started_replay_started_at' => now(),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('getting-started.start'));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_login_error_summary_is_visible_on_invalid_attempt(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->from('/login')
            ->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');

        $page = $this->get('/login');

        $page->assertOk();
        $page->assertSee('id="login-error-summary"', false);
        $page->assertSee('We couldn’t sign you in. Check your email and password, then try again.', false);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
