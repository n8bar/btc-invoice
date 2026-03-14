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

#### 1.1 Reset baseline
1. Treat the current wallet/invoice/payment data as disposable test data.
2. Do not spend time inventorying outgoing rows in detail before reset.
3. Proceed directly to the reset-and-reseed policy below.

#### 1.2 Reset-and-reseed policy
1. Keep existing `users`.
2. Delete and reseed wallet configuration data so runtime lineage work starts from intentional fixtures instead of ambiguous history.
3. Delete and reseed invoices plus invoice-linked payment/delivery test data instead of preserving ambiguous historical rows.
4. Remove accidental duplicate extended keys from normal fixtures.
5. Rebuild invoice fixtures around explicit MS14 scenarios:
   1. unpaid sent invoice
   2. exact-paid sent invoice
   3. underpaid sent invoice
   4. overpaid sent invoice
   5. partial-to-paid sent invoice
   6. draft invoice with payment edge case
   7. deliberate duplicate-key collision fixture
6. Only some invoices need on-chain payments. Fund selected reseeded invoice addresses on `testnet4`, targeting roughly 6-12 total broadcasts across the scenario set. Payment/state expectations remain defined in [`docs/specs/PARTIAL_PAYMENTS.md`](../specs/PARTIAL_PAYMENTS.md).
7. Keep the duplicate-key fixture isolated and clearly labeled so it remains a controlled MS14 fixture rather than ambient test-data ambiguity.
8. Committed fixtures and app behavior may use public derivation material only. Any private keys used to fund local `testnet4` scenarios must remain untracked and outside the product boundary, per [`docs/PRODUCT_SPEC.md`](../PRODUCT_SPEC.md) and [`AGENTS.md`](../../AGENTS.md).
9. At this stage, outbound mail is not part of the reseeding concern; mail restoration and queue cleanup remain MS15 work.
10. Document the resulting scenario set before continuing into Phase 2 runtime lineage work.

#### 1.3 Verification
Run all checks through Sail.

Automated / scripted:
1. Verify reseeded wallet/invoice fixtures cover the intended MS14 scenario set.
2. Fund the selected `testnet4` invoice addresses and confirm watcher observations through the existing scheduler/manual paths already documented in [`AGENTS.md`](../../AGENTS.md) and [`docs/ops/DOCS_DX.md`](../ops/DOCS_DX.md).
3. If cleanup/reseed tooling is added, verify it reports what it removed and what it recreated.

Manual QA:
1. Review the reseeded scenario set and confirm it matches the intended MS14 test matrix.
2. Confirm the deliberate duplicate-key fixture is isolated and clearly named.
3. Confirm any private keys used for local `testnet4` funding stay untracked and outside normal application flows.
4. Confirm the working dataset is in the state we want before Phase 2 begins.

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
