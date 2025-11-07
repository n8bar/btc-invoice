# Invoice Delivery Plan
_Last updated: 2025-11-07_

## Goals
- Allow an invoice owner to email a client directly from the app.
- Emails include invoice summary, BTC payment instructions, and a signed public link.
- Optionally attach a PDF snapshot of the printable invoice.
- All sends are queued, logged, and visible in the UI.

## User Flow
1. Owner opens an invoice (`/invoices/{invoice}`) and clicks **Send invoice**.
2. Modal/form collects recipient email (prefilled with client email), optional CC, subject override, and a note.
3. Submission hits `POST /invoices/{invoice}/send`, which validates ownership and dispatches a queued job.
4. Job generates/refreshes the public share link (if disabled, enable with short expiry) and prepares the mailable.
5. Email is delivered with:
   - Inline summary (USD + BTC, status, due date).
   - “Pay invoice” button linking to the public print view (no PDF attachment for payment requests).
6. Send attempt logged and surfaced back on the invoice show page.

## Architecture Overview

### Routes / Controllers
- New controller `InvoiceDeliveryController` with `send` action.
- Route: `POST /invoices/{invoice}/send` inside the auth group.
- Controller responsibilities:
  - Authorize `update` on invoice.
- Validate payload: `recipient` (email), `cc` (nullable), `subject` (optional), `message` (optional).
  - Dispatch `SendInvoiceEmail` job with invoice ID + payload + authenticated user ID.

### Queue Job
- `SendInvoiceEmail implements ShouldQueue`.
- Steps:
  1. Reload invoice + owner + client; abort if missing/ownership mismatch.
  2. Ensure public share link exists (`enablePublicShare()` if missing), optionally with rotation if expired.
  3. Snapshot current BTC rate via `BtcRate::current()` (refresh if stale) for deterministic email copy.
  4. Instantiate `InvoiceDelivered` mailable with the snapshot data.
  5. Send mail using default mailer (configurable via `.env`).
  6. Update `invoice_delivery_logs` entry to `sent` or `failed` based on result.

### Mailable
- `App\Mail\InvoiceDelivered` (markdown view under `resources/views/mail/invoice-delivered.blade.php`).
- Injects: invoice summary, amount breakdown, rate timestamp, owner note, signed link, optional copy button text.
- Should support preview via `Mail::fake()` tests.

### Data Model / Logging
- New table `invoice_delivery_logs`:
- `id`, `invoice_id`, `user_id`, `recipient`, `cc`, `status` (enum: queued, sent, failed), `queued_at`, `sent_at`, `failed_at`, `failure_reason` (text), timestamps.
- `Invoice` hasMany `deliveryLogs` relation; `User` hasMany as sender.
- On dispatch, create log row (status `queued`), update inside job when sending finishes/fails.
- Optional: store `mail_driver_message_id` for ESP correlation.

### UI Updates
- Invoice show page: add **Send invoice** button and a collapsible “Delivery log” table showing last 5 sends (recipient, status badge, timestamp).
- Flash success/error when send request accepted (job enqueued) or validation fails.
- Disabled state when queue is down? show message via health check.

### Configuration
- `.env` additions to highlight mail driver requirements (e.g., Mailgun). Document in README.
- Add config flag for default expiry on auto-enabled public links (e.g., 30 days).

### Dependencies
- Ensure queue worker containers run (Sail `php artisan queue:work`). Document in README + PLAN.

## Testing Strategy
- **Feature tests**: POST send endpoint using `Queue::fake()` to ensure job dispatched with correct payload and unauthorized users are blocked.
- **Job tests**: use `Mail::fake()` to assert mailable is sent, attachments present when requested, share link auto-created, and logs updated (use `Queue::fake()->dispatchNow()` or `Bus::fake()` + `Mail::fake()`).
- **Log display tests**: ensure show page lists latest log entries and status badges.

## Open Questions / Decisions
- Whether to allow custom email templates per user (out of scope for first pass).
- Whether to restrict attachments for very large invoices; initial plan assumes small HTML-to-PDF output < 1 MB.
- Queue driver: default Sail setup uses `database` queue; production should use Redis/SQS—document expectation but keep implementation driver-agnostic.

## Future Integration: Blockchain Watcher
- Generate per-invoice BTC addresses (via HD wallet/xpub or external service) so payment requests are immutable.
- Subscribe to transaction callbacks (mempool + confirmation). On detection, auto-mark the invoice paid and record payment metadata (txid, amount, confirmation height).
- Remove manual “Mark paid” button; only allow auto-paid status + admin reset if absolutely necessary.
- Email receipts for paid invoices can include a PDF snapshot with fixed rate/as-of at confirmation time.
