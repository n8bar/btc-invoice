<?php

namespace App\Http\Controllers;

use App\Jobs\DeliverInvoiceMail;
use App\Models\Invoice;
use App\Models\InvoiceDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvoiceDeliveryController extends Controller
{
    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        $request->validate([
            'message' => ['nullable','string','max:1000'],
            'cc_self' => ['nullable','boolean'],
        ]);

        if (!$invoice->client || empty($invoice->client->email)) {
            return back()->with('status', 'Add a client email before sending.');
        }

        if (!$invoice->public_enabled || !$invoice->public_token) {
            return back()->with('status', 'Enable the public share link before sending.');
        }

        $recipient = $invoice->client->email;
        $cc = $request->boolean('cc_self') ? $invoice->user->email : null;

        $delivery = InvoiceDelivery::create([
            'invoice_id' => $invoice->id,
            'user_id' => $invoice->user_id,
            'type' => 'send',
            'status' => 'queued',
            'recipient' => $recipient,
            'cc' => $cc,
            'message' => $request->input('message'),
            'dispatched_at' => now(),
        ]);

        DeliverInvoiceMail::dispatch($delivery);

        return back()->with('status', 'Invoice email queued.');
    }
}
