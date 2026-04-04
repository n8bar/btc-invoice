<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\InvoiceDelivery;
use App\Models\InvoicePayment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoicePaymentAcknowledgmentIssuerMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public InvoiceDelivery $delivery,
        public ?InvoicePayment $payment,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Review detected payment for Invoice ' . ($this->invoice->number ?? $this->invoice->id),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invoice-payment-acknowledgment-issuer',
            with: [
                'invoice' => $this->invoice,
                'delivery' => $this->delivery,
                'payment' => $this->payment,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
