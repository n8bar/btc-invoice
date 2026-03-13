# MS14 Implementation Strategy (Working Doc)

Status: Advisory implementation strategy for Milestone 14.
Date: 2026-03-07

This is a working execution plan for MS14 and is not canonical scope. Canonical scope remains in `docs/PLAN.md`, `docs/PRODUCT_SPEC.md`, and `docs/qa/Finding1.md`.

## Canonical Inputs
- `docs/PLAN.md`
- `docs/PRODUCT_SPEC.md`
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

## Risks and Mitigations
1. Risk: incorrect backfill guesses for historical invoices.
- Mitigation: start with a historical-data risk pass. Because the current dataset is still test-only, we may choose to delete/reset ambiguous invoices instead of preserving them. If any historical data is kept, retain explicit `matched` / `inferred` / `unknown` buckets and do not fabricate certainty when lineage is not provable.

2. Risk: cursor regression causing address reuse.
- Mitigation: enforce floor checks (`highest_assigned_for_key + 1`) and add regression tests around key switching.

3. Risk: correction tooling used as normal workflow.
- Mitigation: strong warning copy, confirmation step, and audit logs.

4. Risk: rollout complexity touches watcher + invoice math simultaneously.
- Mitigation: ship in the phased order below, with focused tests and dry-run command checks at each step.

## Phases

### Phase 1 - Historical Data Risk Mitigation
Address the current historical-data uncertainty before changing runtime lineage behavior.

#### 1.1 Current-data risk scan
Inventory the existing dataset so we know how much historical ambiguity we are carrying.

- Count invoices with `derivation_index` but no future key identity fields.
- Count invoices with payments detected before invoice creation.
- Compare stored invoice addresses against re-derived addresses from each user's current wallet key.
- Identify invoices whose owner no longer has a wallet row.

#### 1.2 Historical data handling policy
Choose how to handle ambiguous historical invoices before backfill/runtime work begins.

- Because the current dataset is still test-only, allow deletion or full reset of ambiguous invoices instead of preserving them purely for completeness.
- If historical invoices are kept, use explicit `matched` / `inferred` / `unknown` handling and avoid pretending ambiguous rows are safe.
- Document the chosen policy before continuing into runtime lineage work.

#### 1.3 Verification
Run all checks through Sail.

Automated / scripted:
- Re-run the risk scan after any cleanup/reset so the baseline is explicit.
- If cleanup tooling is added, verify it reports what it removed or preserved.

Manual QA:
- Review suspicious invoice sets and confirm the chosen keep/delete/reset policy matches the current test-data reality.
- Confirm the working dataset is in the state we want before Phase 2 begins.

### Phase 2 - Key Lineage + Cursor Model
Create a durable per-key cursor ledger and treat `wallet_settings` as the active key pointer.

#### 2.1 Schema + model foundation
Add the schema and base model support first, without changing watcher behavior yet.

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

Fingerprint rule:
- Normalize xpub input the same way validation does (trim/remove whitespace).
- Compute `key_fingerprint = sha256("<network>|<normalized_xpub>")`.
- Store the hex digest; do not store plaintext xpub outside existing encrypted columns.

#### 2.2 Wallet save + invoice create behavior
Once the schema is in place, switch the write path to the key-aware cursor model.

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

#### 2.3 Watcher/sync behavior
After new invoices are writing key lineage correctly, shift the watcher/sync read path to invoice-bound lineage.

1. Payment watch/sync
- Resolve network from invoice snapshot (`wallet_network`) first.
- Log and skip when invoice key identity is missing/invalid after migration window.
- Avoid coupling sync correctness to whichever key is currently saved in wallet settings.

#### 2.4 Backfill and recovery support
Handle historical data and operator recovery after the forward paths are in place.

1. Add `wallet:backfill-key-lineage` command:
- Populate cursor rows for active keys.
- Backfill `invoices.wallet_key_fingerprint`/`wallet_network` where deterministically inferable.
- Emit report buckets: `matched`, `inferred`, `unknown`.

2. Extend existing address reassignment tooling only after lineage is in place, so corrections preserve key identity columns.
3. Add any operator runbook notes that the backfill/recovery path requires once the command behavior is settled.

#### 2.5 Verification
Run all checks through Sail.

Automated:
- `./vendor/bin/sail artisan test` at minimum for merge-ready Phase 2 work.
- Add/expand coverage for:
  - key switch forward/back cursor resume behavior
  - invoice creation persisting key identity
  - watcher using invoice-bound network/lineage
  - lineage backfill command output/reporting

Manual QA:
- Reproduce the shared-account false-paid scenario in test fixtures after the chosen Phase 1 data policy is applied.
- Sanity-run:
  - `./vendor/bin/sail artisan wallet:watch-payments`
  - `./vendor/bin/sail artisan wallet:assign-invoice-addresses --dry-run`
  - new lineage/correction commands introduced during Phase 2

### Phase 3 - Dedicated-Wallet UX Hardening

#### 3.1 Wallet settings copy
Update wallet settings copy to explicitly state:
- Use a dedicated account xpub for CryptoZing receives.
- Sharing the same account for receives elsewhere can cause false payment attribution.
- Viewing/spending from that account elsewhere is fine.

#### 3.2 Onboarding reinforcement
Add onboarding reinforcement in wallet step:
- concise warning block + link to Helpful Notes anchor.
- optional explicit acknowledgment checkbox if Browser QA shows warning copy is ignored.

#### 3.3 Guardrails and telemetry
Keep guardrails from `docs/UX_GUARDRAILS.md`:
- no layout shift from warning blocks.
- preserved input on validation failures.
- keyboard/focus sanity for any new controls.

- Add event/log entries when users save wallet settings after seeing dedicated-account guidance (for support/debug traceability).

#### 3.4 Verification
Run all checks through Sail.

Automated:
- `./vendor/bin/sail artisan test` at minimum for merge-ready Phase 3 work.
- Add/expand coverage for wallet-settings copy/flow changes and any onboarding reinforcement that ships.

Manual QA:
- Verify dedicated-account warning clarity on wallet settings.
- Verify onboarding reinforcement is understandable and does not introduce layout shift or focus regressions.
- Confirm any telemetry/logging added for guidance acknowledgment is emitted as expected.

### Phase 4 - Correction Tooling + Safeguards

#### 4.1 Correction metadata
Add correction metadata to `invoice_payments` (or companion audit table):
- `ignored_at`, `ignored_by_user_id`, `ignore_reason`.

#### 4.2 Ignore/restore behavior
Update payment summaries/state logic:
- ignored on-chain rows are excluded from paid/outstanding calculations.
- original tx rows remain stored for audit.

Owner UI on invoice show/payment history:
- `Ignore` action per on-chain payment with warning copy.
- confirmation step requiring explicit intent.
- `Restore` action to reverse mistaken ignores.

Re-run payment state recomputation after ignore/restore.

#### 4.3 Safeguards and auditability
- Only owner can ignore/restore.
- Disallow ignoring manual adjustment rows.
- Log every ignore/restore action with invoice/payment/user IDs.

#### 4.4 Verification
Run all checks through Sail.

Automated:
- `./vendor/bin/sail artisan test` at minimum for merge-ready Phase 4 work.
- Add/expand coverage for:
  - ignore/restore payment recalculation
  - authorization and owner-only safeguards
  - audit logging / metadata persistence

Manual QA:
- Verify correction flow recovers invoice state without deleting raw tx history.
- Confirm ignored rows are excluded from paid/outstanding calculations and restore reverses that cleanly.
- Confirm manual adjustment rows cannot be ignored.

## Exit Criteria for MS14
1. False attribution root cause is structurally mitigated through key-aware lineage and cursor behavior.
2. Wallet/onboarding UX clearly communicates dedicated-account requirement.
3. Operators and owners can recover from shared-account mistakes through auditable correction tooling.
4. QA can reproduce prior failure mode and confirm safe recovery path.
