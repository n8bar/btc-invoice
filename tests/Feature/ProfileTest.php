<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
        $response->assertSee('name="show_invoice_ids"', false);
        $response->assertSee(route('settings.notifications.edit'), false);
        $response->assertDontSee('Auto email paid receipts', false);
        $response->assertDontSee('Show overpayment gratuity note to clients', false);
        $response->assertDontSee('Show QR refresh reminder to clients', false);
    }

    public function test_profile_page_includes_password_visibility_toggles(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
        $response->assertSee('Show current password');
        $response->assertSee('Show new password');
        $response->assertSee('Show password confirmation');
        $response->assertSee("x-bind:type=\"showCurrentPassword ? 'text' : 'password'\"", false);
        $response->assertSee("x-bind:type=\"showNewPassword ? 'text' : 'password'\"", false);
        $response->assertSee("x-bind:type=\"showPasswordConfirmation ? 'text' : 'password'\"", false);
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create([
            'show_overpayment_gratuity_note' => false,
            'show_qr_refresh_reminder' => false,
            'auto_receipt_emails' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'show_invoice_ids' => true,
                'auto_receipt_emails' => false,
                'show_overpayment_gratuity_note' => true,
                'show_qr_refresh_reminder' => true,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue($user->show_invoice_ids);
        $this->assertTrue($user->auto_receipt_emails);
        $this->assertFalse($user->show_overpayment_gratuity_note);
        $this->assertFalse($user->show_qr_refresh_reminder);
    }

    public function test_settings_index_redirects_to_profile_tab(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.index'))
            ->assertRedirect(route('profile.edit'));
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
