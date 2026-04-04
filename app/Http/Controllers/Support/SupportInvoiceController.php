<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SupportInvoiceController extends Controller
{
    public function index(Request $request, User $issuer): View
    {
        $this->authorizeSupportIssuer($request, $issuer);

        $invoices = Invoice::query()
            ->ownedBy($issuer)
            ->with('client')
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('support.invoices.index', [
            'issuer' => $issuer,
            'invoices' => $invoices,
            'supportAccessExpiresAt' => $issuer->support_access_expires_at,
        ]);
    }

    public function show(Request $request, User $issuer, Invoice $invoice): View
    {
        $this->authorizeSupportIssuer($request, $issuer);
        abort_unless($invoice->user_id === $issuer->id, 404);

        $invoice = $invoice->load([
            'client',
            'payments' => fn ($query) => $query
                ->with('sourceInvoice:id,user_id,number')
                ->orderBy('detected_at')
                ->orderBy('id'),
            'sourcePayments' => fn ($query) => $query
                ->with('accountingInvoice:id,user_id,number')
                ->orderBy('detected_at')
                ->orderBy('id'),
            'deliveries' => fn ($query) => $query->latest('id')->limit(10),
        ]);

        return view('support.invoices.show', [
            'issuer' => $issuer,
            'invoice' => $invoice,
            'paymentHistory' => $invoice->paymentHistory(),
            'supportAccessExpiresAt' => $issuer->support_access_expires_at,
        ]);
    }

    private function authorizeSupportIssuer(Request $request, User $issuer): void
    {
        abort_unless($request->user()?->isSupportAgent(), 403);
        abort_unless($issuer->hasActiveSupportAccessGrant(), 403);
    }
}
