# MS14 - On-Chain Payment Attribution Hardening

Status: Active as of 2026-03-25.
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
- Current objective: address the Browser QA findings for the shipped ignore, restore, and reattribute correction tooling.
- Canonical Phase 5 requirements: [`docs/specs/PAYMENT_CORRECTIONS.md`](../specs/PAYMENT_CORRECTIONS.md)

## Phase Rollup
1. [x] Phase 1 - Historical Data Risk Mitigation
   Reset/reseeded the controlled MS14 baseline with funded `testnet4` scenarios and the duplicate-key collision fixture.
2. [x] Phase 2 - Key Lineage + Cursor Model
   Shipped per-key cursor tracking and invoice-bound lineage for invoice creation, reassignment, and watcher flows.
3. [x] Phase 3 - Unsupported Configuration Detection + Flagging
   Shipped proactive and evidence-based unsupported-state handling with invoice-level scoping, warning UI, and repair guidance.
4. [x] Phase 4 - Dedicated-Wallet UX Hardening
   Shipped dedicated-account guidance across wallet, onboarding, and Helpful Notes, with Browser QA complete.
5. [ ] Phase 5 - Correction Tooling + Safeguards
   Browser QA is complete; remaining work is the follow-up findings fix pass for the shipped ignore, restore, and reattribute correction tooling.

## Exit Criteria
- False-attribution root cause is structurally mitigated through key-aware lineage and cursor behavior.
- Unsupported wallet reuse can be detected and flagged without hard-blocking the owner, while stale-address wrong-invoice cases remain correction work rather than unsupported-wallet evidence by default.
- Wallet, onboarding, and help UX clearly communicate the dedicated receiving-account requirement.
- Owners and operators can recover from wrong-invoice attribution through auditable correction tooling.
- Verification reproduces the historical failure mode and confirms the supported flagging and recovery paths end-to-end.
