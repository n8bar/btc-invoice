<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SupportInvoiceController extends Controller
{
    public function index(Request $request, User $owner): View
    {
        $this->authorizeSupportOwner($request, $owner);

        $invoices = Invoice::query()
            ->ownedBy($owner)
            ->with('client')
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('support.invoices.index', [
            'owner' => $owner,
            'invoices' => $invoices,
            'supportAccessExpiresAt' => $owner->support_access_expires_at,
        ]);
    }

    public function show(Request $request, User $owner, Invoice $invoice): View
    {
        $this->authorizeSupportOwner($request, $owner);
        abort_unless($invoice->user_id === $owner->id, 404);

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
            'owner' => $owner,
            'invoice' => $invoice,
            'paymentHistory' => $invoice->paymentHistory(),
            'supportAccessExpiresAt' => $owner->support_access_expires_at,
        ]);
    }

    private function authorizeSupportOwner(Request $request, User $owner): void
    {
        abort_unless($request->user()?->isSupportAgent(), 403);
        abort_unless($owner->hasActiveSupportAccessGrant(), 403);
    }
}
