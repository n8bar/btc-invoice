<?php

namespace App\Http\Controllers;

use App\Jobs\DeliverInvoiceMail;
use App\Models\Invoice;
use App\Models\InvoiceDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvoiceDeliveryController extends Controller
{
    public function updateDraft(Request $request, Invoice $invoice): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $invoice);

        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $message = $validated['message'] ?? null;
        $message = is_string($message) ? trim($message) : null;

        $invoice->forceFill([
            'delivery_message_draft' => $message !== '' ? $message : null,
        ])->save();

        if ($request->expectsJson()) {
            return response()->json([
                'saved' => true,
                'message' => $invoice->delivery_message_draft,
            ]);
        }

        return back()->with('status', 'Delivery note draft saved.');
    }

    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        $validated = $request->validate([
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
            'message' => $validated['message'] ?? null,
            'dispatched_at' => now(),
        ]);

        DeliverInvoiceMail::dispatch($delivery);

        $invoice->forceFill([
            'delivery_message_draft' => null,
        ])->save();

        if ($request->boolean('getting_started')) {
            return redirect()
                ->route('getting-started.start')
                ->with('status', 'Invoice email queued.');
        }

        return back()->with('status', 'Invoice email queued.');
    }
}
