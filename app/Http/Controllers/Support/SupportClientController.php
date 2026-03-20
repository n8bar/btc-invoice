<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SupportClientController extends Controller
{
    public function index(Request $request, User $owner): View
    {
        $this->authorizeSupportOwner($request, $owner);

        $clients = Client::query()
            ->where('user_id', $owner->id)
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('support.clients.index', [
            'owner' => $owner,
            'clients' => $clients,
            'supportAccessExpiresAt' => $owner->support_access_expires_at,
        ]);
    }

    public function show(Request $request, User $owner, Client $client): View
    {
        $this->authorizeSupportOwner($request, $owner);
        abort_unless($client->user_id === $owner->id, 404);

        $recentInvoices = Invoice::query()
            ->ownedBy($owner)
            ->where('client_id', $client->id)
            ->latest('invoice_date')
            ->latest('id')
            ->limit(10)
            ->get();

        return view('support.clients.show', [
            'owner' => $owner,
            'client' => $client,
            'recentInvoices' => $recentInvoices,
            'supportAccessExpiresAt' => $owner->support_access_expires_at,
        ]);
    }

    private function authorizeSupportOwner(Request $request, User $owner): void
    {
        abort_unless($request->user()?->isSupportAgent(), 403);
        abort_unless($owner->hasActiveSupportAccessGrant(), 403);
    }
}
