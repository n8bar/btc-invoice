# Product Spec
_Last updated: 2026-03-13_

This is the canonical product-level spec for CryptoZing.

Use this file for global behavior, concepts, invariants, and cross-feature requirements.
Use [`docs/PLAN.md`](PLAN.md) for milestone order, current focus, and the primary next doc.
Use detailed docs under `docs/specs/` and `docs/milestones/` for local scope, acceptance criteria, and implementation-impacting notes.

## Canonical Doc Roles
- [`docs/PLAN.md`](PLAN.md): human-facing execution dashboard for RC milestone order, current focus, and the primary next doc.
- [`docs/BACKLOG.md`](BACKLOG.md): post-MVP and deferred work only.
- [`docs/UX_GUARDRAILS.md`](UX_GUARDRAILS.md): global UX, accessibility, and interaction rules.
- `docs/milestones/**`: milestone execution docs when a milestone is active or large enough to need detailed checklist tracking.
- Feature and domain specs under `docs/specs/*.md`: canonical detailed requirements for their local domain.
- `docs/ops/**`: rollout, contributor, and deployment runbooks.
- [`docs/CHANGELOG.md`](CHANGELOG.md): append-only record of scope, requirement, and doc-structure changes.
- `docs/strategies/**`: advisory working docs only; never canonical.
- `docs/qa/**`: findings, verification, and archive/reference docs.

## Product Purpose
CryptoZing is a BTC-native invoicing product.

Owners create invoices in USD, derive a unique Bitcoin receive address per invoice, share public invoice links, monitor on-chain payments, and deliver invoice and receipt emails without giving up custody of funds.

## Principles / Invariants
1. Respect property rights and access to funds.
   Money handling is safety-sensitive; ownership and fund access must be treated with high care.

2. Never trap funds.
   Product convenience must not create a situation where a user loses access to or control over their money.

3. Never create ambiguity about ownership.
   If the app cannot confidently attribute a payment, the system and UI must reflect that uncertainty.

4. Never auto-assume correctness when attribution is uncertain.
   Automatic payment detection is valuable, but it must not silently misattribute funds.

5. Make correction paths explicit and reversible where possible.
   Setup mistakes must have a clear recovery path with preserved auditability.

6. Keep UX honest about product constraints.
   If reliable automatic attribution requires a dedicated account xpub or derivation namespace, the product must say so plainly.

7. Prefer minimal, high-leverage safeguards before operational complexity.
   Add the smallest effective protections first and defer heavy process overhead until adoption requires it.

8. Remain watch-only and never hold spending secrets.
   CryptoZing must never require, collect, store, process, or depend on private keys or seed phrases for normal product operation. Wallet integration is watch-only and limited to public derivation material.

## Cross-Feature Requirements
### Ownership and access
- Client and invoice data are owner-scoped.
- Public links are the only intended unauthenticated invoice surface and must remain public-safe.
- Denied or missing-resource states must avoid leaking owner data and should preserve a clear, friendly recovery path.

### Currency and invoice model
- `amount_usd` is canonical for invoice settlement.
- BTC values are derived display and payment-request values, not the source of truth for settlement.
- New invoices start as `draft`.
- The active invoice lifecycle is `draft`, `sent`, `pending`, `partial`, `paid`, `void`.
- `paid` means confirmed USD value meets or exceeds the expected USD amount; unconfirmed chain activity alone is not sufficient.

### Rates, payments, and outstanding balance
- BTC request amounts, BIP21 links, and QR output must follow the rate behavior defined in [`docs/specs/RATES.md`](specs/RATES.md).
- Per-payment USD snapshots are preserved when on-chain payments are detected so settled USD does not float retroactively with BTC price changes.
- Outstanding payment requests target the current outstanding balance rather than the original invoice BTC snapshot.
- Small-balance resolution, adjustment handling, over/underpayment thresholds, and payment history rules are defined in [`docs/specs/PARTIAL_PAYMENTS.md`](specs/PARTIAL_PAYMENTS.md).
- Confirmation thresholds, RBF handling, and unconfirmed-transaction cleanup rules are defined in [`docs/specs/PARTIAL_PAYMENTS.md`](specs/PARTIAL_PAYMENTS.md).

### Wallets and automatic payment attribution
- On-chain payment detection is a core product feature.
- CryptoZing remains watch-only: wallet setup uses public derivation material only, and no normal product flow may require or accept seed phrases or private keys.
- Reliable automatic attribution requires a dedicated account xpub or derivation namespace for CryptoZing receives.
- Users may view or spend from that account in other wallets, but they should not use that same account for additional receives or address generation outside CryptoZing if they want reliable invoice tracking.
- CryptoZing may flag a wallet configuration as unsupported when it detects outside receive activity or invoice-specific collision evidence that makes automatic payment attribution unreliable.
- Unsupported configuration handling is warning/escalation behavior, not a hard block; the product should direct the user to connect a fresh dedicated account key for continued automatic tracking.
- Invoices created while a wallet is flagged unsupported inherit unsupported state at creation time.
- Existing invoices must not be bulk retroactively marked unsupported; mark an existing invoice unsupported only when invoice-specific evidence implicates that invoice.
- When attribution is uncertain, the product must surface that uncertainty and provide a correction path rather than silently assuming correctness.
- The source finding and locked product decision behind this requirement live in [`docs/qa/Finding1.md`](qa/Finding1.md).

### Public links, print output, and outbound communication
- Public-share and email links must use `APP_PUBLIC_URL`.
- Public and print surfaces must honor public-safe boundaries and status-specific behavior defined in [`docs/specs/PRINT_PUBLIC_POLISH.md`](specs/PRINT_PUBLIC_POLISH.md) and [`docs/milestones/MS13_UX_OVERHAUL.md`](milestones/MS13_UX_OVERHAUL.md).
- Outbound mail in pre-production may be recipient-aliased via `MAIL_ALIAS_ENABLED` and `MAIL_ALIAS_DOMAIN`; this aliasing must be disabled before RC or real-customer use.
- Invoice send, receipt delivery, notification triggers, and delivery logging must remain auditable.

### Background processing and timing
- Automatic payment visibility depends on the background watcher flow described in the payment specs and contributor docs.
- Server-side operational time is anchored to `America/Denver` unless a narrower spec defines a specific display exception.
- Viewer-facing time localization may differ from server calculation time where explicitly documented.

### Active plan-linked requirements
- Automatic attribution hardening must make derivation state key-aware, preserve invoice key identity for auditability, detect and flag unsupported wallet reuse without hard-blocking the owner, snapshot unsupported state onto newly created invoices while avoiding blanket retroactive invoice flagging, reinforce the dedicated-account requirement in wallet and onboarding UX, and provide an auditable correction path for wrongly attributed on-chain payments.
- Mailer and alerts hardening must add duplicate-send safeguards, support owner-editable templates with safe variables and preview/reset flows, and tighten queue and delivery safety around outbound email.
- Mainnet cutover and RC deployment must preserve invoice integrity while switching environments, include a backout path, and verify alias-off mail behavior plus public-link correctness before real-customer rollout.

## Canonical Spec Map
- Rates and BTC/USD behavior: [`docs/specs/RATES.md`](specs/RATES.md)
- Partial payments, confirmations, adjustments, and outstanding summaries: [`docs/specs/PARTIAL_PAYMENTS.md`](specs/PARTIAL_PAYMENTS.md)
- Outbound invoice communication, receipts, and alerts: [`docs/specs/NOTIFICATIONS.md`](specs/NOTIFICATIONS.md)
- Print/public behavior: [`docs/specs/PRINT_PUBLIC_POLISH.md`](specs/PRINT_PUBLIC_POLISH.md)
- MS13 UX overhaul milestone doc: [`docs/milestones/MS13_UX_OVERHAUL.md`](milestones/MS13_UX_OVERHAUL.md)
- Onboarding flow: [`docs/specs/ONBOARD_SPEC.md`](specs/ONBOARD_SPEC.md)
- Wallet import and wallet UX: [`docs/specs/WALLET_XPUB_UX_SPEC.md`](specs/WALLET_XPUB_UX_SPEC.md)
- Cross-cutting UX and accessibility rules: [`docs/UX_GUARDRAILS.md`](UX_GUARDRAILS.md)
- Docs and contributor-experience scope: [`docs/ops/DOCS_DX.md`](ops/DOCS_DX.md)
- Rollout verification: [`docs/ops/RC_ROLLOUT_CHECKLIST.md`](ops/RC_ROLLOUT_CHECKLIST.md)

## Current Cross-Feature Constraints
- Additional wallet storage exists, but invoice-level multi-wallet selection remains deferred to post-MVP and is tracked in [`docs/BACKLOG.md`](BACKLOG.md).
- Mainnet rollout is a plan item, not a background assumption; until that cutover is complete, contributor and deployment docs must keep environment and mail-safety steps explicit.
