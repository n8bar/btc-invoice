<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function supportAgent(): User
    {
        $agent = User::factory()->create(['email' => 'agent@support.test']);
        config(['support.agent_emails' => ['agent@support.test']]);
        return $agent;
    }

    private function makeInvoice(User $issuer): Invoice
    {
        $client = Client::create([
            'user_id' => $issuer->id,
            'name'    => 'Test Client',
            'email'   => 'client@example.com',
        ]);

        return Invoice::create([
            'user_id'         => $issuer->id,
            'client_id'       => $client->id,
            'number'          => 'INV-MON-001',
            'amount_usd'      => 100.00,
            'btc_rate'        => 50000.00,
            'amount_btc'      => 0.002,
            'status'          => 'sent',
            'payment_address' => 'tb1qmonitoringtest0000000000000000',
            'invoice_date'    => now()->toDateString(),
            'due_date'        => now()->addWeek()->toDateString(),
        ]);
    }

    public function test_monitoring_panel_shows_queue_depth(): void
    {
        $agent = $this->supportAgent();
        $issuer = User::factory()->create();
        $invoice = $this->makeInvoice($issuer);

        $invoice->deliveries()->create(['type' => 'send', 'status' => 'queued',  'recipient' => 'a@b.com', 'user_id' => $issuer->id]);
        $invoice->deliveries()->create(['type' => 'send', 'status' => 'sending', 'recipient' => 'b@b.com', 'user_id' => $issuer->id]);
        $invoice->deliveries()->create(['type' => 'send', 'status' => 'sent',    'recipient' => 'c@b.com', 'user_id' => $issuer->id]);

        $this->actingAs($agent)
            ->get(route('support.dashboard'))
            ->assertOk()
            ->assertSee('deliveries queued or sending');
    }

    public function test_queue_depth_is_zero_when_no_active_deliveries(): void
    {
        $agent = $this->supportAgent();

        $this->actingAs($agent)
            ->get(route('support.dashboard'))
            ->assertOk()
            ->assertSee('0');
    }

    public function test_monitoring_panel_shows_recent_failures(): void
    {
        $agent = $this->supportAgent();
        $issuer = User::factory()->create();
        $invoice = $this->makeInvoice($issuer);

        $invoice->deliveries()->create([
            'type'          => 'issuer_paid_notice',
            'status'        => 'failed',
            'recipient'     => 'fail@example.com',
            'error_message' => 'SMTP connection refused',
            'user_id'       => $issuer->id,
            'updated_at'    => now()->subHours(2),
        ]);

        $this->actingAs($agent)
            ->get(route('support.dashboard'))
            ->assertOk()
            ->assertSee('issuer_paid_notice')
            ->assertSee('fail@example.com')
            ->assertSee('SMTP connection refused');
    }

    public function test_failures_older_than_24_hours_are_excluded(): void
    {
        $agent = $this->supportAgent();
        $issuer = User::factory()->create();
        $invoice = $this->makeInvoice($issuer);

        $delivery = $invoice->deliveries()->create([
            'type'      => 'send',
            'status'    => 'failed',
            'recipient' => 'old@example.com',
            'user_id'   => $issuer->id,
        ]);
        $delivery->timestamps = false;
        $delivery->forceFill(['updated_at' => now()->subHours(25)])->save();

        $response = $this->actingAs($agent)->get(route('support.dashboard'))->assertOk();
        $this->assertStringNotContainsString('old@example.com', $response->getContent());
    }

    public function test_watcher_healthy_shows_recent_label(): void
    {
        $agent = $this->supportAgent();
        $issuer = User::factory()->create();
        $invoice = $this->makeInvoice($issuer);

        InvoicePayment::create([
            'invoice_id'    => $invoice->id,
            'txid'          => 'tx-mon-health',
            'sats_received' => 10000,
            'is_adjustment' => false,
            'detected_at'   => now()->subMinutes(10),
            'confirmed_at'  => now()->subMinutes(9),
            'usd_rate'      => 50000,
            'fiat_amount'   => 5.00,
        ]);

        $this->actingAs($agent)
            ->get(route('support.dashboard'))
            ->assertOk()
            ->assertSee('recent');
    }

    public function test_watcher_staleness_flag_triggers_past_threshold(): void
    {
        $agent = $this->supportAgent();
        config(['support.watcher_stale_minutes' => 30]);

        $issuer = User::factory()->create();
        $invoice = $this->makeInvoice($issuer);

        InvoicePayment::create([
            'invoice_id'    => $invoice->id,
            'txid'          => 'tx-mon-stale',
            'sats_received' => 10000,
            'is_adjustment' => false,
            'detected_at'   => now()->subMinutes(60),
            'confirmed_at'  => now()->subMinutes(59),
            'usd_rate'      => 50000,
            'fiat_amount'   => 5.00,
        ]);

        $this->actingAs($agent)
            ->get(route('support.dashboard'))
            ->assertOk()
            ->assertSee('No activity in over 30 minutes');
    }

    public function test_adjustment_payments_excluded_from_watcher_health(): void
    {
        $agent = $this->supportAgent();
        config(['support.watcher_stale_minutes' => 30]);

        $issuer = User::factory()->create();
        $invoice = $this->makeInvoice($issuer);

        // Only an adjustment — should not count as watcher activity
        InvoicePayment::create([
            'invoice_id'    => $invoice->id,
            'txid'          => 'tx-adj',
            'sats_received' => 10000,
            'is_adjustment' => true,
            'detected_at'   => now()->subMinutes(5),
            'confirmed_at'  => now()->subMinutes(4),
            'usd_rate'      => 50000,
            'fiat_amount'   => 5.00,
        ]);

        $this->actingAs($agent)
            ->get(route('support.dashboard'))
            ->assertOk()
            ->assertSee('No on-chain payments recorded yet');
    }

    public function test_healthy_state_renders_all_three_indicators_cleanly(): void
    {
        $agent = $this->supportAgent();
        $issuer = User::factory()->create();
        $invoice = $this->makeInvoice($issuer);

        InvoicePayment::create([
            'invoice_id'    => $invoice->id,
            'txid'          => 'tx-mon-ok',
            'sats_received' => 5000,
            'is_adjustment' => false,
            'detected_at'   => now()->subMinutes(5),
            'confirmed_at'  => now()->subMinutes(4),
            'usd_rate'      => 50000,
            'fiat_amount'   => 2.50,
        ]);

        $this->actingAs($agent)
            ->get(route('support.dashboard'))
            ->assertOk()
            ->assertSee('Service Health')
            ->assertSee('no failures')
            ->assertSee('recent');
    }

    public function test_no_payments_recorded_yet_renders_cleanly(): void
    {
        $agent = $this->supportAgent();

        $this->actingAs($agent)
            ->get(route('support.dashboard'))
            ->assertOk()
            ->assertSee('No on-chain payments recorded yet');
    }
}
