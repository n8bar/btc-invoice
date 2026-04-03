<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\InvoiceDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MailgunWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        if (! $this->verifySignature($request)) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $eventData = $request->input('event-data', []);
        $event = $eventData['event'] ?? null;
        $messageId = $eventData['message']['headers']['message-id'] ?? null;

        if (! $messageId) {
            return response()->json(['ok' => true]);
        }

        $delivery = InvoiceDelivery::where('provider_message_id', $messageId)->first();

        if (! $delivery) {
            return response()->json(['ok' => true]);
        }

        match ($event) {
            'delivered' => $delivery->update([
                'status' => 'sent',
                'sent_at' => now(),
                'error_code' => null,
                'error_message' => null,
            ]),
            'failed', 'bounced' => $this->handleFailure($delivery, $eventData),
            default => null,
        };

        return response()->json(['ok' => true]);
    }

    private function verifySignature(Request $request): bool
    {
        $signature = $request->input('signature', []);
        $timestamp = (string) ($signature['timestamp'] ?? '');
        $token = (string) ($signature['token'] ?? '');
        $provided = (string) ($signature['signature'] ?? '');
        $signingKey = config('services.mailgun.webhook_signing_key');

        if (! $signingKey || ! $timestamp || ! $token || ! $provided) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . $token, $signingKey);

        return hash_equals($expected, $provided);
    }

    private function handleFailure(InvoiceDelivery $delivery, array $eventData): void
    {
        $status = $eventData['delivery-status'] ?? [];
        $reason = $status['description'] ?? $status['message'] ?? 'Delivery failed.';
        $code = (string) ($status['code'] ?? '');

        $delivery->update([
            'status' => 'failed',
            'error_code' => $code ?: null,
            'error_message' => $reason,
        ]);

        Log::warning('invoice_delivery.mailgun_failed', [
            'delivery_id' => $delivery->id,
            'invoice_id' => $delivery->invoice_id,
            'type' => $delivery->type,
            'recipient' => $delivery->recipient,
            'reason' => $reason,
            'code' => $code ?: null,
        ]);
    }
}
