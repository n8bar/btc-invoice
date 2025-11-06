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
        $this->authorize('viewAny', Client::class);

        $clients = Client::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('clients.index', compact('clients'));
    }

    /**
     * Store a new client for the authenticated user.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Client::class);

        $data = $request->validate([
            'name'  => ['required','string','max:255'],
            'email' => ['nullable','email','max:255'],
            'notes' => ['nullable','string','max:2000'],
        ]);

        $client = Client::create([
            'user_id' => $request->user()->id,
            'name'    => $data['name'],
            'email'   => $data['email'] ?? null,
            'notes'   => $data['notes'] ?? null,
        ]);

        if ($request->wantsJson()) {
            return response()->json($client, 201);
        }

        return redirect()->route('clients.index')->with('status', 'Client created.');
    }

    /**
     * Show a single client owned by the authenticated user.
     */
    public function show(Request $request, Client $client)
    {
        $this->authorize('view', $client);

        return response()->json($client);
    }

    /**
     * Update a client owned by the authenticated user.
     */
    public function update(Request $request, Client $client)
    {
        $this->authorize('update', $client);

        $data = $request->validate([
            'name'  => ['required','string','max:255'],
            'email' => ['nullable','email','max:255'],
            'notes' => ['nullable','string','max:2000'],
        ]);

        $client->update($data);

        if ($request->wantsJson()) {
            return response()->json($client->fresh());
        }

        return redirect()->route('clients.index')->with('status', 'Client updated.');
    }
    /**
     * Soft-delete a client owned by the authenticated user.
     */
    public function destroy(Request $request, Client $client)
    {
        $this->authorize('delete', $client);

        $client->delete();

        if ($request->wantsJson()) {
            return response()->json(['deleted' => true]);
        }

        return redirect()->route('clients.index')->with('status', 'Client deleted.');
    }

    /**
     * Unused in the MVP (UI not built yet). Return 204 to avoid 404s.
     */
    public function create()
    {
        $this->authorize('create', Client::class);

        return view('clients.create');
    }

    /**
     * Unused in the MVP (UI not built yet). Return 204 to avoid 404s.
     */
    public function edit(Request $request, Client $client)
    {
        $this->authorize('update', $client);
        return view('clients.edit', ['client' => $client]);
    }


    // List trashed clients
    public function trash(Request $request)
    {
        $this->authorize('viewAny', Client::class);

        $clients = Client::onlyTrashed()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('deleted_at')
            ->paginate(15)
            ->withQueryString();

        return view('clients.trash', compact('clients'));
    }

// Restore by id (includes trashed)
    public function restore(Request $request, int $clientId)
    {
        $client = Client::withTrashed()->findOrFail($clientId);
        $this->authorize('restore', $client);

        $client->restore();

        if ($request->wantsJson()) {
            return response()->json($client->fresh());
        }
        return redirect()->route('clients.trash')->with('status', 'Client restored.');
    }

// Permanently delete by id
    public function forceDestroy(Request $request, int $clientId)
    {
        $client = Client::withTrashed()->findOrFail($clientId);
        $this->authorize('forceDelete', $client);

        $client->forceDelete();

        if ($request->wantsJson()) {
            return response()->json(['deleted' => true]);
        }
        return redirect()->route('clients.trash')->with('status', 'Client permanently deleted.');
    }



}
