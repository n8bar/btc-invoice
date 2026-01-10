<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientShowRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_show_redirects_to_edit_for_owner(): void
    {
        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme Co',
            'email' => 'billing@example.com',
            'notes' => null,
        ]);

        $response = $this->actingAs($owner)->get(route('clients.show', $client));

        $response->assertRedirect(route('clients.edit', $client));
    }
}
