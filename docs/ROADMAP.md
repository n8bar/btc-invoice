# Roadmap (RC)
_Last updated: 2026-03-10_

This is the canonical roadmap doc for Release Candidate work.

Use this file for milestone order, status, dependency, and short intent only.
Use [`docs/PRODUCT_SPEC.md`](PRODUCT_SPEC.md) for global product behavior and invariants.
Use detailed specs under `docs/specs/` for requirements, acceptance criteria, and edge cases.
Use [`docs/BACKLOG.md`](BACKLOG.md) for post-MVP work only.

## Completed Milestones
| ID | Milestone | Status | Short intent |
|---|---|---|---|
| 1 | Ownership & Access | Completed | Enforce strict owner boundaries and safe denied-state UX. |
| 2 | Invoice UX Foundations | Completed | Establish invoice CRUD, status flow, BTC/USD display, and public sharing basics. |
| 3 | Test Hardening | Completed | Add baseline feature coverage for public/share, rates, and trash/restore flows. |
| 4 | Rate & Currency Correctness | Completed | Lock USD-canonical rate behavior and shared formatting rules. |
| 5 | Wallet Onboarding & Derived Addresses | Completed | Add wallet-key onboarding and per-invoice derived receive addresses. |
| 6 | Blockchain Payment Detection | Completed | Poll chain activity for invoice addresses and update invoice payment state automatically. |
| 7 | Partial Payments & Outstanding Summaries | Completed | Record multiple payments, preserve USD snapshots, and surface outstanding balance behavior. |
| 8 | Invoice Delivery & Auto Receipts | Completed | Add invoice send flow, delivery logging, and automatic paid receipts. |
| 9 | Print & Public Polish | Completed | Align print/public output with branding, status, and public-state expectations. |
| 10 | User Settings | Completed | Add invoice defaults and stabilize wallet/settings behavior. |
| 11 | Observability & Safety | Completed | Add safety checks, structured logging, and failure-path hardening. |
| 12 | Payment & Address Accuracy | Completed | Correct derivation mismatches and lock confirmation-aware payment accuracy. |
| 13 | UX Overhaul | Completed | Deliver dashboard/theme/help/onboarding/settings IA and close Task 13 Browser QA. |

## Release Candidate Milestones
14. **On-Chain Payment Attribution Hardening** — `active`
   Depends on: MS13 complete.
   Intent: make attribution key-aware, reinforce the dedicated-account requirement, and provide safe correction tooling for shared-account mistakes.
   Canonical detail: [`docs/PRODUCT_SPEC.md`](PRODUCT_SPEC.md), [`docs/qa/Finding1.md`](qa/Finding1.md), [`docs/specs/WALLET_XPUB_UX_SPEC.md`](specs/WALLET_XPUB_UX_SPEC.md).

15. **Mailer & Alerts Polish + Audit** — `planned`
   Depends on: MS14 payment attribution behavior being stable enough to audit downstream alerts.
   Intent: tighten alert behavior, cooldowns, editable templates, and queue/delivery safeguards without changing the product’s core send/receipt model.
   Canonical detail: [`docs/PRODUCT_SPEC.md`](PRODUCT_SPEC.md), [`docs/specs/NOTIFICATIONS.md`](specs/NOTIFICATIONS.md).

16. **Docs & DX** — `planned`
   Depends on: live UX and notification behavior being stable enough to document accurately.
   Intent: keep contributor docs current, document notification coverage, and rationalize the test suite before RC closeout.
   Canonical detail: [`docs/ops/DOCS_DX.md`](ops/DOCS_DX.md), [`docs/qa/tests/TEST_HARDENING.md`](qa/tests/TEST_HARDENING.md).

17. **Mainnet Cutover Preparation** — `planned`
   Depends on: MS14 through MS16 being stable enough to rehearse a safe network switch.
   Intent: define and rehearse env flips, wallet validation, mail sanity, and backout steps for mainnet.
   Canonical detail: [`docs/PRODUCT_SPEC.md`](PRODUCT_SPEC.md), [`docs/ops/RC_ROLLOUT_CHECKLIST.md`](ops/RC_ROLLOUT_CHECKLIST.md).

18. **CryptoZing.app Deployment (RC)** — `planned`
   Depends on: successful mainnet cutover preparation and rollout readiness checks.
   Intent: deploy the RC under `cryptozing.app`, remove temporary mail aliasing, and complete rollout verification.
   Canonical detail: [`docs/ops/RC_ROLLOUT_CHECKLIST.md`](ops/RC_ROLLOUT_CHECKLIST.md), [`docs/PRODUCT_SPEC.md`](PRODUCT_SPEC.md).
