# MS14 - On-Chain Payment Attribution Hardening

Status: Active as of 2026-03-23.
Parent execution doc: [`docs/PLAN.md`](../PLAN.md)
Supporting docs: [`docs/PRODUCT_SPEC.md`](../PRODUCT_SPEC.md), [`docs/specs/PAYMENT_CORRECTIONS.md`](../specs/PAYMENT_CORRECTIONS.md), [`docs/specs/PARTIAL_PAYMENTS.md`](../specs/PARTIAL_PAYMENTS.md), [`docs/specs/WALLET_XPUB_UX_SPEC.md`](../specs/WALLET_XPUB_UX_SPEC.md), [`docs/specs/ONBOARD_SPEC.md`](../specs/ONBOARD_SPEC.md), [`docs/qa/Finding1.md`](../qa/Finding1.md)

This is the milestone execution doc for MS14. It tracks milestone-level objectives plus phase-level progress only.

## Milestone Objectives
- Make invoice attribution key-aware so wallet-key changes do not reuse old receive history or cursors incorrectly.
- Preserve invoice-level key lineage for attribution, auditability, and debugging.
- Detect unsupported shared-wallet reuse without over-flagging legitimate stale-address wrong-invoice cases.
- Reinforce the dedicated receiving-account requirement across wallet setup, onboarding, and help surfaces.
- Provide auditable owner correction tooling for wrongly attributed on-chain payments.

## Current Focus
- Active phase: **Phase 5 - Correction Tooling + Safeguards**
- Current objective: finish same-owner invoice-to-invoice reattribution, complete the remaining bookkeeping delete safeguards, and rerun the rewritten Phase 5 Browser QA.
- Canonical Phase 5 requirements: [`docs/specs/PAYMENT_CORRECTIONS.md`](../specs/PAYMENT_CORRECTIONS.md)

## Phase Rollup
1. [x] Phase 1 - Historical Data Risk Mitigation
   - Outcome: the local MS14 baseline was reset/reseeded with a controlled duplicate-key collision fixture plus funded `testnet4` scenarios for later attribution and correction verification.
2. [x] Phase 2 - Key Lineage + Cursor Model
   - Outcome: per-key cursor tracking shipped, invoices now persist wallet lineage snapshots, and watcher/address-assignment flows use invoice-bound lineage instead of the currently saved wallet row.
3. [x] Phase 3 - Unsupported Configuration Detection + Flagging
   - Outcome: proactive and evidence-driven unsupported-state handling shipped with invoice-level scoping, warning UI, and safe repair guidance.
4. [x] Phase 4 - Dedicated-Wallet UX Hardening
   - Outcome: wallet, onboarding, and Helpful Notes surfaces now reinforce the dedicated receiving-account requirement with completed Browser QA follow-up.
5. [ ] Phase 5 - Correction Tooling + Safeguards
   - Outcome target: owner correction flows support ignore, restore, and reattribute with truthful recalculation, auditable history, and destructive-delete safeguards.
   - Remaining work: same-owner reattribution, the remaining destructive-delete backstop/guidance work, and the final Phase 5 automated/Browser QA.

## Exit Criteria
- False-attribution root cause is structurally mitigated through key-aware lineage and cursor behavior.
- Unsupported wallet reuse can be detected and flagged without hard-blocking the owner, while stale-address wrong-invoice cases remain correction work rather than unsupported-wallet evidence by default.
- Wallet, onboarding, and help UX clearly communicate the dedicated receiving-account requirement.
- Owners and operators can recover from wrong-invoice attribution through auditable correction tooling.
- Verification reproduces the historical failure mode and confirms the supported flagging and recovery paths end-to-end.
