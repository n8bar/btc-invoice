# PLAN
_Last updated: 2026-03-13_

This is the human-facing execution dashboard for Release Candidate work.

Open this doc first when resuming work.
Use [`docs/PRODUCT_SPEC.md`](PRODUCT_SPEC.md) for global product behavior and invariants.
Use milestone docs under `docs/milestones/` when a milestone is large or active enough to need a checklist-bearing execution doc.
Use supporting specs under `docs/specs/` for detailed local requirements.
Use [`docs/BACKLOG.md`](BACKLOG.md) for post-MVP work only.

## Current
- Active milestone: **MS14 - On-Chain Payment Attribution Hardening**
- Status: `active`
- Next action: begin MS14 with Phase 1 risk-mitigation work from the strategy doc: preserve users, reset/reseed wallet and invoice test data, rebuild the explicit MS14 invoice/payment scenario set, and fund only the selected `testnet4` cases needed for the clean baseline.
- Primary next doc: [`docs/strategies/MS14_IMPLEMENTATION_STRATEGY.md`](strategies/MS14_IMPLEMENTATION_STRATEGY.md)
- Supporting docs: [`docs/PRODUCT_SPEC.md`](PRODUCT_SPEC.md), [`docs/specs/WALLET_XPUB_UX_SPEC.md`](specs/WALLET_XPUB_UX_SPEC.md), [`docs/qa/Finding1.md`](qa/Finding1.md)
- Most recently completed milestone doc: [`docs/milestones/MS13_UX_OVERHAUL.md`](milestones/MS13_UX_OVERHAUL.md)
- Milestone-doc note: MS14 does not have a dedicated milestone doc yet; create one only if the work grows beyond the current strategy-backed scope.

## Active and Upcoming Milestones
| Status | ID | Milestone | Short intent | Primary doc |
|---|---|---|---|---|
| [ ] | 14 | On-Chain Payment Attribution Hardening | Make attribution key-aware, reinforce dedicated-account usage, and provide auditable correction tooling. | [`docs/strategies/MS14_IMPLEMENTATION_STRATEGY.md`](strategies/MS14_IMPLEMENTATION_STRATEGY.md) |
| [ ] | 15 | Mailer & Alerts Polish + Audit | Tighten alert behavior, cooldowns, editable templates, and queue/delivery safeguards without changing the core send/receipt model. | [`docs/specs/NOTIFICATIONS.md`](specs/NOTIFICATIONS.md) |
| [ ] | 16 | Docs & DX | Keep contributor docs current, document notification coverage, and rationalize the test suite before RC closeout. | [`docs/ops/DOCS_DX.md`](ops/DOCS_DX.md) |
| [ ] | 17 | Mainnet Cutover Preparation | Define and rehearse env flips, wallet validation, mail sanity, and backout steps for mainnet. | [`docs/ops/RC_ROLLOUT_CHECKLIST.md`](ops/RC_ROLLOUT_CHECKLIST.md) |
| [ ] | 18 | CryptoZing.app Deployment (RC) | Deploy the RC under `cryptozing.app`, remove temporary mail aliasing, and complete rollout verification. | [`docs/ops/RC_ROLLOUT_CHECKLIST.md`](ops/RC_ROLLOUT_CHECKLIST.md) |

## Completed Milestones
| Status | ID | Milestone | Short intent | Primary doc |
|---|---|---|---|---|
| [x] | 13 | UX Overhaul | Deliver dashboard/theme/help/onboarding/settings IA and close Task 13 Browser QA. | [`docs/milestones/MS13_UX_OVERHAUL.md`](milestones/MS13_UX_OVERHAUL.md) |
| [x] | 12 | Payment & Address Accuracy | Correct derivation mismatches and lock confirmation-aware payment accuracy. | [`docs/specs/PARTIAL_PAYMENTS.md`](specs/PARTIAL_PAYMENTS.md) |
| [x] | 11 | Observability & Safety | Add safety checks, structured logging, and failure-path hardening. | - |
| [x] | 10 | User Settings | Add invoice defaults and stabilize wallet/settings behavior. | - |
| [x] | 9 | Print & Public Polish | Align print/public output with branding, status, and public-state expectations. | [`docs/specs/PRINT_PUBLIC_POLISH.md`](specs/PRINT_PUBLIC_POLISH.md) |
| [x] | 8 | Invoice Delivery & Auto Receipts | Add invoice send flow, delivery logging, and automatic paid receipts. | [`docs/specs/NOTIFICATIONS.md`](specs/NOTIFICATIONS.md) |
| [x] | 7 | Partial Payments & Outstanding Summaries | Record multiple payments, preserve USD snapshots, and surface outstanding balance behavior. | [`docs/specs/PARTIAL_PAYMENTS.md`](specs/PARTIAL_PAYMENTS.md) |
| [x] | 6 | Blockchain Payment Detection | Poll chain activity for invoice addresses and update invoice payment state automatically. | - |
| [x] | 5 | Wallet Onboarding & Derived Addresses | Add wallet-key onboarding and per-invoice derived receive addresses. | [`docs/specs/WALLET_XPUB_UX_SPEC.md`](specs/WALLET_XPUB_UX_SPEC.md) |
| [x] | 4 | Rate & Currency Correctness | Lock USD-canonical rate behavior and shared formatting rules. | [`docs/specs/RATES.md`](specs/RATES.md) |
| [x] | 3 | Test Hardening | Add baseline feature coverage for public/share, rates, and trash/restore flows. | [`docs/qa/tests/TEST_HARDENING.md`](qa/tests/TEST_HARDENING.md) |
| [x] | 2 | Invoice UX Foundations | Establish invoice CRUD, status flow, BTC/USD display, and public sharing basics. | - |
| [x] | 1 | Ownership & Access | Enforce strict owner boundaries and safe denied-state UX. | - |
