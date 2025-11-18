# Partial Payments Spec

## Goals
- Record every on-chain payment that hits an invoice address, even if the amount is below the invoice total.
- Surface a `partial` status so owners/clients know funds have arrived but more is due.
- Preserve the BTC/USD rate at the moment we detect each payment for accurate receipts/statements.
- Provide enough metadata for future features (receipt mail, delivery logs, dashboards) to reference payment history.

## Data Model
1. **`invoice_payments` table**
    - `id`, `invoice_id`, `txid`, `vout_index` (optional), `sats_received`, `detected_at`, `confirmed_at`, `block_height`, `usd_rate`, `fiat_amount`.
    - JSON column `raw_tx` (optional) for debugging or future proofs.
    - Index on (`invoice_id`, `txid`) to prevent duplicates.

2. **Invoice columns**
    - Keep `amount_btc` as the target.
    - Add `status` enum entries: `draft`, `sent`, `partial`, `paid`, `void`.
    - New computed columns/accessors:
        - `paid_sats` (sum of confirmed + unconfirmed payments).
        - `outstanding_sats = expected - paid`.

## Watcher Behavior
- Increase tolerance to ±100 sats to ignore tiny rounding differences.
- When detector finds a tx:
    1. Record (or update) a row in `invoice_payments`.
    2. Recompute total sats received (including this tx).
    3. Status transitions:
        - `sent` → `partial` when total < expected.
        - `partial` → `paid` when total ≥ expected (subject to confirmation threshold).
        - `draft` stays draft if we really want to block payments until “sent” — TBD.
    4. `paid_at` stays the first detection timestamp once totals cross expected; confirmation timestamps update when block data arrives.
- Handle multiple payments per tx/address pair gracefully (e.g., same tx sends two outputs to us) by summing `sats_received`.

## USD Snapshot
- When we log each payment, capture the USD/BTC rate:
    - Use the cached rate if it’s fresh (< defined TTL), otherwise call `BtcRate::refresh`.
    - Store `usd_rate` and `fiat_amount = sats_received / 1e8 * usd_rate`.
- Treat the original USD invoice total as canonical; each payment reduces the outstanding USD balance using its captured `usd_rate` so owners always see dollars knocked off at the moment funds arrived (BTC volatility never retroactively changes settled USD).
- Owner/public summary boxes always present USD first (e.g., `Expected: $500.00 (0.0123 BTC)`, `Outstanding: $125.00 (0.0031 BTC)`). Received shows the settled USD total only, since BTC varies per payment.
- QR/BIP21 requests target the *current outstanding USD balance*, converted to BTC using the latest available rate; once the balance hits zero, the QR omits the `amount` parameter altogether.

## UI / API
1. **Invoice Show Page**
    - Payment summary card with:
        - Total expected vs paid vs outstanding (BTC + USD).
        - Status badge showing `Partial` when applicable.
    - Payment history table (one row per `invoice_payments` row) showing txid, amount, detected/confirmed timestamps, fiat value.
    - Alerts for underpayments (e.g., outstanding > 0).

2. **Print/Public View**
    - Mirror the payment history (maybe condensed) so clients see what we received.
    - Paid watermark only once status `paid`; otherwise show an “Outstanding balance” note.

3. **API / JSON**
    - If we expose invoices via API, include payment fragments + totals.

## Interactions with Invoice Delivery
- Receipt emails should enumerate the payments and total settled amount.
- Delivery log should note whether auto-receipts fired after full payment or partial payment updates.

## Testing
- Unit tests for `Invoice` accessors (`paid_sats`, `outstanding_sats`, status transitions).
- Watcher feature tests covering:
    - First partial payment logged.
    - Multiple partials summing to paid.
    - Confirmations updating existing payment rows.
    - Overpayments (money above expected) flagged but still mark invoice `paid`.
- Blade tests / snapshots for the payment history table and public view.

## Completed Tasks
1. ✅ `invoice_payments` table stores every tx with sats + USD snapshot per detection.
2. ✅ Watcher (`wallet:watch-payments`) records multiple partials per invoice and refreshes status/outstanding totals automatically.
3. ✅ UI shows payment history, USD-first summary, and QR codes that target the outstanding balance.
4. ✅ Watcher tolerance (±100 sats) is enforced and detection/confirmation timestamps surface in the payment history UI.
5. ✅ Payment history rows display the captured USD rate/fiat amount and owners can annotate each payment with short notes.
6. ✅ Automatic invoice delivery + paid receipt emails log to `invoice_deliveries`, with queue-backed mailers and profile toggles.

## Roadmap to Release Candidate
7. Trigger owner alerts for significant over- or under-payments (per the tolerance rules) and provide a manual adjustment flow to reconcile errors without editing the original tx rows.

## Clarifications
- **Draft invoices**: payments may arrive even while status is `draft` (each invoice address is unique), so the watcher still logs them immediately. The UI simply defers showing payment history until the invoice is marked `sent` to avoid confusing “pending drafts.”
- **Overpayments / Tips**: record the surplus (treat it as a tip by default), keep status `paid`, and introduce two levels of handling:
    - **Noise tolerance** (≤ $10 USD equivalent or ≤ 1% of invoice) — simply show the extra as part of the payment history without alerts.
    - **Significant overpay** (> tolerance) — flag the invoice for the owner (UI + notification) with recommended actions: keep it as a tip, credit the excess toward the next invoice, or refund minus fees. If a client has multiple overpaid invoices, batch the refund/credit calculation so the owner can settle them in one transaction. Future automation can email the client with those options if we don’t act within a configured SLA so mistaken overpayments can be corrected.
