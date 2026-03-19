# MS14 Implementation Strategy (Working Doc)

Status: Advisory implementation strategy for Milestone 14.
Date: 2026-03-07

This is a working execution plan for MS14 and is not canonical scope. Canonical scope remains in `docs/PLAN.md`, `docs/PRODUCT_SPEC.md`, `docs/specs/WALLET_XPUB_UX_SPEC.md`, `docs/specs/ONBOARD_SPEC.md`, and `docs/qa/Finding1.md`.

## Canonical Inputs
- `docs/PLAN.md`
- `docs/PRODUCT_SPEC.md`
- `docs/specs/WALLET_XPUB_UX_SPEC.md`
- `docs/specs/ONBOARD_SPEC.md`
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
- Mitigation: Phase 1 resets and reseeds the current test-only wallet/invoice/payment dataset instead of preserving ambiguous historical rows. If future non-test data ever needs to be retained, use explicit `matched` / `inferred` / `unknown` buckets and do not fabricate certainty when lineage is not provable.

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
1. [x] Create a database backup first, even though the current dataset is still test-only.
2. [x] Delete existing wallet configuration data, invoices, and invoice-linked payment/delivery test data while keeping existing `users`.
3. [x] Generate fresh `testnet4` account-key material for the reseeded wallet fixtures:
   1. [x] generate new public extended keys for all normal wallet fixtures
   2. [x] generate one deliberate duplicate-key pair for the collision fixture
   3. [x] generate any matching private keys through local developer tooling
   4. [x] store those private keys only in an untracked local path (for example under `.cybercreek/`) outside normal application flows
   5. [x] use only the public extended keys in tracked fixtures and app data
4. [x] Rebuild invoice fixtures around explicit MS14 scenarios:
   1. [x] unpaid sent invoice
   2. [x] exact-paid sent invoice
   3. [x] underpaid sent invoice
   4. [x] overpaid sent invoice
   5. [x] partial-to-paid sent invoice
   6. [x] draft invoice with payment edge cases
   7. [x] deliberate duplicate-key collision fixture
5. [x] Fund only the selected reseeded invoice addresses on `testnet4`, targeting roughly 6-12 total broadcasts across the scenario set. Payment/state expectations remain defined in [`docs/specs/PARTIAL_PAYMENTS.md`](../specs/PARTIAL_PAYMENTS.md).
   1. [x] select the invoice scenarios that actually need on-chain fixtures
   2. [x] derive or collect the target invoice receive addresses after reseeding
   3. [x] fund those addresses from local-only `testnet4` wallet material stored in an untracked path
   4. [x] broadcast the transactions for the selected funded scenarios
6. [x] Keep the duplicate-key fixture isolated and clearly labeled so it remains a controlled MS14 fixture rather than ambient test-data ambiguity.
7. [x] Leave outbound mail out of scope for this reseeding pass; mail restoration and queue cleanup remain MS15 work.
8. [x] Document the resulting scenario set before continuing into Phase 2 runtime lineage work.

#### 1.2 Verify the reseeded MS14 baseline
Run all checks through Sail.

Automated / scripted:
1. [x] Verify reseeded wallet/invoice fixtures cover the intended MS14 scenario set.
2. [x] Confirm CryptoZing detects each funded `testnet4` payment and establishes the intended Phase 1 baseline using the existing scheduler/manual paths already documented in [`AGENTS.md`](../../AGENTS.md) and [`docs/ops/DOCS_DX.md`](../ops/DOCS_DX.md).
   - Current local result on 2026-03-14: all funded payments were detected. The deliberate duplicate-address fixture reproduced the known MS14 bug by attaching the same tx to both invoices `57` and `58`.
3. [x] If cleanup/reseed tooling is added, verify it reports what it removed and what it recreated.
4. [x] Confirm the deliberate duplicate-key fixture is isolated and clearly named.
5. [x] Confirm any private keys used for local `testnet4` funding stay untracked and outside normal application flows.

Browser QA:
6. [x] Review the seeded invoices in the browser and confirm the visible scenario set matches the intended MS14 baseline:
   - invoice `50` owned by `nate@cybercreek.us`: unpaid sent invoice
   - invoice `51` owned by `test1@cybercreek.us`: exact-paid sent invoice, currently `pending`
   - invoice `52` owned by `ChesterTester2@nospam.site`: underpaid sent invoice, currently `pending`
   - invoice `53` owned by `TestTestTest@nospam.site`: overpaid sent invoice, currently `pending`
   - invoice `54` owned by `test4user@nospam.site`: partial-to-paid sent invoice, currently `pending`
   - invoice `55` owned by `antonina12@nospam.site`: draft invoice with exact payment edge, currently still `draft`
   - invoice `56` owned by `antonina12@nospam.site`: draft invoice with partial payment edge, currently still `draft`
   - invoice `57` owned by `tester5@nospam.site`: deliberate duplicate-key source invoice, currently `pending`
   - invoice `58` owned by `invalid-user@nospam.site`: deliberate duplicate-key collision target invoice, currently `pending`
   - Current local note on 2026-03-14 19:44: invoices `51` and `55` were originally seeded as exact-payment fixtures under the discarded fixed `100000.00` rate. After restoring real/current-rate BTC display, they no longer represent exact-paid cases, but they remain useful browser fixtures and exact-paid behavior was already covered in earlier phases.
7. [x] Open invoices `57` and `58` and confirm both are visible as separate records while sharing the same receive address `tb1q5lpzjj7c3pthr6f3qy8tdd4vzjqr7gj487gzy6`.
8. [x] Spot-check the funded invoices in the browser and confirm the current pre-MS14 states match expectations:
   - invoices `51`, `52`, `53`, `54`, `57`, and `58` show detected payments and remain `pending`
   - invoices `55` and `56` remain `draft` even though payments were detected
   - invoice `50` remains the unfunded control case

### Phase 2 - Key Lineage + Cursor Model
Create a durable per-key cursor ledger and remove legacy per-wallet cursor state.

#### 2.1 Add the lineage schema
1. [x] Create `wallet_key_cursors` with `user_id`, `network`, `key_fingerprint`, `next_derivation_index`, `first_seen_at`, and `last_seen_at`.
2. [x] Add a unique index on (`user_id`, `network`, `key_fingerprint`).
3. [x] Add `wallet_key_fingerprint` and `wallet_network` to `invoices` so every newly assigned invoice stores its key lineage snapshot.
4. [x] Drop `wallet_settings.next_derivation_index` so `wallet_key_cursors` becomes the only derivation-state source.
5. [x] Drop `user_wallet_accounts.next_derivation_index` so additional wallet rows remain saved public-key metadata only.
6. [x] Implement key fingerprinting consistently:
   1. [x] normalize xpub input the same way validation does (trim/remove whitespace)
   2. [x] compute `key_fingerprint = sha256("<network>|<normalized_xpub>")`
   3. [x] store the hex digest without storing plaintext xpub outside the existing encrypted columns

#### 2.2 Persist active-key lineage on wallet save and invoice creation
1. [x] Compute the active key fingerprint whenever the primary wallet is saved.
2. [x] Upsert the active key's cursor row when saving wallet settings.
3. [x] Initialize a brand-new key cursor at `0`.
4. [x] Resume from the existing cursor row when a previously used key is saved again.
5. [x] Clamp the active cursor to the safety floor `highest_assigned_for_same_key + 1` before issuing new assignments.
6. [x] Derive each new invoice address from the active key plus the active cursor row.
7. [x] Persist `derivation_index`, `wallet_key_fingerprint`, and `wallet_network` when creating the invoice.
8. [x] Increment only `wallet_key_cursors.next_derivation_index` after a successful invoice create.

#### 2.3 Switch runtime and command paths to invoice-bound lineage
1. [x] Update payment watch/sync to resolve network from `invoices.wallet_network` first.
2. [x] Update payment watch/sync to use `invoices.wallet_key_fingerprint` as the invoice-bound lineage context instead of whichever wallet is currently saved.
3. [x] Log and skip any invoice that still lacks key lineage instead of guessing.
4. [x] Update `wallet:assign-invoice-addresses` to allocate from `wallet_key_cursors` and persist invoice lineage columns with each assignment.
5. [x] Update `reassign-invoice-addresses` so any rewritten address/index also rewrites `wallet_key_fingerprint` and `wallet_network` from the selected key lineage instead of relying on removed wallet-row counters.

#### 2.4 Remove legacy cursor assumptions from tests, fixtures, and notes
1. [x] Update factories and seeders to stop setting removed `next_derivation_index` columns on wallet rows.
2. [x] Update feature and unit tests to assert cursor-ledger behavior instead of wallet-row counters.
3. [x] Remove or update any docs and ops notes that still describe wallet rows as owning derivation state.

#### 2.5 Verify Phase 2
Automated / command verification:
1. [x] Run `./vendor/bin/sail artisan test` at minimum for merge-ready Phase 2 work.
2. [x] Add or expand automated coverage for:
   1. [x] saving a new key creates the expected cursor row
   2. [x] saving a previously used key resumes that key's historical cursor
   3. [x] invoice creation persists key identity and increments only the cursor ledger
   4. [x] watcher behavior uses invoice-bound network/lineage
   5. [x] address-assignment and reassignment commands use the cursor ledger and keep lineage columns in sync
3. [x] Sanity-run:
   1. [x] `./vendor/bin/sail artisan wallet:watch-payments`
   2. [x] `./vendor/bin/sail artisan wallet:assign-invoice-addresses --dry-run`
   3. [x] the updated address-reassignment command path introduced during Phase 2
4. [x] Re-run the shared-account collision fixture from the Phase 1 dataset and confirm Phase 2 behavior follows invoice-bound lineage instead of whichever wallet is currently saved.
   - Current local result on 2026-03-18: after temporarily changing duplicate-fixture user `11`'s current wallet row from `testnet4` to `mainnet`, `./vendor/bin/sail artisan wallet:watch-payments --invoice=57` still resolved invoice `57` through its stored `wallet_network`/`wallet_key_fingerprint` lineage and preserved txid `84cf4ce7488f775a430f196227c8b4aba9f8241a8b3b9d4163ca0a8925062d93` with `80000` sats.

Browser QA:
5. [x] Save a fresh wallet key through `/wallet/settings` and create an invoice through the browser; confirm the flow still works end-to-end after the legacy wallet-row cursor fields are removed.
6. [x] Save a previously used wallet key again through `/wallet/settings`, create another invoice, and confirm the app resumes that key's assignment history instead of reusing an old address.
   - Current local result on 2026-03-18: `Tester1` (`user_id=12`) switched to a second key and created invoice `61` at derivation index `0`, then switched back to the original key and created invoice `62` at derivation index `2`, advancing the original key cursor to `3` instead of reusing an older address.

### Phase 3 - Unsupported Configuration Detection + Flagging
Detect risky wallet reuse, flag the wallet gently but clearly, and snapshot unsupported state only where evidence supports it.

#### 3.1 Persist unsupported wallet and invoice state
1. [x] Add wallet-level unsupported-state fields that capture whether the primary wallet is currently flagged plus enough metadata to explain why it was flagged.
2. [x] Add invoice-level unsupported-state fields so newly created invoices can snapshot wallet unsupported state and existing invoices can be flagged individually when direct evidence implicates them.
3. [x] Keep unsupported-state metadata explicit enough to distinguish proactive detection from evidence-triggered detection.
   - Current implementation on 2026-03-18: wallet settings now persist `unsupported_configuration_active` plus `source` / `reason` / `details` / `flagged_at`; new invoices snapshot that state at creation time; replacing the primary wallet key clears the current wallet-level unsupported flag without retroactively changing older invoice snapshots.

#### 3.2 Detect unsupported wallet activity proactively
1. [x] Inspect the saved primary wallet key for prior outside receive activity when the owner saves or replaces the wallet.
2. [x] Flag the wallet as unsupported when proactive detection finds prior activity that makes automatic tracking unreliable.
3. [x] Allow the owner to continue after save instead of hard-blocking the wallet flow.
4. [x] Treat outside receive activity as the trigger; spending elsewhere alone is not enough to flag the wallet unsupported.
   - Current implementation on 2026-03-18: wallet save now scans a bounded window of derived external receive addresses through the existing mempool client, ignores receive history on addresses already owned by this same key lineage inside CryptoZing, flags the wallet when an unknown derived receive address shows prior incoming funds, and leaves spend-only history unflagged.

#### 3.3 Detect unsupported cases from invoice/payment evidence
1. [x] Flag the wallet as unsupported when watcher or lineage evidence later shows collision-style activity that undermines automatic attribution.
2. [x] Mark only the implicated existing invoice unsupported when the evidence is specific to that invoice.
3. [x] Do not retroactively bulk-mark older invoices unsupported without invoice-specific evidence.
4. [x] Mark every newly created invoice unsupported while the wallet remains flagged unsupported.
   - Current implementation on 2026-03-18: payment sync now treats a paid shared-address collision as invoice/payment evidence, marks each invoice using that paid address unsupported, flags the current wallet only when the invoice lineage still matches the owner's currently saved primary key, and leaves unrelated older invoices untouched.

#### 3.4 Surface unsupported state in app chrome and wallet flows
1. [x] Show red attention UI only when the wallet is actually flagged unsupported:
   1. [x] an attention-grabbing label near the user menu
   2. [x] a red dot on the Settings nav item
   3. [x] a red dot on the Wallet settings tab
   4. [x] a red warning near the wallet account key field
2. [x] Keep the warning copy gentle and corrective:
   1. [x] explain that CryptoZing found wallet activity outside its dedicated receive flow
   2. [x] explain that automatic tracking is no longer reliable for this wallet account
   3. [x] direct the owner to connect a fresh dedicated account key
3. [x] Mark invoices created while the wallet is flagged unsupported as unsupported in their own UI/state so that replacing the wallet later does not silently make them look safe.
   - Current implementation on 2026-03-18: app chrome now shows an `Unsupported configuration` label near the user menu plus red dots on Settings and Wallet only while the wallet is flagged; wallet settings show a gentle corrective warning block; and flagged invoices now display unsupported markers in the invoice list and on the invoice detail page.

#### 3.5 Verify Phase 3
Automated / command verification:
1. Run `./vendor/bin/sail artisan test` at minimum for merge-ready Phase 3 work.
2. Add or expand automated coverage for:
   1. proactive unsupported-state detection when a saved wallet shows prior outside receive activity
   2. evidence-triggered wallet flagging from invoice/payment collisions
   3. new invoices inheriting unsupported state while the wallet is flagged
   4. existing invoices remaining unflagged unless invoice-specific evidence marks them
   5. unsupported-state UI indicators appearing only when the wallet is actually flagged

Browser QA:
3. Save a wallet key that triggers proactive unsupported-state detection and confirm the owner can continue while the app shows the red warning state only in the intended places.
4. Confirm the wallet warning language stays gentle and points the owner toward connecting a new dedicated account key.
5. Create a new invoice while the wallet is flagged and confirm the invoice is marked unsupported at creation time.
6. Confirm an older invoice is not retroactively marked unsupported unless evidence for that specific invoice triggers it.

### Phase 4 - Dedicated-Wallet UX Hardening

#### 4.1 Update wallet settings copy
1. Update wallet settings copy to explicitly state that CryptoZing expects a dedicated account xpub for receives.
2. Explain that sharing the same account for receives elsewhere can cause false payment attribution.
3. Clarify that viewing or spending from that account elsewhere is fine.

#### 4.2 Reinforce the dedicated-account requirement in onboarding
1. Add onboarding reinforcement in the wallet step with a concise warning block and link to the Helpful Notes anchor.
2. Add an explicit acknowledgment checkbox only if Browser QA shows the warning copy is being ignored.

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
3. Confirm any telemetry or logging added for guidance acknowledgment is emitted as expected.

Browser QA:
4. Verify dedicated-account warning clarity on wallet settings.
5. Verify onboarding reinforcement is understandable and does not introduce layout shift or focus regressions.
6. Verify the Helpful Notes explainer is understandable to a less technical audience and matches the in-app warning language.

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
3. Verify raw tx history remains present after ignore/restore.

Browser QA:
4. Exercise the correction flow in the browser and confirm visible invoice state recovers as expected.
5. Confirm ignored rows are excluded from paid/outstanding calculations and restore reverses that cleanly.
6. Confirm manual adjustment rows cannot be ignored.

## Exit Criteria for MS14
1. False attribution root cause is structurally mitigated through key-aware lineage and cursor behavior.
2. Unsupported wallet reuse can be detected and flagged without hard-blocking the owner, and unsupported invoice state is applied only where creation-time state or invoice-specific evidence supports it.
3. Wallet/onboarding/help UX clearly communicates the dedicated-account requirement.
4. Operators and owners can recover from shared-account mistakes through auditable correction tooling.
5. QA can reproduce the prior failure mode and confirm the flagging/recovery path.
