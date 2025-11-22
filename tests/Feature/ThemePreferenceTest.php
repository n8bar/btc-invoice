<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_theme_preference(): void
    {
        $user = User::factory()->create(['theme' => 'system']);

        $response = $this->actingAs($user)->patchJson(route('theme.update'), ['theme' => 'dark']);

        $response->assertOk()
            ->assertJson(['theme' => 'dark']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'theme' => 'dark',
        ]);

        $html = $this->actingAs($user->fresh())->get(route('dashboard'))->getContent();
        $this->assertStringContainsString('data-theme="dark"', $html);
        $this->assertStringContainsString('<html', $html);
    }

    public function test_invalid_theme_rejected(): void
    {
        $user = User::factory()->create(['theme' => 'system']);

        $response = $this->actingAs($user)->patch(route('theme.update'), ['theme' => 'invalid']);

        $response->assertSessionHasErrors('theme');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'theme' => 'system',
        ]);
    }
}
