<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvoicePaymentNoteController extends Controller
{
    public function update(Request $request, Invoice $invoice, InvoicePayment $payment): RedirectResponse
    {
        $this->authorize('update', $invoice);

        abort_if($payment->invoice_id !== $invoice->id, 404);

        $data = $request->validate([
            'note' => ['nullable','string','max:500'],
            'source_payment_id' => ['nullable','integer'],
        ]);

        $payment->note = $data['note'] ?? null;
        $payment->save();

        return back()->with('status', 'Payment note updated.');
    }
}
