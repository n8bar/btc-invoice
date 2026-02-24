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

    public function test_clients_index_shows_linked_client_rows_and_actions(): void
    {
        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme Co',
            'email' => 'billing@example.com',
            'notes' => 'Monthly design retainers',
        ]);

        $response = $this->actingAs($owner)->get(route('clients.index'));

        $response->assertOk();
        $response->assertSee('Manage the people and businesses you invoice.', false);
        $response->assertSee(route('clients.show', $client), false);
        $response->assertSee('New client', false);
        $response->assertSee('Trash', false);
        $response->assertSee('overflow-x-auto', false);
    }

    public function test_client_update_redirects_back_to_edit_with_status_flash(): void
    {
        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme Co',
            'email' => 'billing@example.com',
            'notes' => null,
        ]);

        $response = $this
            ->actingAs($owner)
            ->put(route('clients.update', $client), [
                'name' => 'Acme Holdings',
                'email' => 'accounts@acme.example',
                'notes' => 'Updated contact data',
            ]);

        $response->assertRedirect(route('clients.edit', $client));
        $response->assertSessionHas('status', 'Client updated.');

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'name' => 'Acme Holdings',
            'email' => 'accounts@acme.example',
        ]);
    }

    public function test_client_edit_page_displays_delete_section(): void
    {
        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme Co',
            'email' => 'billing@example.com',
            'notes' => null,
        ]);

        $response = $this
            ->actingAs($owner)
            ->get(route('clients.edit', $client));

        $response->assertOk();
        $response->assertSee('Delete client', false);
        $response->assertSee(route('clients.destroy', $client), false);
    }
}
