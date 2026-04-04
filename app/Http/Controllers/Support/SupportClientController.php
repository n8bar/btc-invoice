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
    public function index(Request $request, User $issuer): View
    {
        $this->authorizeSupportIssuer($request, $issuer);

        $clients = Client::query()
            ->where('user_id', $issuer->id)
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('support.clients.index', [
            'issuer' => $issuer,
            'clients' => $clients,
            'supportAccessExpiresAt' => $issuer->support_access_expires_at,
        ]);
    }

    public function show(Request $request, User $issuer, Client $client): View
    {
        $this->authorizeSupportIssuer($request, $issuer);
        abort_unless($client->user_id === $issuer->id, 404);

        $recentInvoices = Invoice::query()
            ->ownedBy($issuer)
            ->where('client_id', $client->id)
            ->latest('invoice_date')
            ->latest('id')
            ->limit(10)
            ->get();

        return view('support.clients.show', [
            'issuer' => $issuer,
            'client' => $client,
            'recentInvoices' => $recentInvoices,
            'supportAccessExpiresAt' => $issuer->support_access_expires_at,
        ]);
    }

    private function authorizeSupportIssuer(Request $request, User $issuer): void
    {
        abort_unless($request->user()?->isSupportAgent(), 403);
        abort_unless($issuer->hasActiveSupportAccessGrant(), 403);
    }
}
