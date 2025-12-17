<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpfulNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_page_is_public_and_indexable(): void
    {
        $response = $this->get(route('help'));

        $response->assertOk();
        $response->assertHeaderMissing('X-Robots-Tag');
        $response->assertDontSee('<meta name="robots" content="noindex,nofollow,noarchive">', false);
        $response->assertSee('<link rel="canonical" href="' . route('help') . '">', false);
        $response->assertSee('name="description"', false);
        $response->assertSee('Helpful Notes');
        $response->assertSee('Extended public keys', false);
        $response->assertSee('seed phrase', false);
    }

    public function test_help_page_shows_back_link_when_linked_from_wallet_settings(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('help', ['from' => 'wallet-settings']));

        $response->assertOk();
        $response->assertSee('Back to Wallet Settings');
        $response->assertSee(route('wallet.settings.edit'));
        $response->assertSee('<link rel="canonical" href="' . route('help') . '">', false);
    }

    public function test_wallet_settings_links_to_specific_help_note(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('wallet.settings.edit'));

        $response->assertOk();
        $response->assertSee('href="' . route('help', ['from' => 'wallet-settings']) . '#xpub-safety"', false);
    }
}
