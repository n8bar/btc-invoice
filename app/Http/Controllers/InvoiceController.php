<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

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
    public function index(\Illuminate\Http\Request $request)
    {
        $invoices = Invoice::with('client')
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('invoices.index', compact('invoices'));
    }

    /**
     * Store a new invoice:
     * - validates client ownership
     * - locks BTC rate and amount at creation
     * - generates a unique invoice number after insert
     */
    public function store(Request $request)
    {
        $userId = $request->user()->id;

        $data = $request->validate([
            'client_id'   => [
                'required','integer',
                Rule::exists('clients','id')->where(fn($q) => $q->where('user_id', $userId)),
            ],
            'number'      => [
                'required','string','max:32',
                Rule::unique('invoices','number')->where(fn($q) => $q->where('user_id', $userId)),
            ],
            'description' => ['nullable','string','max:2000'],
            'amount_usd'  => ['required','numeric','min:0.01'],
            'btc_rate'    => ['nullable','numeric','min:0'],         // USD per BTC
            'amount_btc'  => ['nullable','numeric','min:0'],
            'btc_address' => ['nullable','string','max:128'],
            'status'      => ['nullable','in:draft,sent,paid,void'],
            'due_date'    => ['nullable','date'],
            'paid_at'     => ['nullable','date'],
            'txid'        => ['nullable','string','max:128'],
        ]);

        // If rate is provided and BTC amount omitted, compute it.
        if (empty($data['amount_btc']) && !empty($data['btc_rate']) && $data['btc_rate'] > 0) {
            $data['amount_btc'] = round($data['amount_usd'] / $data['btc_rate'], 8);
        }

        $invoice = Invoice::create($data + ['user_id' => $userId]);

        if ($request->wantsJson()) {
            return response()->json($invoice->load('client'), 201);
        }

        return redirect()->route('invoices.index')->with('status', 'Invoice created.');
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
    public function update(\Illuminate\Http\Request $request, \App\Models\Invoice $invoice)
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);
        $userId = $request->user()->id;

        $data = $request->validate([
            'client_id'   => ['required','integer', Rule::exists('clients','id')->where(fn($q)=>$q->where('user_id',$userId))],
            'number'      => ['required','string','max:32', Rule::unique('invoices','number')->where(fn($q)=>$q->where('user_id',$userId))->ignore($invoice->id)],
            'description' => ['nullable','string','max:2000'],
            'amount_usd'  => ['required','numeric','min:0.01'],
            'btc_rate'    => ['nullable','numeric','min:0'],
            'amount_btc'  => ['nullable','numeric','min:0'],
            'btc_address' => ['nullable','string','max:128'],
            'status'      => ['nullable','in:draft,sent,paid,void'],
            'due_date'    => ['nullable','date'],
            'paid_at'     => ['nullable','date'],
            'txid'        => ['nullable','string','max:128'],
        ]);

        if (empty($data['amount_btc']) && !empty($data['btc_rate']) && $data['btc_rate'] > 0) {
            $data['amount_btc'] = round($data['amount_usd'] / $data['btc_rate'], 8);
        }

        $invoice->update($data);

        if ($request->wantsJson()) return response()->json($invoice->fresh('client'));
        return redirect()->route('invoices.index')->with('status','Invoice updated.');
    }


    /**
     * Soft-delete an owned invoice.
     */
    public function destroy(\Illuminate\Http\Request $request, \App\Models\Invoice $invoice)
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);

        $invoice->delete(); // soft-delete

        if ($request->wantsJson()) {
            return response()->json(['deleted' => true]);
        }

        return redirect()->route('invoices.index')->with('status', 'Invoice deleted.');
    }


    public function create(\Illuminate\Http\Request $request)
    {
        $clients = Client::where('user_id', $request->user()->id)
            ->orderBy('name')->get(['id','name']);

        return view('invoices.create', compact('clients'));
    }

    public function edit(\Illuminate\Http\Request $request, \App\Models\Invoice $invoice)
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);

        $clients = Client::where('user_id', $request->user()->id)
            ->orderBy('name')->get(['id','name']);

        return view('invoices.edit', compact('invoice','clients'));
    }

    public function trash(\Illuminate\Http\Request $request)
    {
        $invoices = \App\Models\Invoice::onlyTrashed()
            ->with('client')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('deleted_at')
            ->paginate(15)
            ->withQueryString();

        return view('invoices.trash', compact('invoices'));
    }

    public function restore(\Illuminate\Http\Request $request, int $invoiceId)
    {
        $invoice = \App\Models\Invoice::withTrashed()->findOrFail($invoiceId);
        abort_unless($invoice->user_id === $request->user()->id, 403);

        $invoice->restore();

        if ($request->wantsJson()) return response()->json($invoice->fresh('client'));
        return redirect()->route('invoices.trash')->with('status', 'Invoice restored.');
    }

    public function forceDestroy(\Illuminate\Http\Request $request, int $invoiceId)
    {
        $invoice = \App\Models\Invoice::withTrashed()->findOrFail($invoiceId);
        abort_unless($invoice->user_id === $request->user()->id, 403);

        $invoice->forceDelete();

        if ($request->wantsJson()) return response()->json(['deleted' => true]);
        return redirect()->route('invoices.trash')->with('status', 'Invoice permanently deleted.');
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
