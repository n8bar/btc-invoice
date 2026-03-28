<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\InvoiceDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvoiceDeliveryController extends Controller
{
    public function __construct(private readonly InvoiceDeliveryService $deliveries)
    {
    }

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

        $delivery = $this->deliveries->queue(
            $invoice,
            'send',
            $recipient,
            $cc,
            $validated['message'] ?? null
        );

        if ($delivery->status === 'queued') {
            $invoice->forceFill([
                'delivery_message_draft' => null,
            ])->save();
        }

        $statusMessage = $delivery->status === 'queued'
            ? 'Invoice email queued.'
            : ($delivery->error_message ?: 'Invoice email skipped.');

        if ($request->boolean('getting_started')) {
            return redirect()
                ->route('getting-started.start')
                ->with('status', $statusMessage);
        }

        return back()->with('status', $statusMessage);
    }
}
