<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\InvoiceDelivery;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceUnderpaymentClientMail extends Mailable
{
    use SerializesModels;

    public function __construct(public Invoice $invoice, public InvoiceDelivery $delivery)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invoice ' . ($this->invoice->number ?? $this->invoice->id) . ' has a balance due',
            replyTo: [new Address($this->invoice->user->email, $this->invoice->user->name)],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invoice-underpayment-client',
            with: [
                'invoice' => $this->invoice,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
