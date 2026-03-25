# MS14 Phase 1 Strategy - Historical Data Risk Mitigation

Status: Completed.
Parent milestone doc: [`docs/milestones/MS14_PAYMENT_ATTRIBUTION_HARDENING.md`](../milestones/MS14_PAYMENT_ATTRIBUTION_HARDENING.md)

Address the current historical-data uncertainty before changing runtime lineage behavior.

## 1.1 Reset and reseed the MS14 baseline
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

## 1.2 Verify the reseeded MS14 baseline
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
