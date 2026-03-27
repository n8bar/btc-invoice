# Notifications, Delivery & Alerts Spec

This doc is canonical for outbound invoice communication:
- manual invoice send flow
- paid receipts
- automated alert triggers
- shared delivery-log mechanics
- cooldown expectations and notice-class behavior

## Goals
- Let invoice owners email a public link plus summary to their client from the app.
- Automatically send a paid receipt email once the watcher marks an invoice paid.
- Ensure both owners and clients receive email alerts for key invoice events without relying on manual follow-up.
- Surface automated emails when an invoice is paid, becomes past due, or when on-chain payments deviate from the invoice total by more than the defined tolerance.
- Prevent payment-triggered mail from claiming certainty after a later on-chain payment on an already-funded invoice when that payment may be semantically ambiguous.

## Base Communication Flows
1. **Send Invoice (manual owner action)**
   - Visible on invoice show when the invoice has a client email and public link enabled.
   - Submission dispatches a queued job that renders the invoice-ready email and logs the attempt.
   - Draft sends are allowed for now, but the UX should make draft state clear.

2. **Paid Receipt (automatic client receipt)**
   - Fired when `InvoicePaid` dispatches after the invoice transitions to `paid`.
   - Skip when the client email is missing or the receipt was already sent for the current paid state.
   - Receipt email includes amount, tx metadata, settlement timestamps, and a link to the paid public page.

3. **Delivery Log**
   - `invoice_deliveries` is the shared audit log for manual sends, receipts, and automated alerts.
   - Rows capture invoice, sender/owner context, recipient email(s), delivery type, status, and error/timestamp metadata.
   - The owner invoice detail page remains the primary operator view for this history.

## Automated Alert Triggers
1. **Invoice Paid Notice (Owner)**
   - Fired when `InvoicePaid` event dispatches (already happens when watcher crosses `paid` threshold or manual adjustments close the balance).
   - Owner gets a succinct “Invoice {number} paid” email summarizing amounts, tx metadata, and a link back to the dashboard.
   - Delivery log should capture owner notice and client receipt with distinct `type` values (for example `owner_paid_notice`, `receipt`).

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

5. **Proactive Partial-Payment Warning (Client + Owner FYI)**
   - Fired after the watcher detects multiple partial payments on the same invoice to encourage a single payment and reduce miner fees.
   - Client email reminds them to send one payment for the outstanding balance; owner gets an FYI in the delivery log/CC.
   - Logged via `invoice_deliveries` (e.g., `partial_warning_client`, `partial_warning_owner`) and respects aliasing in non-prod.

## Shared Implementation Requirements
- Use the existing queued mail + `invoice_deliveries` pattern for all outbound communication so aliasing and delivery logging stay consistent.
- `APP_PUBLIC_URL` defines the host used in public-share links embedded in emails. Production must use `https://cryptozing.app`.
- Temporary catch-all aliasing during pre-production uses `MAIL_ALIAS_ENABLED=true` and `MAIL_ALIAS_DOMAIN=mailer.cryptozing.app`. Disable aliasing before RC or real-customer traffic.
- Profile setting for automatic paid receipts remains part of the owner communication model.
- A global feature flag may disable outbound invoice communication entirely when mail is not configured.
- Delivery jobs should surface queued, sent, and failed outcomes through the shared delivery log.
- Once an invoice has already received one or more detected on-chain payments, any later on-chain payment on that same invoice may be semantically ambiguous even when the wallet remains supported. Examples include stale-address reuse and payers intentionally using an older valid CryptoZing invoice address for a newer invoice.
- The later-payment owner-validation gate is planned MS16 work, not an MS14 Phase 5 reattribution gate.
- For second-or-later detected on-chain payments on the same invoice, payment-triggered outbound mail should eventually be held pending owner validation before send.
- This planned later-payment validation gate applies to `receipt`, `owner_paid_notice`, `client_partial_warning`, `owner_partial_warning`, and any overpayment or underpayment alert first raised by that later payment.
- Manual invoice sends and past-due reminders are outside this safeguard.
- MS16 delivery-log polish should replace raw underscore-separated delivery `type` keys with concise human-readable owner-facing labels.

## Mailables, Routes, and Jobs
- Base communication classes:
  - `App\Mail\InvoiceReadyMail`
  - `App\Mail\InvoicePaidReceipt`
- New mailable classes:
  - `InvoiceOwnerPaidNoticeMail`
  - `InvoicePastDueOwnerMail`
  - `InvoicePastDueClientMail`
  - `InvoiceOverpaymentClientMail`
  - `InvoiceUnderpaymentClientMail`
  - (Optional) `InvoiceUnderpaymentOwnerMail` if we want a distinct copy instead of CCing.
  - `InvoicePartialPaymentWarningClientMail` (plus owner FYI mail/CC)
- Shared Blade partials should keep invoice header/footer branding aligned across email types.
- `POST /invoices/{invoice}/deliver` remains the base manual-send endpoint and must validate ownership, client email presence, and public-link availability before enqueueing work.
- Add a lightweight service that raises “notification intents” from watcher/manual-adjustment flows and deduplicates sends (e.g., don’t send multiple overpay emails for the same invoice unless the percentage keeps climbing and a configured interval has passed).
- Persist last-alert timestamps on `invoices` (columns such as `last_overpayment_alert_at`, `last_underpayment_alert_at`, `last_past_due_alert_at`) to prevent repeated sends within 24h.
- Scheduler additions:
  - Extend the existing `scheduler` service to run a new artisan command (`invoices:send-past-due-alerts`) nightly.
  - Reuse `wallet:watch-payments` (or hook into `InvoicePaymentDetector`) to trigger over/under payment emails immediately after each detection.

## Copy Guidelines
- Keep subjects/actionable text short (<=60 chars). Example subjects:
  - Manual send: `Invoice INV-1042 is ready`
  - Client receipt: `Receipt for Invoice INV-1042`
  - Owner paid: `Invoice INV-1042 paid`
  - Past due client: `Reminder: Invoice INV-1042 is past due`
  - Overpayment client: `Invoice INV-1042 was overpaid`
  - Underpayment client: `Invoice INV-1042 has a balance due`
- Client overpayment body must include: “Overpayments are treated as gratuities by default; please notify the sender if this was a mistake.”
- Underpayment emails must include outstanding USD/BTC amounts and the public link CTA.

## Testing
- Feature tests covering:
  - manual send flow queues work and writes delivery-log entries
  - failure paths record delivery errors
  - `InvoicePaid` dispatch triggers owner notice plus client receipt exactly once
  - automated alert classes respect cooldown and delivery-log rules
  - second-or-later payment-triggered deliveries on an already-funded invoice are held until owner validation
- Optional Blade/mail snapshot tests can be used for the invoice-ready and receipt mailables.

## Open Questions
- Should “Send invoice” stay allowed while status is `draft`? Current direction: yes, but make draft state explicit in the email UX.
- Retry strategy: keep one `invoice_deliveries` row per job fire vs. a single row with status updates. Current direction: one row per job fire for audit clarity.

## Future Enhancements
- After base implementation, consider allowing owners to configure alert thresholds per profile (default 15%).
- Once we add Slack/webhook integrations, mirror these events there for ops teams.
