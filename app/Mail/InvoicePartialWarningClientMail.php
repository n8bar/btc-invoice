<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\InvoiceDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoicePartialWarningClientMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice, public InvoiceDelivery $delivery)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invoice ' . ($this->invoice->number ?? $this->invoice->id) . ' payment reminder',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invoice-partial-warning-client',
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
