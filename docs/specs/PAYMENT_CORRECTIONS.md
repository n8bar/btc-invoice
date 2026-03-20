# Payment Corrections / Ignore-Restore-Reattribute Spec

Purpose: define the owner-only correction flow for wrongly attributed on-chain payments in MS14 Phase 5 while preserving auditability and keeping invoice/payment state truthful across owner, public, and operational surfaces.

This doc is canonical for:
- ignoring/restoring on-chain `invoice_payments` rows
- planned invoice-to-invoice reattribution of detected payments
- correction-specific audit metadata and logs
- recalculation requirements after ignore/restore/reattribute
- UI/authorization rules for payment correction actions
- the boundary between detected-payment interpretation tools and manual adjustment entries

This doc complements [`docs/specs/PARTIAL_PAYMENTS.md`](PARTIAL_PAYMENTS.md). Manual adjustments remain a separate ledger feature; this spec covers correction of detected on-chain rows that should no longer count toward an invoice.

Wrong-invoice cases include stale-address reuse: an old valid CryptoZing invoice address may receive later funds that were intended for a different invoice or business purpose. That is an invoice-attribution problem, not unsupported-wallet evidence by itself.

## Goals
- Give the owner an explicit way to exclude a wrongly attributed on-chain payment from an invoice without deleting the underlying ledger row.
- Give the owner an explicit way to reassign a wrongly attributed payment from one invoice to another invoice owned by the same owner.
- Preserve the original transaction evidence (`txid`, sats, timestamps, metadata, notes) for support/debug/audit use.
- Make the correction reversible so mistaken ignores can be restored cleanly.
- Recompute invoice state immediately so status, outstanding balance, QR/BIP21 targets, and payment summaries stop claiming the wrong payment interpretation.
- Keep the correction surface owner-only and auditable.

## Non-Goals
- Editing the amount, txid, or timestamps of an on-chain payment row.
- Replacing the existing manual-adjustment flow.
- Bulk ignore/restore operations across multiple invoices in RC.
- Granting support agents or public users any correction ability.
- Cross-owner payment reassignment in RC.

## Core Decisions
1. Corrections target individual non-adjustment `invoice_payments` rows only.
2. Ignoring a payment excludes it from settlement math and invoice state, but does not delete the row.
3. Restoring a payment reverses the ignore and returns the row to normal settlement math.
4. Reattributing a payment moves CryptoZing's accounting credit from invoice A to invoice B without changing the blockchain facts.
5. Manual adjustment rows (`is_adjustment = true`) are never eligible for ignore/restore/reattribute.
6. Invoice-to-invoice reattribution is same-owner only in RC.
7. Owner auditability takes precedence over convenience: correction actions require explicit intent and leave a trace.
8. Ignore/restore/reattribute are bookkeeping interpretation tools for detected payments; manual adjustments are separate owner-created ledger entries.

## Scope Boundary
- Legitimate later payments to a correctly assigned old CryptoZing invoice address are correction candidates when the business intent belongs elsewhere, but they do not by themselves prove outside receive activity or a shared wallet namespace.
- Reattribution is internal CryptoZing bookkeeping with preserved provenance and audit trail. It must not claim to mutate or reinterpret blockchain history itself.
- Manual adjustments remain a separate tool for creating an owner-entered accounting fact rather than changing how an existing detected payment is counted.

## Data Model
Shipped ignore/restore metadata lives directly on `invoice_payments`:
- `ignored_at` timestamp nullable
- `ignored_by_user_id` foreign ID nullable, `nullOnDelete()`
- `ignore_reason` string(500) nullable

Rules:
- A row is considered ignored when `ignored_at` is non-null.
- `ignore_reason` is required when a row is ignored.
- Restoring a payment clears `ignored_at`, `ignored_by_user_id`, and `ignore_reason`.
- Ignore/restore never rewrites `txid`, `vout_index`, `sats_received`, `detected_at`, `confirmed_at`, `block_height`, `usd_rate`, `fiat_amount`, `meta`, or `note`.
- No separate RC audit table is required; row metadata plus structured logs is sufficient for this phase.

Planned reattribution must preserve:
- the source invoice where the payment was originally detected
- the destination invoice that now receives the accounting credit
- who reattributed it, when, and why

The exact storage shape may be finalized during implementation, but the provenance requirements above are mandatory.

## Ledger and Recalculation Rules
Ignored rows must be excluded from all owner/client-visible settlement math:
- `Invoice::sumPaymentsUsd()`
- `Invoice::sumPaymentSats()`
- `Invoice::paymentSummary()`
- invoice status recomputation
- invoice outstanding totals / QR / BIP21 amount targeting
- dashboard totals and recent-payment aggregates
- alert threshold checks that depend on paid/outstanding amounts

Ignore/restore/reattribute recomputation must refresh invoice-derived fields, not just the row metadata:
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
- If reattributing a payment moves credit from invoice A to invoice B, both invoices must be recomputed immediately and independently.
- Reattribution must not silently erase the fact that the payment was originally detected on invoice A.

## Watcher and Sync Interaction
Correction metadata must survive watcher runs:
- The watcher must not auto-clear ignored metadata when it re-sees the same txid.
- Ignored rows must not be deleted by dropped-unconfirmed cleanup.
- If the watcher refreshes sats/confirmation metadata for an ignored row, that row remains ignored until the owner restores it.
- Unsupported-configuration evidence already attached to the invoice/wallet is not automatically cleared by ignore/restore in Phase 5.
- A later payment to a legitimate old CryptoZing invoice address should be treated as an invoice-level correction scenario unless separate outside-receive or collision evidence exists.
- Reattribution must not cause watcher sync to duplicate or rediscover the same payment as new money on both invoices.

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

For eligible detected payment rows:
- Show a `Reattribute` action that lets the owner choose another invoice they own as the destination.
- The confirmation step must explain that CryptoZing will stop counting the payment toward the current invoice and start counting it toward the selected invoice instead.
- Require a short reason field for reattribution.
- Destination choices must be constrained to the same owner and must never include the current invoice as a no-op option.

For manual adjustment rows:
- Do not render ignore/restore/reattribute controls.
- Keep existing manual-adjustment labels and note behavior.

UX guardrails:
- Keep the confirmation UI inline to the row or section so keyboard flow remains sane and layout shift stays controlled.
- Preserve typed ignore reason on validation failure.
- Preserve the selected destination invoice and typed reason on reattribution validation failure.
- Focus the first errored field when correction validation fails.

## Public, Print, and Support Surfaces
- Public and print invoice views must exclude ignored rows from payment history and totals entirely.
- Owner invoice views keep ignored or reattributed rows visible for audit, clearly marked with their current accounting state.
- Support read-only surfaces may show the owner-visible ignored state if they already render payment history, but must never expose correction controls.

## Authorization and Routing
- Ignore/restore/reattribute is owner-only.
- Route-model binding must enforce that the payment belongs to the invoice in the URL; mismatches are `404`.
- Public users and support users receive denial/no-control behavior consistent with existing owner-only invoice actions.
- Attempts to ignore or reattribute an adjustment row must fail safely and leave the ledger unchanged.

Preferred route shape:
- `PATCH /invoices/{invoice}/payments/{payment}/ignore`
- `PATCH /invoices/{invoice}/payments/{payment}/restore`
- `PATCH /invoices/{invoice}/payments/{payment}/reattribute`

## Logging and Audit
Every correction action must emit a structured log entry:
- `invoice.payment.ignored`
- `invoice.payment.restored`
- `invoice.payment.reattributed`

Minimum log context:
- `invoice_id`
- `payment_id`
- `user_id`
- `txid`
- `status_before`
- `status_after`

Ignore logs should also include:
- `ignore_reason`

Reattribute logs should also include:
- `source_invoice_id`
- `destination_invoice_id`
- `reattribute_reason`

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
- queued payment-triggered deliveries affected by reattribution must be held, skipped, or regenerated so both source and destination invoices remain truthful

## Testing
Automated coverage should include:
- ignoring a confirmed on-chain payment excludes it from paid/outstanding calculations and reopens invoice state when appropriate
- restoring that payment returns the invoice to the prior truthful state
- owner-only authorization for ignore/restore routes
- reattributing a payment from invoice A to invoice B recomputes both invoices truthfully
- owner-only authorization and same-owner destination safeguards for reattribution
- manual adjustment rows cannot be ignored, restored, or reattributed through the correction flow
- correction metadata persistence (`ignored_at`, `ignored_by_user_id`, `ignore_reason`)
- structured audit log emission for ignore and restore
- provenance/audit persistence for reattribution
- ignored rows remain in owner payment history but disappear from public/print settlement views
- dashboard totals/recent payments exclude ignored rows
- watcher sync does not delete or auto-restore ignored rows
- queued payment-related deliveries that become untruthful are marked skipped, not deleted
- stale-address wrong-invoice cases remain invoice-scoped correction work and do not become unsupported-wallet evidence without additional facts

Browser QA should include:
- owner can ignore a row with a reason and immediately see totals/status change
- owner can restore the row and see totals/status recover
- owner can reattribute a row to another owned invoice and immediately see both invoices recalculate truthfully
- ignored or reattributed rows remain visible and clearly marked in owner history
- ignored rows do not appear in public/print payment history, and reattributed rows only count on the destination invoice
- later payment-triggered mail stays held or skipped appropriately after reattribution
- stale-address wrong-invoice cases do not trigger unsupported-wallet UI by themselves
- manual adjustment rows never show correction controls

## Definition of Done
- Owners can ignore, restore, and reattribute detected payment rows from the invoice show page with explicit confirmation.
- Corrected rows remain auditable with their provenance intact.
- Invoice/payment/dashboard/public math reflects the truthful active accounting after ignore/restore/reattribute.
- Manual adjustments remain untouched by the correction flow.
- Logs and tests cover the correction actions and their recalculation effects.
