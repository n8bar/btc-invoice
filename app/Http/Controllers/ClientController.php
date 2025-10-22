<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function __construct()
    {
        // no-op; routes are already wrapped in auth middleware
        // $this->middleware('auth');  
    }

    /**
     * List only the authenticated user's clients (paginated).
     */
    public function index(Request $request)
    {
        $clients = Client::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return response()->json($clients);
    }

    /**
     * Store a new client for the authenticated user.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'notes' => 'nullable|string',
        ]);

        $client = Client::create($data + ['user_id' => $request->user()->id]);

        return response()->json($client, 201);
    }

    /**
     * Show a single client owned by the authenticated user.
     */
    public function show(Request $request, Client $client)
    {
        $this->authorizeOwnership($request, $client);

        return response()->json($client);
    }

    /**
     * Update a client owned by the authenticated user.
     */
    public function update(Request $request, Client $client)
    {
        $this->authorizeOwnership($request, $client);

        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'notes' => 'nullable|string',
        ]);

        $client->update($data);

        return response()->json($client->fresh());
    }

    /**
     * Soft-delete a client owned by the authenticated user.
     */
    public function destroy(Request $request, Client $client)
    {
        $this->authorizeOwnership($request, $client);

        $client->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * Unused in the MVP (UI not built yet). Return 204 to avoid 404s.
     */
    public function create()
    {
        return response()->noContent(204);
    }

    /**
     * Unused in the MVP (UI not built yet). Return 204 to avoid 404s.
     */
    public function edit(Client $client)
    {
        return response()->noContent(204);
    }

    /**
     * Ensure the authenticated user owns the model.
     */
    private function authorizeOwnership(Request $request, Client $client): void
    {
        abort_unless($client->user_id === $request->user()->id, 403);
    }
}
