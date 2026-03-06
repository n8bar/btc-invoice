<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;
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
        $response->assertSee('Workspace preferences', false);
        $response->assertSee('Client-facing payment notes', false);
        $response->assertSee('Save profile', false);
    }

    public function test_profile_note_toggles_default_to_checked_when_values_are_null(): void
    {
        $user = User::factory()->create();
        $user->setAttribute('show_overpayment_gratuity_note', null);
        $user->setAttribute('show_qr_refresh_reminder', null);

        $html = view('profile.partials.update-profile-information-form', [
            'user' => $user,
            'errors' => new ViewErrorBag(),
        ])->render();

        $this->assertMatchesRegularExpression('/id="show_overpayment_gratuity_note"[^>]*checked/', $html);
        $this->assertMatchesRegularExpression('/id="show_qr_refresh_reminder"[^>]*checked/', $html);
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'show_invoice_ids' => true,
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
        $this->assertTrue($user->show_overpayment_gratuity_note);
        $this->assertTrue($user->show_qr_refresh_reminder);
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
