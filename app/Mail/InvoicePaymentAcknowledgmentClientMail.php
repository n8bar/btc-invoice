<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\InvoiceDelivery;
use App\Models\InvoicePayment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoicePaymentAcknowledgmentClientMail extends Mailable
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
            subject: 'Bitcoin payment detected',
            replyTo: [new Address($this->invoice->user->email, $this->invoice->user->name)],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invoice-payment-acknowledgment-client',
            with: [
                'invoice' => $this->invoice,
                'delivery' => $this->delivery,
                'payment' => $this->payment,
                'client' => $this->invoice->client,
                'publicUrl' => $this->invoice->public_url,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
