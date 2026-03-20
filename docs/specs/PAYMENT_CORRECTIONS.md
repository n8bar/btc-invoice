# Payment Corrections / Ignore-Restore Spec

Purpose: define the owner-only correction flow for wrongly attributed on-chain payments in MS14 Phase 5 while preserving auditability and keeping invoice/payment state truthful across owner, public, and operational surfaces.

This doc is canonical for:
- ignoring/restoring on-chain `invoice_payments` rows
- correction-specific audit metadata and logs
- recalculation requirements after ignore/restore
- UI/authorization rules for payment correction actions

This doc complements [`docs/specs/PARTIAL_PAYMENTS.md`](PARTIAL_PAYMENTS.md). Manual adjustments remain a separate ledger feature; this spec covers correction of detected on-chain rows that should no longer count toward an invoice.

## Goals
- Give the owner an explicit way to exclude a wrongly attributed on-chain payment from an invoice without deleting the underlying ledger row.
- Preserve the original transaction evidence (`txid`, sats, timestamps, metadata, notes) for support/debug/audit use.
- Make the correction reversible so mistaken ignores can be restored cleanly.
- Recompute invoice state immediately so status, outstanding balance, QR/BIP21 targets, and payment summaries stop claiming the ignored payment.
- Keep the correction surface owner-only and auditable.

## Non-Goals
- Editing the amount, txid, or timestamps of an on-chain payment row.
- Replacing the existing manual-adjustment flow.
- Bulk ignore/restore operations across multiple invoices in RC.
- Granting support agents or public users any correction ability.

## Core Decisions
1. Corrections target individual non-adjustment `invoice_payments` rows only.
2. Ignoring a payment excludes it from settlement math and invoice state, but does not delete the row.
3. Restoring a payment reverses the ignore and returns the row to normal settlement math.
4. Manual adjustment rows (`is_adjustment = true`) are never eligible for ignore/restore.
5. Owner auditability takes precedence over convenience: correction actions require explicit intent and leave a trace.

## Data Model
Add correction metadata directly to `invoice_payments`:
- `ignored_at` timestamp nullable
- `ignored_by_user_id` foreign ID nullable, `nullOnDelete()`
- `ignore_reason` string(500) nullable

Rules:
- A row is considered ignored when `ignored_at` is non-null.
- `ignore_reason` is required when a row is ignored.
- Restoring a payment clears `ignored_at`, `ignored_by_user_id`, and `ignore_reason`.
- Ignore/restore never rewrites `txid`, `vout_index`, `sats_received`, `detected_at`, `confirmed_at`, `block_height`, `usd_rate`, `fiat_amount`, `meta`, or `note`.
- No separate RC audit table is required; row metadata plus structured logs is sufficient for this phase.

## Ledger and Recalculation Rules
Ignored rows must be excluded from all owner/client-visible settlement math:
- `Invoice::sumPaymentsUsd()`
- `Invoice::sumPaymentSats()`
- `Invoice::paymentSummary()`
- invoice status recomputation
- invoice outstanding totals / QR / BIP21 amount targeting
- dashboard totals and recent-payment aggregates
- alert threshold checks that depend on paid/outstanding amounts

Ignore/restore recomputation must refresh invoice-derived fields, not just the row metadata:
- `status`
- `paid_at`
- `payment_amount_sat`
- `txid`
- `payment_confirmations`
- `payment_confirmed_height`
- `payment_detected_at`
- `payment_confirmed_at`

Truthfulness requirements:
- If ignoring a payment causes confirmed USD to fall below expected USD, the invoice must leave `paid` and move to the truthful status (`partial`, `pending`, or `sent`, depending on remaining active payments).
- If an invoice is no longer `paid` after correction, `paid_at` must be cleared.
- If restoring a payment causes confirmed USD to meet/exceed expected USD again, the normal paid transition rules apply.

## Watcher and Sync Interaction
Correction metadata must survive watcher runs:
- The watcher must not auto-clear ignored metadata when it re-sees the same txid.
- Ignored rows must not be deleted by dropped-unconfirmed cleanup.
- If the watcher refreshes sats/confirmation metadata for an ignored row, that row remains ignored until the owner restores it.
- Unsupported-configuration evidence already attached to the invoice/wallet is not automatically cleared by ignore/restore in Phase 5.

## Owner UI
The owner correction surface lives on the invoice show page payment-history table.

For non-adjustment, non-ignored on-chain rows:
- Show an `Ignore` action.
- The action must lead to an explicit confirmation step, not a silent one-click toggle.
- The confirmation step must explain that the payment will stop counting toward totals/status but the raw row will remain for audit.
- Require a short reason field (`ignore_reason`).

For ignored rows:
- Show a visible `Ignored` badge/state in owner payment history.
- Show the ignore reason and ignored timestamp.
- Show a `Restore` action with explicit confirmation.

For manual adjustment rows:
- Do not render ignore/restore controls.
- Keep existing manual-adjustment labels and note behavior.

UX guardrails:
- Keep the confirmation UI inline to the row or section so keyboard flow remains sane and layout shift stays controlled.
- Preserve typed ignore reason on validation failure.
- Focus the first errored field when ignore validation fails.

## Public, Print, and Support Surfaces
- Public and print invoice views must exclude ignored rows from payment history and totals entirely.
- Owner invoice views keep ignored rows visible for audit, clearly marked as ignored.
- Support read-only surfaces may show the owner-visible ignored state if they already render payment history, but must never expose correction controls.

## Authorization and Routing
- Ignore/restore is owner-only.
- Route-model binding must enforce that the payment belongs to the invoice in the URL; mismatches are `404`.
- Public users and support users receive denial/no-control behavior consistent with existing owner-only invoice actions.
- Attempts to ignore an adjustment row must fail safely and leave the ledger unchanged.

Preferred route shape:
- `PATCH /invoices/{invoice}/payments/{payment}/ignore`
- `PATCH /invoices/{invoice}/payments/{payment}/restore`

## Logging and Audit
Every correction action must emit a structured log entry:
- `invoice.payment.ignored`
- `invoice.payment.restored`

Minimum log context:
- `invoice_id`
- `payment_id`
- `user_id`
- `txid`
- `status_before`
- `status_after`

Ignore logs should also include:
- `ignore_reason`

Do not log wallet keys, raw xpubs, seed phrases, or other sensitive wallet material.

## Delivery and Alert Interaction
Correction actions must re-run the same post-payment checks used by existing payment/adjustment flows where they are still truthful:
- recompute invoice state
- reevaluate threshold-based alert conditions

Audit rules:
- Past sent/skipped delivery records remain intact.
- If a correction makes a queued payment-related delivery no longer truthful, mark that queued delivery `skipped` rather than deleting it.

Minimum Phase 5 requirement:
- queued receipts / paid notices must not send after an ignore reopens the invoice
- queued underpay/partial warnings that no longer apply after a restore should be skippable

## Testing
Automated coverage should include:
- ignoring a confirmed on-chain payment excludes it from paid/outstanding calculations and reopens invoice state when appropriate
- restoring that payment returns the invoice to the prior truthful state
- owner-only authorization for ignore/restore routes
- manual adjustment rows cannot be ignored or restored through the correction flow
- correction metadata persistence (`ignored_at`, `ignored_by_user_id`, `ignore_reason`)
- structured audit log emission for ignore and restore
- ignored rows remain in owner payment history but disappear from public/print settlement views
- dashboard totals/recent payments exclude ignored rows
- watcher sync does not delete or auto-restore ignored rows
- queued payment-related deliveries that become untruthful are marked skipped, not deleted

Browser QA should include:
- owner can ignore a row with a reason and immediately see totals/status change
- owner can restore the row and see totals/status recover
- ignored rows remain visible and clearly marked in owner history
- ignored rows do not appear in public/print payment history
- manual adjustment rows never show correction controls

## Definition of Done
- Owners can ignore and restore on-chain payment rows from the invoice show page with explicit confirmation.
- Ignored rows remain preserved in `invoice_payments` and are auditable.
- Invoice/payment/dashboard/public math excludes ignored rows consistently.
- Manual adjustments remain untouched by the correction flow.
- Logs and tests cover the correction actions and their recalculation effects.
