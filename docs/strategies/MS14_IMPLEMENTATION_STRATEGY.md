# MS14 Implementation Strategy (Working Doc)

Status: Advisory implementation strategy for Milestone 14.
Date: 2026-03-07

This is a working execution plan for MS14 and is not canonical scope. Canonical scope remains in `docs/PLAN.md` Milestone 14 and `docs/qa/Finding1.md`.

## Canonical Inputs
- `docs/PLAN.md` (Milestone 14)
- `docs/qa/Finding1.md`
- Existing wallet/payment behavior in:
  - `app/Http/Controllers/WalletSettingsController.php`
  - `app/Http/Controllers/InvoiceController.php`
  - `app/Services/InvoicePaymentSyncService.php`
  - `app/Console/Commands/WatchInvoicePayments.php`

## Problem Restatement
Shared account xpub usage causes address collisions, so new invoices can inherit unrelated on-chain history and be marked paid incorrectly. Current derivation state is effectively tied to the current wallet row and does not preserve per-key lineage across key switches.

## Strategy Goals
1. Make derivation state key-aware (not just user-aware) so key switches are safe and reversible.
2. Persist invoice key identity for attribution/audit/debug.
3. Harden UX so users understand dedicated-account requirements before relying on auto-attribution.
4. Provide correction tooling that is explicit and auditable when attribution mistakes happen.

## Non-Goals (for MS14)
- No custodial forwarding/sweeping design.
- No broad multi-wallet receiving routing logic.
- No mailer/template work from MS15.

## Current-State Gaps
1. `wallet_settings.next_derivation_index` tracks only the currently saved key.
2. Invoice records persist `derivation_index` but not key identity.
3. Payment sync/watch uses current wallet/network context (`user.walletSetting`) rather than invoice-bound key context.
4. No owner-facing way to explicitly ignore a wrongly attributed on-chain payment while preserving auditability.

## Proposed Architecture

### A) Key Lineage + Cursor Model (Phase 14.1)
Create a durable per-key cursor ledger and treat `wallet_settings` as the active key pointer.

#### Data model
1. Add `wallet_key_cursors` table:
- `id`
- `user_id` (FK)
- `network`
- `key_fingerprint` (stable deterministic ID derived from normalized xpub + network)
- `next_derivation_index`
- `first_seen_at`, `last_seen_at`
- unique index: (`user_id`, `network`, `key_fingerprint`)

2. Add invoice key lineage columns on `invoices`:
- `wallet_key_fingerprint` (nullable during transition, then required for newly created invoices)
- `wallet_network` (snapshot at assignment time)

3. Keep existing `wallet_settings.next_derivation_index` as a compatibility mirror of the active key cursor during rollout.

#### Fingerprint rule
- Normalize xpub input the same way validation does (trim/remove whitespace).
- Compute `key_fingerprint = sha256("<network>|<normalized_xpub>")`.
- Store the hex digest; do not store plaintext xpub outside existing encrypted columns.

#### Runtime behavior
1. Wallet save/update (`WalletSettingsController@update`)
- Compute active key fingerprint.
- Upsert cursor row for this key.
- Set wallet `next_derivation_index` from cursor with safety floor `max(cursor, highest_assigned_for_same_key + 1)`.
- If key is new: initialize cursor at `0`.
- If switching back to prior key: resume that key's historical cursor.

2. Invoice create (`InvoiceController@store`)
- Derive address from active key + active key cursor.
- Persist `derivation_index`, `wallet_key_fingerprint`, and `wallet_network` on invoice.
- Increment only the active key cursor (and mirror `wallet_settings.next_derivation_index`).

3. Payment watch/sync
- Resolve network from invoice snapshot (`wallet_network`) first.
- Log and skip when invoice key identity is missing/invalid after migration window.
- Avoid coupling sync correctness to whichever key is currently saved in wallet settings.

#### Backfill and safety commands
1. Add `wallet:backfill-key-lineage` command:
- Populate cursor rows for active keys.
- Backfill `invoices.wallet_key_fingerprint`/`wallet_network` where deterministically inferable.
- Emit report buckets: `matched`, `inferred`, `unknown`.

2. Extend existing address reassignment tooling only after lineage is in place, so corrections preserve key identity columns.

### B) Dedicated-Wallet UX Hardening (Phase 14.2)

#### UX updates
1. Update wallet settings copy to explicitly state:
- Use a dedicated account xpub for CryptoZing receives.
- Sharing the same account for receives elsewhere can cause false payment attribution.
- Viewing/spending from that account elsewhere is fine.

2. Add onboarding reinforcement in wallet step:
- concise warning block + link to Helpful Notes anchor.
- optional explicit acknowledgment checkbox if Browser QA shows warning copy is ignored.

3. Keep guardrails from `docs/UX_GUARDRAILS.md`:
- no layout shift from warning blocks.
- preserved input on validation failures.
- keyboard/focus sanity for any new controls.

#### Validation and telemetry
- Add event/log entries when users save wallet settings after seeing dedicated-account guidance (for support/debug traceability).

### C) Correction Tooling + Safeguards (Phase 14.3)

#### Data + behavior
1. Add correction metadata to `invoice_payments` (or companion audit table):
- `ignored_at`, `ignored_by_user_id`, `ignore_reason`.

2. Update payment summaries/state logic:
- ignored on-chain rows are excluded from paid/outstanding calculations.
- original tx rows remain stored for audit.

3. Owner UI on invoice show/payment history:
- `Ignore` action per on-chain payment with warning copy.
- confirmation step requiring explicit intent.
- `Restore` action to reverse mistaken ignores.

4. Re-run payment state recomputation after ignore/restore.

#### Safeguards
- Only owner can ignore/restore.
- Disallow ignoring manual adjustment rows.
- Log every ignore/restore action with invoice/payment/user IDs.

## Delivery Plan (Small PR Slices)
1. Slice 1: schema + models for key cursor/lineage, no behavior switch yet.
2. Slice 2: wallet save + invoice create behavior on cursor ledger.
3. Slice 3: watcher/sync behavior shifted to invoice-bound network/lineage.
4. Slice 4: wallet UX/onboarding dedicated-account hardening.
5. Slice 5: correction tooling (ignore/restore) + invoice payment math updates.
6. Slice 6: backfill/recovery command(s) + operator runbook notes.

## Test Plan

### Automated (required per slice)
- Use Sail for all runs.
- `./vendor/bin/sail artisan test` at minimum for merge-ready slices.
- Add/expand feature coverage for:
  - key switch forward/back cursor resume behavior.
  - invoice creation persisting key identity.
  - watcher using invoice-bound network/lineage.
  - ignore/restore payment recalculation and authorization.
- Add command tests for lineage backfill output/reporting.

### Manual QA (browser + console)
1. Reproduce shared-account false-paid scenario in test fixtures.
2. Verify dedicated-account warning clarity on wallet settings and onboarding.
3. Verify correction flow recovers invoice state without deleting raw tx history.
4. Sanity-run commands:
- `./vendor/bin/sail artisan wallet:watch-payments`
- `./vendor/bin/sail artisan wallet:assign-invoice-addresses --dry-run`
- new lineage/correction commands introduced in MS14.

## Risks and Mitigations
1. Risk: incorrect backfill guesses for historical invoices.
- Mitigation: keep unknown bucket explicit; do not fabricate lineage when not provable.

2. Risk: cursor regression causing address reuse.
- Mitigation: enforce floor checks (`highest_assigned_for_key + 1`) and add regression tests around key switching.

3. Risk: correction tooling used as normal workflow.
- Mitigation: strong warning copy, confirmation step, and audit logs.

4. Risk: rollout complexity touches watcher + invoice math simultaneously.
- Mitigation: ship in slices above, each with focused tests and dry-run command checks.

## Exit Criteria for MS14
1. False attribution root cause is structurally mitigated through key-aware lineage and cursor behavior.
2. Wallet/onboarding UX clearly communicates dedicated-account requirement.
3. Operators and owners can recover from shared-account mistakes through auditable correction tooling.
4. QA can reproduce prior failure mode and confirm safe recovery path.
