# Notifications & Alerts Spec

## Goals
- Ensure both owners and clients receive email alerts for key invoice events without relying on manual follow-up.
- Surface automated emails when an invoice is paid, becomes past due, or when on-chain payments deviate from the invoice total by more than the defined tolerance.
- Preserve existing delivery infrastructure (queued mail, Mailgun aliasing, `invoice_deliveries` log) so every outbound email is auditable.

## Triggers
1. **Invoice Paid (Owner + Client)**
   - Fired when `InvoicePaid` event dispatches (already happens when watcher crosses `paid` threshold or manual adjustments close the balance).
   - Owner gets a succinct “Invoice {number} paid” email summarizing amounts, tx metadata, and a link back to the dashboard.
   - Client receives the existing receipt email (extend current `InvoicePaidReceiptMail` to reuse the new copy where sensible) so both parties have confirmation.
   - Delivery log should capture both owner + client emails with distinct `type` values (e.g., `owner_paid_notice`, `receipt`).

2. **Past Due (Owner + Client)**
   - Nightly scheduler checks invoices whose `due_date` is in the past, status is not `paid`/`void`, and that have not been reminded in the last 48h.
   - Owner email: “Invoice {number} is past due” with outstanding totals and suggested next steps.
   - Client email: polite reminder referencing outstanding balance, invoice link, and a short “contact the sender if you already paid” line.
   - Store reminders in `invoice_deliveries` (type `past_due_owner`, `past_due_client`) to avoid spamming.

3. **Overpayment Alert (Client)**
   - Triggered when `overpaymentPercent() >= 15%`. Checked whenever watcher logs a new payment or a manual adjustment increases the paid total.
   - Email explains that overpayments are treated as gratuities by default and asks the client to contact the sender if it was accidental.
   - Owner can be CC’d automatically (or has the option via profile setting) so they know the alert fired.

4. **Underpayment Alert (Client)**
   - Triggered when `underpaymentPercent() >= 15%` (after tolerance). Same entry point as overpay (watcher or manual adjustment that reopens balance).
   - Email lists the outstanding USD/BTC amounts and links to the public invoice so the client can settle.
   - Owner also receives a brief notice (“Client underpayment alert sent”) so they’re aware of the outreach.

## Implementation Notes
- Use the existing job/mailable pattern (`DeliverInvoiceMail` + `invoice_deliveries`) for all new emails so aliasing + logging stay consistent.
- New mailable classes:
  - `InvoiceOwnerPaidNoticeMail`
  - `InvoicePastDueOwnerMail`
  - `InvoicePastDueClientMail`
  - `InvoiceOverpaymentClientMail`
  - `InvoiceUnderpaymentClientMail`
  - (Optional) `InvoiceUnderpaymentOwnerMail` if we want a distinct copy instead of CCing.
- Add a lightweight service that raises “notification intents” from watcher/manual-adjustment flows and deduplicates sends (e.g., don’t send multiple overpay emails for the same invoice unless the percentage keeps climbing and a configured interval has passed).
- Persist last-alert timestamps on `invoices` (columns such as `last_overpayment_alert_at`, `last_underpayment_alert_at`, `last_past_due_alert_at`) to prevent repeated sends within 24h.
- Scheduler additions:
  - Extend the existing `scheduler` service to run a new artisan command (`invoices:send-past-due-alerts`) nightly.
  - Reuse `wallet:watch-payments` (or hook into `InvoicePaymentDetector`) to trigger over/under payment emails immediately after each detection.

## Copy Guidelines
- Keep subjects/actionable text short (<=60 chars). Example subjects:
  - Owner paid: `Invoice INV-1042 paid`
  - Past due client: `Reminder: Invoice INV-1042 is past due`
  - Overpayment client: `Invoice INV-1042 was overpaid`
  - Underpayment client: `Invoice INV-1042 has a balance due`
- Client overpayment body must include: “Overpayments are treated as gratuities by default; please notify the sender if this was a mistake.”
- Underpayment emails must include outstanding USD/BTC amounts and the public link CTA.

## Future Enhancements
- After base implementation, consider allowing owners to configure alert thresholds per profile (default 15%).
- Once we add Slack/webhook integrations, mirror these events there for ops teams.
