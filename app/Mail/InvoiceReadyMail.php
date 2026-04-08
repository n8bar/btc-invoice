<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\InvoiceDelivery;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceReadyMail extends Mailable
{
    use SerializesModels;

    public function __construct(public Invoice $invoice, public InvoiceDelivery $delivery)
    {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invoice ' . ($this->invoice->number ?? $this->invoice->id) . ' ready',
            replyTo: [new Address($this->invoice->user->email, $this->invoice->user->name)],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invoice-ready',
            with: [
                'invoice' => $this->invoice,
                'delivery' => $this->delivery,
                'client' => $this->invoice->client,
                'publicUrl' => $this->invoice->public_url,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
