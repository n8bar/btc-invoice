# Product Spec
_Last updated: 2026-03-28_

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
- [`docs/CHANGELOG.log`](CHANGELOG.log): append-only record of scope, requirement, and doc-structure changes.
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
- Temporary support access may exist only when the owner explicitly grants CryptoZing tech support time-limited read-only access to that owner's invoices and clients.
- Support access must expire automatically, remain revocable by the owner at any time, and must not broaden into wallet/settings/write access.
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
- Small-balance resolution, adjustment handling, manual-adjustment reversal, over/underpayment thresholds, and payment history rules are defined in [`docs/specs/PARTIAL_PAYMENTS.md`](specs/PARTIAL_PAYMENTS.md). Manual adjustments are append-only ledger rows; owner-facing undo is handled by creating an equal-and-opposite reversal entry rather than editing or deleting the original row in place.
- Confirmation thresholds, RBF handling, and unconfirmed-transaction cleanup rules are defined in [`docs/specs/PARTIAL_PAYMENTS.md`](specs/PARTIAL_PAYMENTS.md).
- Owner correction handling for wrongly attributed on-chain payments is defined in [`docs/specs/PAYMENT_CORRECTIONS.md`](specs/PAYMENT_CORRECTIONS.md); this includes wrong-invoice cases caused by later use of an old valid CryptoZing invoice address. Ignored rows must remain auditable while being excluded from active settlement math, owner/support audit views may still show the ignored state, reattributed payments must remain visible in owner audit history on both source and destination invoices, active reattributions must expose an explicit undo path back to the immutable source invoice and must not offer ignore until that undo returns the payment there, source-invoice public/print surfaces must not show or count reattributed-out payments, destination-invoice public/print surfaces must show and count the active payment without exposing source-invoice provenance or related-invoice links, public/print and dashboard settlement surfaces must exclude ignored rows, queued payment-related deliveries that become untruthful after a correction must be skipped rather than deleted, and destructive invoice deletion must be treated as a purge path that is blocked at both the app and persistence layers until related bookkeeping history has been intentionally removed or resolved.

### Wallets and automatic payment attribution
- On-chain payment detection is a core product feature.
- CryptoZing remains watch-only: wallet setup uses public derivation material only, and no normal product flow may require or accept seed phrases or private keys.
- Reliable automatic attribution requires a dedicated account xpub or derivation namespace for CryptoZing receives.
- Users may view or spend from that account in other wallets, but they should not use that same account for additional receives or address generation outside CryptoZing if they want reliable invoice tracking.
- CryptoZing-issued invoice addresses remain valid indefinitely on-chain; later inbound BTC to an old valid invoice address may still be attributed to that invoice even when the sender intended a different invoice or business purpose.
- Wallet settings, onboarding guidance, unsupported-state warnings, and public Helpful Notes content are all valid ways to communicate this requirement; Helpful Notes is one reinforcing surface, not the whole safeguard.
- CryptoZing may flag a wallet configuration as unsupported when it detects outside receive activity or invoice-specific collision evidence that makes automatic payment attribution unreliable.
- Later use of a correctly assigned old CryptoZing invoice address is an invoice-attribution problem by default, not unsupported-wallet evidence by itself.
- Unsupported configuration handling is warning/escalation behavior, not a hard block; the product should direct the user to connect a fresh dedicated account key for continued automatic tracking.
- Invoices created while a wallet is flagged unsupported inherit unsupported state at creation time.
- Existing invoices must not be bulk retroactively marked unsupported; mark an existing invoice unsupported only when invoice-specific evidence implicates that invoice.
- When attribution is uncertain, the product must surface that uncertainty and provide a correction path rather than silently assuming correctness.
- The source finding and locked product decision behind this requirement live in [`docs/qa/Finding1.md`](qa/Finding1.md).

### Public links, print output, and outbound communication
- Public-share and email links must use `APP_PUBLIC_URL`.
- Public and print surfaces must honor public-safe boundaries and status-specific behavior defined in [`docs/specs/PRINT_PUBLIC_POLISH.md`](specs/PRINT_PUBLIC_POLISH.md) and [`docs/milestones/13_UX_OVERHAUL.md`](milestones/13_UX_OVERHAUL.md).
- Outbound mail in pre-production may be recipient-aliased via `MAIL_ALIAS_ENABLED` and `MAIL_ALIAS_DOMAIN`; this aliasing must be disabled before RC or real-customer use.
- Invoice send, receipt delivery, notification triggers, and delivery logging must remain auditable.

### Background processing and timing
- Automatic payment visibility depends on the background watcher flow described in the payment specs and contributor docs.
- Server-side operational time is anchored to `America/Denver` unless a narrower spec defines a specific display exception.
- Viewer-facing time localization may differ from server calculation time where explicitly documented.

### Active plan-linked requirements
- Automatic attribution hardening must make derivation state key-aware, preserve invoice key identity for auditability, detect and flag unsupported wallet reuse without hard-blocking the owner, snapshot unsupported state onto newly created invoices while avoiding blanket retroactive invoice flagging, distinguish unsupported shared-wallet evidence from stale-address wrong-invoice reuse, reinforce the dedicated-account requirement in wallet and onboarding UX, and provide an auditable correction path for wrongly attributed on-chain payments.
- Mailer and alerts hardening must prevent duplicate outbound mail, support owner-editable templates with safe variables and preview/reset flows, and keep payment-triggered outbound communication truthful and reviewable, including low-information payment acknowledgments plus owner-reviewed client receipts; the detailed outbound-mail rules live in [`docs/specs/NOTIFICATIONS.md`](specs/NOTIFICATIONS.md).
- Post-payment onboarding should remain lightweight in RC: Part 1 still gets owners to first invoice delivery, while Part 2 should activate only once a first paid invoice is receipt-eligible, suspend when ignore/reattribution review would make a receipt untruthful, and complete when the owner sends the first reviewed client receipt.
- Mainnet cutover and RC deployment must preserve invoice integrity while switching environments, include a backout path, and verify alias-off mail behavior plus public-link correctness before real-customer rollout.

## Canonical Spec Map
- Rates and BTC/USD behavior: [`docs/specs/RATES.md`](specs/RATES.md)
- Partial payments, confirmations, adjustments, and outstanding summaries: [`docs/specs/PARTIAL_PAYMENTS.md`](specs/PARTIAL_PAYMENTS.md)
- Payment correction / ignore-restore behavior: [`docs/specs/PAYMENT_CORRECTIONS.md`](specs/PAYMENT_CORRECTIONS.md)
- Outbound invoice communication, receipts, and alerts: [`docs/specs/NOTIFICATIONS.md`](specs/NOTIFICATIONS.md)
- Print/public behavior: [`docs/specs/PRINT_PUBLIC_POLISH.md`](specs/PRINT_PUBLIC_POLISH.md)
- MS13 UX overhaul milestone doc: [`docs/milestones/13_UX_OVERHAUL.md`](milestones/13_UX_OVERHAUL.md)
- Onboarding flow: [`docs/specs/ONBOARD_SPEC.md`](specs/ONBOARD_SPEC.md)
- Post-payment onboarding: [`docs/specs/POST_PAYMENT_ONBOARDING.md`](specs/POST_PAYMENT_ONBOARDING.md)
- Wallet import and wallet UX: [`docs/specs/WALLET_XPUB_UX_SPEC.md`](specs/WALLET_XPUB_UX_SPEC.md)
- Support access: [`docs/specs/SUPPORT_ACCESS.md`](specs/SUPPORT_ACCESS.md)
- Cross-cutting UX and accessibility rules: [`docs/UX_GUARDRAILS.md`](UX_GUARDRAILS.md)
- Docs and contributor-experience scope: [`docs/ops/DOCS_DX.md`](ops/DOCS_DX.md)
- Rollout verification: [`docs/ops/RC_ROLLOUT_CHECKLIST.md`](ops/RC_ROLLOUT_CHECKLIST.md)

## Current Cross-Feature Constraints
- Additional wallet storage exists, but invoice-level multi-wallet selection remains deferred to post-MVP and is tracked in [`docs/BACKLOG.md`](BACKLOG.md).
- Mainnet rollout is a plan item, not a background assumption; until that cutover is complete, contributor and deployment docs must keep environment and mail-safety steps explicit.
