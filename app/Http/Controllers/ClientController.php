<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Client::class, 'client');
    }

    /**
     * List only the authenticated user's clients (paginated).
     */
    public function index(Request $request)
    {
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
        $data = $this->validatedClientData($request);

        $client = Client::create([
            'user_id' => $request->user()->id,
            'name'    => $data['name'],
            'email'   => $data['email'],
            'notes'   => $data['notes'] ?? null,
        ]);

        if ($request->wantsJson()) {
            return response()->json($client, 201);
        }

        $returnTo = $this->validatedReturnTo($request->input('return_to'));
        if ($returnTo !== null) {
            return redirect($returnTo)->with('status', 'Client created.');
        }

        return redirect()->route('clients.index')->with('status', 'Client created.');
    }

    /**
     * Show a single client owned by the authenticated user.
     */
    public function show(Request $request, Client $client)
    {
        if ($request->wantsJson()) {
            return response()->json($client);
        }

        return redirect()->route('clients.edit', $client);
    }

    /**
     * Update a client owned by the authenticated user.
     */
    public function update(Request $request, Client $client)
    {
        $data = $this->validatedClientData($request);

        $client->update($data);

        if ($request->wantsJson()) {
            return response()->json($client->fresh());
        }

        return redirect()->route('clients.edit', $client)->with('status', 'Client updated.');
    }
    /**
     * Soft-delete a client owned by the authenticated user.
     */
    public function destroy(Request $request, Client $client)
    {
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
        return view('clients.create');
    }

    /**
     * Unused in the MVP (UI not built yet). Return 204 to avoid 404s.
     */
    public function edit(Request $request, Client $client)
    {
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

    /**
     * @return array{name:string,email:string,notes:?string}
     */
    private function validatedClientData(Request $request): array
    {
        /** @var array{name:string,email:string,notes:?string} $validated */
        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return $validated;
    }

    private function validatedReturnTo(mixed $returnTo): ?string
    {
        if (! is_string($returnTo)) {
            return null;
        }

        $candidate = trim($returnTo);
        if ($candidate === '' || strlen($candidate) > 2048) {
            return null;
        }

        if (! str_starts_with($candidate, '/') || str_starts_with($candidate, '//')) {
            return null;
        }

        $candidatePath = parse_url($candidate, PHP_URL_PATH);
        $invoiceCreatePath = parse_url(route('invoices.create', [], false), PHP_URL_PATH);

        if (! is_string($candidatePath) || ! is_string($invoiceCreatePath)) {
            return null;
        }

        return $candidatePath === $invoiceCreatePath ? $candidate : null;
    }
}
