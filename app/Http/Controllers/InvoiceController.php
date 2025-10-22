<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{
    public function __construct()
    {
        // no-op; routes are already wrapped in auth middleware
        // $this->middleware('auth');
    }

    /**
     * List invoices for the authenticated user (optionally filter by status).
     */
    public function index(Request $request)
    {
        $q = Invoice::where('user_id', $request->user()->id)
            ->with(['client:id,name,email'])
            ->latest();

        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }

        return response()->json($q->paginate(15));
    }

    /**
     * Store a new invoice:
     * - validates client ownership
     * - locks BTC rate and amount at creation
     * - generates a unique invoice number after insert
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id'   => 'required|integer|exists:clients,id',
            'description' => 'nullable|string',
            'amount_usd'  => 'required|numeric|min:0.01',
            'due_date'    => 'nullable|date',
            'btc_address' => 'nullable|string|max:128',
        ]);

        // Ensure the client belongs to the user
        $client = Client::where('id', $data['client_id'])
            ->where('user_id', $request->user()->id)
            ->first();

        abort_if(!$client, 403, 'Client not found or not owned.');

        // Determine BTC address (env default for MVP if not provided)
        //$btcAddress = $data['btc_address'] ?? config('app.btc_address', env('BTC_ADDRESS')) ?? '';
        //let's lose the env-wide fallback. and have a per-user default, still overridable per invoice
        $btcAddress = $data['btc_address'] ?? ($request->user()->btc_address ?? '');

        // Fetch price and compute BTC amount (gracefully fallback if external call fails)
        [$rate, $amountBtc] = $this->lockRateAndAmount((float)$data['amount_usd']);

        // Insert first to obtain ID, then set the final invoice number atomically
        $invoice = null;

        DB::transaction(function () use ($request, $data, $client, $btcAddress, $rate, $amountBtc, &$invoice) {
            $invoice = Invoice::create([
                'user_id'     => $request->user()->id,
                'client_id'   => $client->id,
                'number'      => 'PENDING',
                'description' => $data['description'] ?? null,
                'amount_usd'  => $data['amount_usd'],
                'btc_rate'    => $rate,
                'amount_btc'  => $amountBtc,
                'btc_address' => $btcAddress ?: 'SET-BTC-ADDRESS',
                'status'      => 'pending',
                'due_date'    => $data['due_date'] ?? null,
            ]);

            // Use the auto-increment ID to ensure a globally unique number
            $invoice->number = sprintf('INV-%06d', $invoice->id);
            $invoice->save();
        });

        return response()->json($invoice->fresh(['client:id,name,email']), 201);
    }

    /**
     * Show one invoice owned by the authenticated user.
     */
    public function show(Request $request, Invoice $invoice)
    {
        $this->authorizeOwnership($request, $invoice);
        return response()->json($invoice->load(['client:id,name,email']));
    }

    /**
     * Update limited fields of an owned invoice.
     * - allows changing description, status (to paid/void), txid, due_date, btc_address
     * - does NOT change monetary fields or client_id in the MVP
     */
    public function update(Request $request, Invoice $invoice)
    {
        $this->authorizeOwnership($request, $invoice);

        $data = $request->validate([
            'description' => 'nullable|string',
            'status'      => 'nullable|in:pending,paid,void',
            'txid'        => 'nullable|string|max:128',
            'due_date'    => 'nullable|date',
            'btc_address' => 'nullable|string|max:128',
        ]);

        if (($data['status'] ?? null) === 'paid' && is_null($invoice->paid_at)) {
            $invoice->paid_at = now();
        }

        $invoice->fill($data)->save();

        return response()->json($invoice->fresh(['client:id,name,email']));
    }

    /**
     * Soft-delete an owned invoice.
     */
    public function destroy(Request $request, Invoice $invoice)
    {
        $this->authorizeOwnership($request, $invoice);
        $invoice->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * Unused in MVP UI. Keep endpoints defined for resource controller.
     */
    public function create()
    {
        return response()->noContent(204);
    }

    public function edit(Invoice $invoice)
    {
        return response()->noContent(204);
    }

    /**
     * Guard: ensure the model belongs to the current user.
     */
    private function authorizeOwnership(Request $request, Invoice $invoice): void
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);
    }

    /**
     * Fetch the current BTC/USD spot rate and compute amount BTC for a USD amount.
     * Returns [rateUsdPerBtc, amountBtc]. On failure, uses last known rate or 0.
     */
    private function lockRateAndAmount(float $amountUsd): array
    {
        $rate = $this->fetchBtcUsdRate();

        if ($rate <= 0) {
            // Fallback: try last known rate from an existing invoice
            $rate = (float) (Invoice::orderByDesc('id')->value('btc_rate') ?? 0);
        }

        if ($rate <= 0) {
            // As a last resort, avoid division by zero; let user edit later.
            $rate = 0.0;
            $amountBtc = 0.0;
        } else {
            $amountBtc = round($amountUsd / $rate, 8);
        }

        return [$rate, $amountBtc];
    }

    /**
     * Attempts multiple public endpoints to get USD per BTC.
     */
    private function fetchBtcUsdRate(): float
    {
        try {
            // Coinbase spot
            $r = Http::timeout(5)->acceptJson()->get('https://api.coinbase.com/v2/prices/BTC-USD/spot');
            if ($r->ok()) {
                $amount = (float) data_get($r->json(), 'data.amount', 0);
                if ($amount > 0) return $amount;
            }
        } catch (\Throwable $e) {
            Log::warning('Coinbase rate failed: '.$e->getMessage());
        }

        try {
            // Coindesk
            $r = Http::timeout(5)->acceptJson()->get('https://api.coindesk.com/v1/bpi/currentprice/USD.json');
            if ($r->ok()) {
                $amount = (float) data_get($r->json(), 'bpi.USD.rate_float', 0);
                if ($amount > 0) return $amount;
            }
        } catch (\Throwable $e) {
            Log::warning('Coindesk rate failed: '.$e->getMessage());
        }

        return 0.0;
    }
}
