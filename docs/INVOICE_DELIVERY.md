# Invoice Delivery Spec

## Goals
- Let invoice owners email a signed public link + summary to their client right from the app.
- Automatically send a paid receipt email once the watcher marks an invoice paid.
- Persist delivery attempts (success/error metadata) so the invoice detail page shows the history.
- Keep the feature optional (per-user mail settings) and require no third-party services beyond standard SMTP.

## Scope
1. **Send Invoice** (manual action)
    - UI button on invoice show page (visible when the invoice has a client email + public link enabled).
    - Modal/form collects optional message, CC self toggle, and whether to include a PDF (future hook).
    - Submission dispatches a queued job that renders the “Invoice Ready” mailable and logs the attempt.

2. **Paid Receipt** (automatic)
    - Watcher dispatches `InvoicePaid` event when it transitions status → `paid`.
    - Listener queues `SendInvoiceReceipt` job (skip if already sent or if client email missing).
    - Receipt email contains amount, tx metadata, settlement timestamps, and link to the paid public page.

3. **Delivery Log**
    - `invoice_deliveries` table with: invoice_id, user_id, type (`send`,`receipt`), recipient email(s), status (`queued`,`sent`,`failed`), error message, dispatched_at, sent_at.
    - Relationship `Invoice->deliveries()` to list attempts on the show page; show type/status badges and timestamps.

4. **Configuration**
    - `.env` additions: `MAIL_FROM_NAME`, `MAIL_FROM_ADDRESS`, plus document SMTP expectations in README/PLAN.
    - Profile setting for “Auto-email paid receipts to client” (default on).
    - Feature flag to disable Invoice Delivery globally if mail isn’t configured.
    - `APP_PUBLIC_URL` defines the domain used in public share links embedded in emails; staging can leave this as localhost, but production must set it to `https://cryptozing.app`.
    - Temporary catch-all aliasing during pre-production: `MAIL_ALIAS_ENABLED=true` and `MAIL_ALIAS_DOMAIN=mailer.cryptozing.app` rewrites every outgoing recipient to the CryptoZing catch-all route managed in Mailgun. Disable this flag (or clear the domain) as part of the RC deployment checklist when real emails should go to customers.

5. **Mailables**
    - `App\Mail\InvoiceReadyMail`: includes client name, invoice summary, CTA button linking to public share, optional custom note.
    - `App\Mail\InvoicePaidReceipt`: includes paid timestamp, txid, confirmations, USD/BTC amounts, button linking to public share (with watermark).
    - Shared Blade partial for invoice header/footer branding; queueable mailables.

6. **Routes / Controllers**
    - `POST /invoices/{invoice}/deliver`: controller validates ownership, ensures public link + client email exist, enqueues send job.
    - Jobs push `InvoiceDeliveryLogged` events so the log table updates on queued, sent, failed.

7. **Testing**
    - Feature tests covering: manual send flow enqueues job, job renders email + log entry, failure path records error.
    - Event listener test to confirm `InvoicePaid` dispatch triggers receipt job exactly once.
    - Blade snapshot tests for both mailables fed with fake data (optional but recommended).

8. **Future Hooks**
    - PDF attachment (once we add printable receipts).
    - Webhook/Slack notifications piggybacking on the same log/events.

## Open Questions
- Should “Send invoice” be allowed while status is `draft`? Proposed: yes, but badge the email as “Draft”.
- Retry strategy: rely on queue retry/backoff and mark log entries per attempt (multiple rows?) or keep a single row with status updates? Proposed: one row per job fire, with `status` transitions.

Once this spec is approved, implementation will include the migration, models, controllers, jobs, mailables, Blade templates, profile toggle, and UI changes outlined above.
