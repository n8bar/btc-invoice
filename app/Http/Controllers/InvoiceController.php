<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use App\Services\BtcRate;
use App\Services\HdWallet;

class InvoiceController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Invoice::class, 'invoice');
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

        return view('invoices.index', [
            'invoices' => $invoices,
            'showInvoiceIds' => $request->user()->show_invoice_ids,
        ]);
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

        if (!$request->filled('number')) {
            $request->merge(['number' => \App\Models\Invoice::nextNumberForUser($userId)]);
        }

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
            'status'      => ['nullable','in:draft,sent,paid,void'],
            'due_date'    => ['nullable','date'],
            'paid_at'     => ['nullable','date'],
            'txid'        => ['nullable','string','max:128'],
            'invoice_date' => ['required','date'],
        ]);

        // If rate is provided and BTC amount omitted, compute it.
        if (empty($data['amount_btc']) && !empty($data['btc_rate']) && $data['btc_rate'] > 0) {
            $data['amount_btc'] = round($data['amount_usd'] / $data['btc_rate'], 8);
        }

        $wallet = $request->user()->walletSetting;
        if (!$wallet) {
            return redirect()->route('wallet.settings.edit')
                ->with('status', 'Connect a wallet before creating invoices.');
        }

        $invoice = DB::transaction(function () use ($data, $wallet, $userId) {
            $address = app(HdWallet::class)->deriveAddress(
                $wallet->bip84_xpub,
                $wallet->next_derivation_index,
                $wallet->network
            );

            $invoice = Invoice::create($data + [
                'user_id' => $userId,
                'payment_address' => $address,
                'derivation_index' => $wallet->next_derivation_index,
            ]);

            $wallet->increment('next_derivation_index');

            return $invoice;
        });

        if ($request->wantsJson()) {
            return response()->json($invoice->load('client'), 201);
        }

        return redirect()->route('invoices.index')->with('status', 'Invoice created.');
    }

    /**
     * Show one invoice owned by the authenticated user.
     */
    public function show(\Illuminate\Http\Request $request, \App\Models\Invoice $invoice)
    {
        $rate = BtcRate::current();

        if ($rate && isset($rate['as_of']) && !$rate['as_of'] instanceof Carbon) {
            $rate['as_of'] = Carbon::parse($rate['as_of']);
        }

        $invoice = $invoice->load([
            'client',
            'payments' => fn ($query) => $query->orderBy('detected_at')->orderBy('id'),
            'deliveries' => fn ($query) => $query->latest('id'),
        ]);
        $display = $this->formatInvoiceDisplay($invoice, $rate);
        $summary = $this->buildPaymentSummary($invoice, $rate, $display['computedBtc'] ?? null);

        $btcForUri = $summary['outstanding_btc_float'];
        $display['displayBitcoinUri'] = $invoice->bitcoinUriForAmount(
            $btcForUri,
            allowFallback: $btcForUri !== null
        );

        return view('invoices.show', [
            'invoice'           => $invoice,
            'rate'              => $rate,
            'paymentSummary'    => $summary,
        ] + $display);
    }


    /**
     * Update limited fields of an owned invoice.
     * - allows changing description, status (to paid/void), txid, due_date
     * - does NOT change monetary fields or client_id in the MVP
     */
    public function update(\Illuminate\Http\Request $request, \App\Models\Invoice $invoice)
    {
        $userId = $request->user()->id;

        $data = $request->validate([
            'client_id'   => ['required','integer', Rule::exists('clients','id')->where(fn($q)=>$q->where('user_id',$userId))],
            'number'      => ['required','string','max:32', Rule::unique('invoices','number')->where(fn($q)=>$q->where('user_id',$userId))->ignore($invoice->id)],
            'description' => ['nullable','string','max:2000'],
            'amount_usd'  => ['required','numeric','min:0.01'],
            'btc_rate'    => ['nullable','numeric','min:0'],
            'amount_btc'  => ['nullable','numeric','min:0'],
            'status'      => ['nullable','in:draft,sent,paid,void'],
            'due_date'    => ['nullable','date'],
            'paid_at'     => ['nullable','date'],
            'txid'        => ['nullable','string','max:128'],
            'invoice_date'=> ['required','date'],
        ]);

        if ($invoice->public_enabled) {
            // Fields that affect what recipients see
            $locked = ['client_id','number','description','amount_usd','invoice_date','due_date'];
            foreach ($locked as $f) {
                // Compare only if field is present in payload and changed
                if (array_key_exists($f, $data) && $data[$f] != $invoice->{$f}) {
                    return back()
                        ->with('status', 'Disable the public link to edit invoice details.')
                        ->withInput();
                }
            }
        }
        // allowed to change even when public: status/paid_at/txid/etc.


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
        $invoice->delete(); // soft-delete

        if ($request->wantsJson()) {
            return response()->json(['deleted' => true]);
        }

        return redirect()->route('invoices.index')->with('status', 'Invoice deleted.');
    }


    public function create(\Illuminate\Http\Request $request)
    {
        if (!$request->user()->walletSetting) {
            return redirect()->route('wallet.settings.edit')
                ->with('status', 'Connect a wallet before creating invoices.');
        }

        $clients = Client::where('user_id', $request->user()->id)
            ->orderBy('name')->get(['id','name']);
        $suggestedNumber = \App\Models\Invoice::nextNumberForUser($request->user()->id);

        $r = BtcRate::current();
        $prefillRate = $r['rate_usd'] ?? null;

        $today = now()->toDateString(); // ✅ for invoice_date default

        return view('invoices.create', compact('clients','suggestedNumber','prefillRate','today'));
    }

    public function edit(\Illuminate\Http\Request $request, \App\Models\Invoice $invoice)
    {
        $clients = Client::where('user_id', $request->user()->id)
            ->orderBy('name')->get(['id','name']);

        return view('invoices.edit', compact('invoice','clients'));
    }

    public function trash(\Illuminate\Http\Request $request)
    {
        $this->authorize('viewAny', Invoice::class);
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
        $this->authorize('restore', $invoice);

        $invoice->restore();

        if ($request->wantsJson()) return response()->json($invoice->fresh('client'));
        return redirect()->route('invoices.trash')->with('status', 'Invoice restored.');
    }

    public function forceDestroy(\Illuminate\Http\Request $request, int $invoiceId)
    {
        $invoice = \App\Models\Invoice::withTrashed()->findOrFail($invoiceId);
        $this->authorize('forceDelete', $invoice);

        $invoice->forceDelete();

        if ($request->wantsJson()) return response()->json(['deleted' => true]);
        return redirect()->route('invoices.trash')->with('status', 'Invoice permanently deleted.');
    }

    public function setStatus(\Illuminate\Http\Request $request, \App\Models\Invoice $invoice, string $action)
    {
        $this->authorize('update', $invoice);

        $allowed = ['draft','sent','void'];
        abort_unless(in_array($action, $allowed, true), 404);

        $updates = [
            'status' => $action,
            'paid_at' => null,
        ];

        $invoice->update($updates);
        $invoice->refreshPaymentState();

        if ($request->wantsJson()) return response()->json($invoice->fresh('client'));
        return back()->with('status', 'Status updated.');
    }

    public function currentRate()
    {
        //$this->authorize('viewAny', \App\Models\Invoice::class); // optional; remove if no policies

        $r = \App\Services\BtcRate::current();
        if (!$r) {
            return response()->json(['ok' => false, 'message' => 'Rate unavailable'], 503);
        }

        return response()->json([
            'ok'       => true,
            'rate_usd' => $r['rate_usd'],
            'as_of'    => $r['as_of']->toIso8601String(),
            'source'   => $r['source'],
        ]);
    }

    public function refreshRate(\Illuminate\Http\Request $request)
    {
        $rate = BtcRate::refreshCache();

        if ($rate && isset($rate['as_of'])) {
            $request->session()->put('rate_as_of', $rate['as_of']);
        }

        return back()->with('status', 'Rate refreshed.');
    }

    public function print(\Illuminate\Http\Request $request, \App\Models\Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        $invoice = $invoice->load([
            'client',
            'payments' => fn ($query) => $query->orderBy('detected_at')->orderBy('id'),
        ]);
        $rate = BtcRate::current();
        $display = $this->formatInvoiceDisplay($invoice, $rate);
        $summary = $this->buildPaymentSummary($invoice, $rate, $display['computedBtc'] ?? null);

        $btcForUri = $summary['outstanding_btc_float'];
        $display['displayBitcoinUri'] = $invoice->bitcoinUriForAmount(
            $btcForUri,
            allowFallback: $btcForUri !== null
        );

        return view('invoices.print', [
            'invoice' => $invoice,
            'rate_as_of' => $rate['as_of'] ?? null,
            'public' => false,
            'paymentSummary' => $summary,
        ] + $display);
    }


    public function publicPrint(\Illuminate\Http\Request $request, string $token)
    {
        $invoice = \App\Models\Invoice::with('client')
            ->where('public_enabled', true)
            ->where('public_token', $token)
            ->where(function ($q) {
                $q->whereNull('public_expires_at')
                    ->orWhere('public_expires_at', '>', now());
            })
            ->firstOrFail();

        // Fresh rate every view
        $rate = \App\Services\BtcRate::fresh() ?? \App\Services\BtcRate::current();
        $rateUsd = $rate['rate_usd'] ?? null;
        $asOf    = $rate['as_of'] ?? now();

        $invoice = $invoice->load('client');
        $display = $this->formatInvoiceDisplay($invoice, $rate);
        $summary = $this->buildPaymentSummary($invoice, $rate, $display['computedBtc'] ?? null);

        $btcForUri = $summary['outstanding_btc_float'];
        $display['displayBitcoinUri'] = $invoice->bitcoinUriForAmount(
            $btcForUri,
            allowFallback: $btcForUri !== null
        );

        return response()
            ->view('invoices.print', [
                'invoice'       => $invoice,
                'rate_as_of'    => $asOf,
                'public'        => true,
                'paymentSummary'=> $summary,
            ] + $display)
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');

    }

    public function enableShare(\Illuminate\Http\Request $request, \App\Models\Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        //if ($invoice->status === 'draft') {
        //    return back()->with('status', 'Public link not allowed for drafts. Mark Sent or Paid first.');
        //}

        $data = $request->validate([
            'expires'        => ['nullable','date','after:now'],
            'expires_preset' => ['nullable','in:none,24h,7d,30d'],
        ]);

        $expires = null;
        if (!empty($data['expires_preset']) && $data['expires_preset'] !== 'none') {
            switch ($data['expires_preset']) {
                case '24h':  $expires = now()->addDay(); break;
                case '7d':   $expires = now()->addDays(7); break;
                case '30d':  $expires = now()->addDays(30); break;
            }
        } elseif (!empty($data['expires'])) {
            $expires = \Carbon\Carbon::parse($data['expires']);
        }

        $invoice->enablePublicShare($expires);

        return back()->with('status', 'Public link enabled.')
            ->with('public_url', $invoice->public_url);
    }

    public function disableShare(\Illuminate\Http\Request $request, \App\Models\Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        $invoice->disablePublicShare();

        return back()->with('status', 'Public link disabled.');
    }

    public function rotateShare(\Illuminate\Http\Request $request, \App\Models\Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        $invoice->public_token = \App\Models\Invoice::generatePublicToken();
        $invoice->save();

        return back()->with('status', 'Public link regenerated.')
            ->with('public_url', $invoice->public_enabled ? $invoice->public_url : null);
    }

    private function buildPaymentSummary(Invoice $invoice, ?array $rate, ?float $computedBtc): array
    {
        $expectedUsd = $invoice->amount_usd !== null ? (float) $invoice->amount_usd : null;
        $rateUsd = isset($rate['rate_usd']) ? (float) $rate['rate_usd'] : null;

        $expectedBtcFloat = $computedBtc ?? ($invoice->amount_btc !== null ? (float) $invoice->amount_btc : null);
        $expectedSats = $expectedBtcFloat !== null
            ? (int) round($expectedBtcFloat * Invoice::SATS_PER_BTC)
            : $invoice->expectedPaymentSats();

        $paidSats = (int) $invoice->payments->sum('sats_received');
        $receivedUsd = $this->sumPaymentsUsd($invoice);

        $outstandingUsd = $expectedUsd !== null
            ? max($expectedUsd - $receivedUsd, 0.0)
            : null;

        $outstandingBtcFloat = null;
        if ($outstandingUsd !== null && $outstandingUsd > 0) {
            $sourceRate = $rateUsd ?? ($invoice->btc_rate ? (float) $invoice->btc_rate : null);
            if ($sourceRate && $sourceRate > 0) {
                $outstandingBtcFloat = round($outstandingUsd / $sourceRate, 8);
            }
        }

        $outstandingSats = $expectedSats !== null
            ? max($expectedSats - $paidSats, 0)
            : null;

        if ($expectedSats !== null && $paidSats >= $expectedSats - Invoice::PAYMENT_SAT_TOLERANCE) {
            $outstandingSats = 0;
            if ($outstandingUsd !== null) {
                $outstandingUsd = 0.0;
            }
            if ($outstandingBtcFloat !== null) {
                $outstandingBtcFloat = 0.0;
            }
        }

        $lastDetected = $invoice->payments->max('detected_at');
        $lastConfirmed = $invoice->payments->max('confirmed_at');

        return [
            'expected_usd' => $expectedUsd,
            'expected_btc_formatted' => $invoice->formatBitcoinAmount($expectedBtcFloat),
            'expected_sats' => $expectedSats,
            'received_usd' => round($receivedUsd, 2),
            'received_sats' => $paidSats,
            'outstanding_usd' => $outstandingUsd !== null ? round($outstandingUsd, 2) : null,
            'outstanding_btc_formatted' => $invoice->formatBitcoinAmount($outstandingBtcFloat),
            'outstanding_btc_float' => $outstandingBtcFloat,
            'outstanding_sats' => $outstandingSats,
            'last_payment_detected_at' => $lastDetected,
            'last_payment_confirmed_at' => $lastConfirmed,
        ];
    }

    private function sumPaymentsUsd(Invoice $invoice): float
    {
        return $invoice->payments->sum(function ($payment) use ($invoice) {
            return $this->paymentUsdValue($invoice, $payment);
        });
    }

    private function paymentUsdValue(Invoice $invoice, $payment): float
    {
        $fiat = $payment->fiat_amount;
        if ($fiat !== null) {
            return (float) $fiat;
        }

        if ($payment->usd_rate !== null) {
            return round(($payment->sats_received / Invoice::SATS_PER_BTC) * (float) $payment->usd_rate, 2);
        }

        if ($invoice->btc_rate) {
            return round(($payment->sats_received / Invoice::SATS_PER_BTC) * (float) $invoice->btc_rate, 2);
        }

        return 0.0;
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

    private function formatInvoiceDisplay(Invoice $invoice, ?array $rate): array
    {
        $rateUsd = isset($rate['rate_usd']) ? (float) $rate['rate_usd'] : null;
        $amountUsd = $invoice->amount_usd !== null ? (float) $invoice->amount_usd : null;
        $storedAmountBtc = $invoice->amount_btc !== null ? (float) $invoice->amount_btc : null;
        $storedRateUsd = $invoice->btc_rate !== null ? (float) $invoice->btc_rate : null;

        $computedBtc = null;
        if ($rateUsd !== null && $rateUsd > 0 && $amountUsd !== null && $amountUsd > 0) {
            $computedBtc = round($amountUsd / $rateUsd, 8);
        }

        $displayAmountBtc = null;
        if ($computedBtc !== null && $computedBtc > 0) {
            $displayAmountBtc = $invoice->formatBitcoinAmount($computedBtc);
        }
        if ($displayAmountBtc === null && $storedAmountBtc !== null) {
            $displayAmountBtc = $invoice->formatBitcoinAmount($storedAmountBtc);
        }

        $displayRateUsd = null;
        if ($rateUsd !== null && $rateUsd > 0) {
            $displayRateUsd = number_format($rateUsd, 2, '.', '');
        } elseif ($storedRateUsd !== null && $storedRateUsd > 0) {
            $displayRateUsd = number_format($storedRateUsd, 2, '.', '');
        }

        $bitcoinUriAmount = $computedBtc ?? $storedAmountBtc;
        $displayBitcoinUri = $invoice->bitcoinUriForAmount($bitcoinUriAmount);

        return [
            'computedBtc'       => $computedBtc,
            'displayAmountBtc'  => $displayAmountBtc,
            'displayRateUsd'    => $displayRateUsd,
            'displayBitcoinUri' => $displayBitcoinUri,
        ];
    }
}
