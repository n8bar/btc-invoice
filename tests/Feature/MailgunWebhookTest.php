<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MailgunWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $signingKey = 'test-signing-key';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mailgun.webhook_signing_key' => $this->signingKey]);
    }

    private function makeSignature(string $timestamp, string $token): array
    {
        return [
            'timestamp' => $timestamp,
            'token' => $token,
            'signature' => hash_hmac('sha256', $timestamp . $token, $this->signingKey),
        ];
    }

    private function makeDelivery(string $messageId, string $status = 'sent'): InvoiceDelivery
    {
        $user = User::factory()->create();
        $client = Client::create([
            'user_id' => $user->id,
            'name' => 'Test Client',
            'email' => 'client@example.com',
        ]);
        $invoice = Invoice::create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'number' => 'INV-TEST-' . uniqid(),
            'amount_usd' => 100,
            'btc_rate' => 50_000,
            'amount_btc' => 0.002,
            'payment_address' => 'tb1qtest',
            'status' => 'sent',
            'invoice_date' => now()->toDateString(),
        ]);

        return InvoiceDelivery::create([
            'invoice_id' => $invoice->id,
            'user_id' => $user->id,
            'type' => 'send',
            'status' => $status,
            'recipient' => 'client@example.com',
            'provider_message_id' => $messageId,
        ]);
    }

    public function test_rejects_request_with_invalid_signature(): void
    {
        $response = $this->postJson('/webhooks/mailgun', [
            'signature' => [
                'timestamp' => '1234567890',
                'token' => 'abc123',
                'signature' => 'invalidsignature',
            ],
            'event-data' => ['event' => 'delivered'],
        ]);

        $response->assertStatus(403);
    }

    public function test_delivered_event_marks_delivery_sent(): void
    {
        $messageId = '<test-message-id@mailgun.org>';
        $delivery = $this->makeDelivery($messageId, 'sending');
        $timestamp = (string) now()->timestamp;
        $token = 'testtoken123';

        $response = $this->postJson('/webhooks/mailgun', [
            'signature' => $this->makeSignature($timestamp, $token),
            'event-data' => [
                'event' => 'delivered',
                'message' => [
                    'headers' => [
                        'message-id' => $messageId,
                    ],
                ],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('invoice_deliveries', [
            'id' => $delivery->id,
            'status' => 'sent',
        ]);
    }

    public function test_failed_event_marks_delivery_failed_and_logs_warning(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($channel) => $channel === 'invoice_delivery.mailgun_failed');

        $messageId = '<test-failed-message@mailgun.org>';
        $delivery = $this->makeDelivery($messageId, 'sending');
        $timestamp = (string) now()->timestamp;
        $token = 'testtoken456';

        $response = $this->postJson('/webhooks/mailgun', [
            'signature' => $this->makeSignature($timestamp, $token),
            'event-data' => [
                'event' => 'failed',
                'message' => [
                    'headers' => [
                        'message-id' => $messageId,
                    ],
                ],
                'delivery-status' => [
                    'code' => 550,
                    'description' => 'No such mailbox.',
                ],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('invoice_deliveries', [
            'id' => $delivery->id,
            'status' => 'failed',
            'error_code' => '550',
            'error_message' => 'No such mailbox.',
        ]);
    }

    public function test_unknown_message_id_returns_200_no_op(): void
    {
        $timestamp = (string) now()->timestamp;
        $token = 'testtoken789';

        $response = $this->postJson('/webhooks/mailgun', [
            'signature' => $this->makeSignature($timestamp, $token),
            'event-data' => [
                'event' => 'delivered',
                'message' => [
                    'headers' => [
                        'message-id' => '<unknown@mailgun.org>',
                    ],
                ],
            ],
        ]);

        $response->assertStatus(200);
    }

    public function test_bounced_event_marks_delivery_failed(): void
    {
        $messageId = '<test-bounced-message@mailgun.org>';
        $delivery = $this->makeDelivery($messageId, 'sending');
        $timestamp = (string) now()->timestamp;
        $token = 'testtokenabc';

        $response = $this->postJson('/webhooks/mailgun', [
            'signature' => $this->makeSignature($timestamp, $token),
            'event-data' => [
                'event' => 'bounced',
                'message' => [
                    'headers' => [
                        'message-id' => $messageId,
                    ],
                ],
                'delivery-status' => [
                    'code' => 421,
                    'description' => 'Domain not found.',
                ],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('invoice_deliveries', [
            'id' => $delivery->id,
            'status' => 'failed',
        ]);
    }
}
