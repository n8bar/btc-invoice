# PLAN
_Last updated: 2026-03-26_

This is the human-facing execution dashboard for Release Candidate work.

Open this doc first when resuming work.
Use [`docs/PRODUCT_SPEC.md`](PRODUCT_SPEC.md) for global product behavior and invariants.
Use milestone docs under `docs/milestones/` when a milestone is large or active enough to need a checklist-bearing execution doc.
Use supporting specs under `docs/specs/` for detailed local requirements.
Use [`docs/BACKLOG.md`](BACKLOG.md) for post-MVP work only.

## Current
- Active milestone: **MS15 - CryptoZing.app SEO Bootstrap**
- Status: `active`
- Next action: draft the MS15 milestone doc and scope the placeholder/landing-page SEO bootstrap work.
- Primary next doc: [`docs/PLAN.md`](PLAN.md)
- Supporting docs: [`site/index.html`](../site/index.html), [`site/sitemap.xml`](../site/sitemap.xml), [`site/robots.txt`](../site/robots.txt), [`docs/PRODUCT_SPEC.md`](PRODUCT_SPEC.md)
- Most recently completed milestone doc: [`docs/milestones/MS14_PAYMENT_ATTRIBUTION_HARDENING.md`](milestones/MS14_PAYMENT_ATTRIBUTION_HARDENING.md)

## Active and Upcoming Milestones
| Status | ID | Milestone | Short intent | Primary doc |
|---|---|---|---|---|
| [ ] | 15 | CryptoZing.app SEO Bootstrap | Get the placeholder/landing page discovered, indexed, and positioned for continuity before the live app landing page replaces it at the same URL. | [`docs/PLAN.md`](PLAN.md) |
| [ ] | 16 | Mailer & Alerts Polish + Audit | Tighten alert behavior, cooldowns, editable templates, and queue/delivery safeguards without changing the core send/receipt model. | [`docs/specs/NOTIFICATIONS.md`](specs/NOTIFICATIONS.md) |
| [ ] | 17 | Docs, DX & Support UX | Keep contributor docs current, document notification coverage, complete and harden the minimum viable support UI and access flow for RC, add auth/password policy hardening, and rationalize the test suite before RC closeout. | [`docs/ops/DOCS_DX.md`](ops/DOCS_DX.md) |
| [ ] | 18 | Mainnet Cutover Preparation | Define and rehearse env flips, wallet validation, mail sanity, and backout steps for mainnet. | [`docs/ops/RC_ROLLOUT_CHECKLIST.md`](ops/RC_ROLLOUT_CHECKLIST.md) |
| [ ] | 19 | CryptoZing.app Deployment (RC) | Deploy the RC under `cryptozing.app`, remove temporary mail aliasing, and complete rollout verification. | [`docs/ops/RC_ROLLOUT_CHECKLIST.md`](ops/RC_ROLLOUT_CHECKLIST.md) |

## Completed Milestones
| Status | ID | Milestone | Short intent | Primary doc |
|---|---|---|---|---|
| [x] | 1 | Ownership & Access | Enforce strict owner boundaries and safe denied-state UX. | - |
| [x] | 2 | Invoice UX Foundations | Establish invoice CRUD, status flow, BTC/USD display, and public sharing basics. | - |
| [x] | 3 | Test Hardening | Add baseline feature coverage for public/share, rates, and trash/restore flows. | [`docs/qa/tests/TEST_HARDENING.md`](qa/tests/TEST_HARDENING.md) |
| [x] | 4 | Rate & Currency Correctness | Lock USD-canonical rate behavior and shared formatting rules. | [`docs/specs/RATES.md`](specs/RATES.md) |
| [x] | 5 | Wallet Onboarding & Derived Addresses | Add wallet-key onboarding and per-invoice derived receive addresses. | [`docs/specs/WALLET_XPUB_UX_SPEC.md`](specs/WALLET_XPUB_UX_SPEC.md) |
| [x] | 6 | Blockchain Payment Detection | Poll chain activity for invoice addresses and update invoice payment state automatically. | - |
| [x] | 7 | Partial Payments & Outstanding Summaries | Record multiple payments, preserve USD snapshots, and surface outstanding balance behavior. | [`docs/specs/PARTIAL_PAYMENTS.md`](specs/PARTIAL_PAYMENTS.md) |
| [x] | 8 | Invoice Delivery & Auto Receipts | Add invoice send flow, delivery logging, and automatic paid receipts. | [`docs/specs/NOTIFICATIONS.md`](specs/NOTIFICATIONS.md) |
| [x] | 9 | Print & Public Polish | Align print/public output with branding, status, and public-state expectations. | [`docs/specs/PRINT_PUBLIC_POLISH.md`](specs/PRINT_PUBLIC_POLISH.md) |
| [x] | 10 | User Settings | Add invoice defaults and stabilize wallet/settings behavior. | - |
| [x] | 11 | Observability & Safety | Add safety checks, structured logging, and failure-path hardening. | - |
| [x] | 12 | Payment & Address Accuracy | Correct derivation mismatches and lock confirmation-aware payment accuracy. | [`docs/specs/PARTIAL_PAYMENTS.md`](specs/PARTIAL_PAYMENTS.md) |
| [x] | 13 | UX Overhaul | Deliver dashboard/theme/help/onboarding/settings IA and close Task 13 Browser QA. | [`docs/milestones/MS13_UX_OVERHAUL.md`](milestones/MS13_UX_OVERHAUL.md) |
| [x] | 14 | On-Chain Payment Attribution Hardening | Make attribution key-aware, detect unsupported wallet reuse, reinforce dedicated-account usage, and provide auditable correction tooling. | [`docs/milestones/MS14_PAYMENT_ATTRIBUTION_HARDENING.md`](milestones/MS14_PAYMENT_ATTRIBUTION_HARDENING.md) |
