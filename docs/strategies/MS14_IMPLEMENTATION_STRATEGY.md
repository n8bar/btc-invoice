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

#### 1.1 Reset and reseed the MS14 baseline
1. Create a database backup first, even though the current dataset is still test-only.
2. Delete existing wallet configuration data, invoices, and invoice-linked payment/delivery test data while keeping existing `users`.
3. Generate fresh `testnet4` account-key material for the reseeded wallet fixtures:
   1. generate new public extended keys for all normal wallet fixtures
   2. generate one deliberate duplicate-key pair for the collision fixture
   3. generate any matching private keys through local developer tooling
   4. store those private keys only in an untracked local path (for example under `.cybercreek/`) outside normal application flows
   5. use only the public extended keys in tracked fixtures and app data
4. Rebuild invoice fixtures around explicit MS14 scenarios:
   1. unpaid sent invoice
   2. exact-paid sent invoice
   3. underpaid sent invoice
   4. overpaid sent invoice
   5. partial-to-paid sent invoice
   6. draft invoice with payment edge cases
   7. deliberate duplicate-key collision fixture
5. Fund only the selected reseeded invoice addresses on `testnet4`, targeting roughly 6-12 total broadcasts across the scenario set. Payment/state expectations remain defined in [`docs/specs/PARTIAL_PAYMENTS.md`](../specs/PARTIAL_PAYMENTS.md).
   1. select the invoice scenarios that actually need on-chain fixtures
   2. derive or collect the target invoice receive addresses after reseeding
   3. fund those addresses from local-only `testnet4` wallet material stored in an untracked path
   4. broadcast the transactions for the selected funded scenarios
6. Keep the duplicate-key fixture isolated and clearly labeled so it remains a controlled MS14 fixture rather than ambient test-data ambiguity.
7. Treat outbound mail as out of scope for this reseeding pass; mail restoration and queue cleanup remain MS15 work.
8. Document the resulting scenario set before continuing into Phase 2 runtime lineage work.

#### 1.2 Verify the reseeded MS14 baseline
Run all checks through Sail.

Automated / scripted:
1. Verify reseeded wallet/invoice fixtures cover the intended MS14 scenario set.
2. Confirm CryptoZing detects each funded `testnet4` payment, attaches it to the expected invoice, and updates payment/state behavior as defined in [`docs/specs/PARTIAL_PAYMENTS.md`](../specs/PARTIAL_PAYMENTS.md), using the existing scheduler/manual paths already documented in [`AGENTS.md`](../../AGENTS.md) and [`docs/ops/DOCS_DX.md`](../ops/DOCS_DX.md).
3. If cleanup/reseed tooling is added, verify it reports what it removed and what it recreated.

Human / Browser QA:
1. Review the reseeded scenario set and confirm it matches the intended MS14 test matrix.
2. Confirm the deliberate duplicate-key fixture is isolated and clearly named.
3. Confirm any private keys used for local `testnet4` funding stay untracked and outside normal application flows.
4. Confirm the working dataset is in the state we want before Phase 2 begins.

### Phase 2 - Key Lineage + Cursor Model
Create a durable per-key cursor ledger and remove legacy per-wallet cursor state.

#### 2.1 Add the lineage schema
1. Create `wallet_key_cursors` with `user_id`, `network`, `key_fingerprint`, `next_derivation_index`, `first_seen_at`, and `last_seen_at`.
2. Add a unique index on (`user_id`, `network`, `key_fingerprint`).
3. Add `wallet_key_fingerprint` and `wallet_network` to `invoices` so every newly assigned invoice stores its key lineage snapshot.
4. Drop `wallet_settings.next_derivation_index` so `wallet_key_cursors` becomes the only derivation-state source.
5. Drop `user_wallet_accounts.next_derivation_index` so additional wallet rows remain saved public-key metadata only.
6. Implement key fingerprinting consistently:
   1. normalize xpub input the same way validation does (trim/remove whitespace)
   2. compute `key_fingerprint = sha256("<network>|<normalized_xpub>")`
   3. store the hex digest without storing plaintext xpub outside the existing encrypted columns

#### 2.2 Persist active-key lineage on wallet save and invoice creation
1. Compute the active key fingerprint whenever the primary wallet is saved.
2. Upsert the active key's cursor row when saving wallet settings.
3. Initialize a brand-new key cursor at `0`.
4. Resume from the existing cursor row when a previously used key is saved again.
5. Clamp the active cursor to the safety floor `highest_assigned_for_same_key + 1` before issuing new assignments.
6. Derive each new invoice address from the active key plus the active cursor row.
7. Persist `derivation_index`, `wallet_key_fingerprint`, and `wallet_network` when creating the invoice.
8. Increment only `wallet_key_cursors.next_derivation_index` after a successful invoice create.

#### 2.3 Switch runtime and command paths to invoice-bound lineage
1. Update payment watch/sync to resolve network from `invoices.wallet_network` first.
2. Update payment watch/sync to use `invoices.wallet_key_fingerprint` as the invoice-bound lineage context instead of whichever wallet is currently saved.
3. Log and skip any invoice that still lacks key lineage instead of guessing.
4. Update `wallet:assign-invoice-addresses` to allocate from `wallet_key_cursors` and persist invoice lineage columns with each assignment.
5. Update `reassign-invoice-addresses` so any rewritten address/index also rewrites `wallet_key_fingerprint` and `wallet_network` from the selected key lineage instead of relying on removed wallet-row counters.

#### 2.4 Remove legacy cursor assumptions from tests, fixtures, and notes
1. Update factories and seeders to stop setting removed `next_derivation_index` columns on wallet rows.
2. Update feature and unit tests to assert cursor-ledger behavior instead of wallet-row counters.
3. Remove or update any docs and ops notes that still describe wallet rows as owning derivation state.

#### 2.5 Verify Phase 2
Automated / command verification:
1. Run `./vendor/bin/sail artisan test` at minimum for merge-ready Phase 2 work.
2. Add or expand automated coverage for:
   1. saving a new key creates the expected cursor row
   2. saving a previously used key resumes that key's historical cursor
   3. invoice creation persists key identity and increments only the cursor ledger
   4. watcher behavior uses invoice-bound network/lineage
   5. address-assignment and reassignment commands use the cursor ledger and keep lineage columns in sync
3. Sanity-run:
   1. `./vendor/bin/sail artisan wallet:watch-payments`
   2. `./vendor/bin/sail artisan wallet:assign-invoice-addresses --dry-run`
   3. the updated address-reassignment command path introduced during Phase 2

Human / Browser QA:
1. Save a fresh wallet key through `/wallet/settings` and create an invoice through the browser; confirm the flow still works end-to-end after the legacy wallet-row cursor fields are removed.
2. Save a previously used wallet key again through `/wallet/settings`, create another invoice, and confirm the app resumes that key's assignment history instead of reusing an old address.
3. Re-run the shared-account collision fixture from the Phase 1 dataset and confirm Phase 2 behavior follows invoice-bound lineage instead of whichever wallet is currently saved.

### Phase 3 - Unsupported Configuration Detection + Flagging
Detect risky wallet reuse, flag the wallet gently but clearly, and snapshot unsupported state only where evidence supports it.

#### 3.1 Persist unsupported wallet and invoice state
1. Add wallet-level unsupported-state fields that capture whether the primary wallet is currently flagged plus enough metadata to explain why it was flagged.
2. Add invoice-level unsupported-state fields so newly created invoices can snapshot wallet unsupported state and existing invoices can be flagged individually when direct evidence implicates them.
3. Keep unsupported-state metadata explicit enough to distinguish proactive detection from evidence-triggered detection.

#### 3.2 Detect unsupported wallet activity proactively
1. Inspect the saved primary wallet key for prior outside receive activity when the owner saves or replaces the wallet.
2. Flag the wallet as unsupported when proactive detection finds prior activity that makes automatic tracking unreliable.
3. Allow the owner to continue after save instead of hard-blocking the wallet flow.
4. Treat outside receive activity as the trigger; spending elsewhere alone is not enough to flag the wallet unsupported.

#### 3.3 Detect unsupported cases from invoice/payment evidence
1. Flag the wallet as unsupported when watcher or lineage evidence later shows collision-style activity that undermines automatic attribution.
2. Mark only the implicated existing invoice unsupported when the evidence is specific to that invoice.
3. Do not retroactively bulk-mark older invoices unsupported without invoice-specific evidence.
4. Mark every newly created invoice unsupported while the wallet remains flagged unsupported.

#### 3.4 Surface unsupported state in app chrome and wallet flows
1. Show red attention UI only when the wallet is actually flagged unsupported:
   1. an attention-grabbing label near the user menu
   2. a red dot on the Settings nav item
   3. a red dot on the Wallet settings tab
   4. a red warning near the wallet account key field
2. Keep the warning copy gentle and corrective:
   1. explain that CryptoZing found wallet activity outside its dedicated receive flow
   2. explain that automatic tracking is no longer reliable for this wallet account
   3. direct the owner to connect a fresh dedicated account key
3. Mark invoices created while the wallet is flagged unsupported as unsupported in their own UI/state so that replacing the wallet later does not silently make them look safe.

#### 3.5 Verify Phase 3
Automated / command verification:
1. Run `./vendor/bin/sail artisan test` at minimum for merge-ready Phase 3 work.
2. Add or expand automated coverage for:
   1. proactive unsupported-state detection when a saved wallet shows prior outside receive activity
   2. evidence-triggered wallet flagging from invoice/payment collisions
   3. new invoices inheriting unsupported state while the wallet is flagged
   4. existing invoices remaining unflagged unless invoice-specific evidence marks them
   5. unsupported-state UI indicators appearing only when the wallet is actually flagged

Human / Browser QA:
1. Save a wallet key that triggers proactive unsupported-state detection and confirm the owner can continue while the app shows the red warning state only in the intended places.
2. Confirm the wallet warning language stays gentle and points the owner toward connecting a new dedicated account key.
3. Create a new invoice while the wallet is flagged and confirm the invoice is marked unsupported at creation time.
4. Confirm an older invoice is not retroactively marked unsupported unless evidence for that specific invoice triggers it.

### Phase 4 - Dedicated-Wallet UX Hardening

#### 4.1 Update wallet settings copy
1. Update wallet settings copy to explicitly state that CryptoZing expects a dedicated account xpub for receives.
2. Explain that sharing the same account for receives elsewhere can cause false payment attribution.
3. Clarify that viewing or spending from that account elsewhere is fine.

#### 4.2 Reinforce the dedicated-account requirement in onboarding
1. Add onboarding reinforcement in the wallet step with a concise warning block and link to the Helpful Notes anchor.
2. Add an explicit acknowledgment checkbox only if Human / Browser QA shows the warning copy is being ignored.

#### 4.3 Publish a Helpful Notes explainer for less technical users
1. Add a public Helpful Notes article that explains CryptoZing's watch-only model in plain language.
2. Explain why automatic payment tracking needs a dedicated receiving account key.
3. Explain that spending elsewhere is fine, but receiving elsewhere with the same account key makes automatic attribution unreliable.
4. Explain what unsupported configuration means and why the recommended fix is to connect a fresh dedicated account key.
5. Recommend separate receive and spend apps or accounts as the safest pattern without making separate apps a hard product requirement.

#### 4.4 Keep dedicated-wallet guidance usable and traceable
1. Apply `docs/UX_GUARDRAILS.md` so dedicated-wallet guidance does not introduce layout shift.
2. Preserve wallet input on validation failures.
3. Keep keyboard and focus behavior sane for any new guidance controls.
4. Add event or log entries when users save wallet settings after seeing dedicated-account guidance if support/debug traceability proves necessary.

#### 4.5 Verify Phase 4
Automated / command verification:
1. Run `./vendor/bin/sail artisan test` at minimum for merge-ready Phase 4 work.
2. Add or expand automated coverage for wallet-settings copy/flow changes, any onboarding reinforcement that ships, and any Helpful Notes linkage or rendering that ships with this phase.

Human / Browser QA:
1. Verify dedicated-account warning clarity on wallet settings.
2. Verify onboarding reinforcement is understandable and does not introduce layout shift or focus regressions.
3. Verify the Helpful Notes explainer is understandable to a less technical audience and matches the in-app warning language.
4. Confirm any telemetry or logging added for guidance acknowledgment is emitted as expected.

### Phase 5 - Correction Tooling + Safeguards

#### 5.1 Add correction metadata
Add correction metadata to `invoice_payments` (or companion audit table):
- `ignored_at`, `ignored_by_user_id`, `ignore_reason`.

#### 5.2 Add ignore/restore behavior
1. Update payment summaries and state logic so ignored on-chain rows are excluded from paid/outstanding calculations.
2. Preserve the original tx rows for audit.

Owner UI on invoice show/payment history:
- `Ignore` action per on-chain payment with warning copy.
- confirmation step requiring explicit intent.
- `Restore` action to reverse mistaken ignores.

Re-run payment state recomputation after ignore/restore.

#### 5.3 Keep corrections guarded and auditable
1. Restrict ignore/restore to the owner.
2. Disallow ignoring manual adjustment rows.
3. Log every ignore/restore action with invoice/payment/user IDs.

#### 5.4 Verify Phase 5
Automated / command verification:
1. Run `./vendor/bin/sail artisan test` at minimum for merge-ready Phase 5 work.
2. Add or expand automated coverage for:
   1. ignore/restore payment recalculation
   2. authorization and owner-only safeguards
   3. audit logging and metadata persistence

Human / Browser QA:
1. Verify the correction flow recovers invoice state without deleting raw tx history.
2. Confirm ignored rows are excluded from paid/outstanding calculations and restore reverses that cleanly.
3. Confirm manual adjustment rows cannot be ignored.

## Exit Criteria for MS14
1. False attribution root cause is structurally mitigated through key-aware lineage and cursor behavior.
2. Unsupported wallet reuse can be detected and flagged without hard-blocking the owner, and unsupported invoice state is applied only where creation-time state or invoice-specific evidence supports it.
3. Wallet/onboarding/help UX clearly communicates the dedicated-account requirement.
4. Operators and owners can recover from shared-account mistakes through auditable correction tooling.
5. QA can reproduce the prior failure mode and confirm the flagging/recovery path.
