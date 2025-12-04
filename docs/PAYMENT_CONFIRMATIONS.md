# Payment Confirmations & RBF Safety

Goal: keep payment tracking accurate under replace-by-fee (RBF) and require confirmations before invoices are marked paid, while treating USD as the source of truth and locking each payment at its captured rate.

## Status Flow
- `sent`: no payments detected.
- `pending`: unconfirmed payments detected; awaiting confirmations. Show received/confirmed sats and confirmations count.
- `partial`: confirmed payments received but confirmed USD remains below expected.
- `paid`: confirmed USD meets/exceeds expected (after confirmation threshold).

## Confirmation Gate
- Default confirmation threshold: 1 (configurable via `BLOCKCHAIN_CONFIRMATIONS_REQUIRED`). Post-RC: allow per-user setting (1–6) with app default fallback.
- Invoice transitions to `paid` only when **confirmed USD totals** satisfy the expected USD; unconfirmed amounts keep the invoice `pending` (or `partial` if confirmed USD is >0 but short).
- `paid_at` set only on confirmed transition.

## RBF/Dropped TX Handling
- On each watcher run:
  - Fetch current mempool txs for the invoice address.
  - For stored **unconfirmed** txids that are no longer present, drop/ignore them (do not count toward totals); never drop confirmed txs.
  - Deduplicate by txid; if a replacement appears, treat it as the current record rather than additive.
- Recompute sats + per-payment USD, outstanding USD/BTC, and status after cleanup.
- Log dropped/replaced tx events; optional owner notification.

## UI/Copy
- Surface unconfirmed payments with “Pending confirmation” plus confirmations count.
- Show both received and confirmed amounts (USD + BTC where helpful); reserve “Paid” badges/messages for confirmed state once confirmed USD meets expected.

## Tests to Add
- Unconfirmed payment does not mark paid; flips to paid after confirmations >= threshold.
- RBF replacement: old tx disappears, new tx appears; totals reflect only the live tx; no double-count.
- Dropped unconfirmed tx shrinks total and reverts status appropriately.
- Confirmed tx persists and drives paid state using confirmed USD totals.

## Migration (Post-RC)
- Add per-user “required confirmations” setting (1–6), used by watcher; default to app setting when unset.
