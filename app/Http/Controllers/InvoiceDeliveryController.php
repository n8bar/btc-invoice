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

    public function storeReceipt(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        if (!$invoice->client || empty($invoice->client->email)) {
            return back()->with('status', 'Add a client email before sending a receipt.');
        }

        if ($invoice->status !== 'paid') {
            return back()->with('status', 'Only paid invoices can send a receipt.');
        }

        if ($invoice->receiptIsBlockedByCorrectionState()) {
            return back()->with('error', 'Resolve the ignored or reattributed payment before sending the receipt.');
        }

        $delivery = $this->deliveries->queue(
            $invoice,
            'receipt',
            $invoice->client->email
        );

        $statusMessage = $delivery->status === 'queued'
            ? 'Receipt queued.'
            : ($delivery->error_message ?: 'Receipt skipped.');

        if ($request->boolean('getting_started')) {
            return redirect()
                ->route('getting-started.start')
                ->with('status', $statusMessage);
        }

        return back()->with('status', $statusMessage);
    }

    public function resendReceipt(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        if (! $invoice->client || empty($invoice->client->email)) {
            return back()->with('status', 'No client email on file.');
        }

        if ($invoice->status !== 'paid') {
            return back()->with('status', 'Only paid invoices can send a receipt.');
        }

        $hasSentReceipt = $invoice->deliveries()
            ->where('type', 'receipt')
            ->whereIn('status', ['queued', 'sending', 'sent'])
            ->exists();

        if (! $hasSentReceipt) {
            return back()->with('status', 'No receipt has been sent yet.');
        }

        $delivery = $this->deliveries->queueResend($invoice, 'receipt', $invoice->client->email);

        if ($delivery === null) {
            $cooldown = $this->deliveries->manualSendCooldownMinutes();
            return back()->with('status', "Receipt was sent recently. Please wait {$cooldown} minutes before resending.");
        }

        return back()->with('status', 'Receipt resend queued.');
    }
}
