# PROJECT PLAN — Bitcoin Invoice Generator
_Last updated: 2026-03-06_

> Maintained by Codex – this document is updated whenever PRs land or the delivery plan changes.

## Overview
A Laravel application for generating and sharing Bitcoin invoices. Users can manage clients and invoices, lock BTC/USD rates, surface BIP21 links + copy + QR, provide public share links, and print friendly invoices. Focus areas: strict ownership enforcement, friendly 403 UX, accurate BTC calculations, and a deployment-ready experience.

## Stack / Environment
- Laravel 12 + PHP 8.4 with Breeze + Tailwind UI.
- Dockerized via Laravel Sail; containers: `laravel.test` (PHP-FPM/Nginx), `scheduler` (runs `php artisan schedule:work`), and `mysql`.
- Database: MySQL (Sail). SQLite only for specific tests if ever configured.
- QR generation handled server-side via `simplesoftwareio/simple-qrcode`.
- Timezone default on server: **America/Denver**.
- `APP_PUBLIC_URL` controls the absolute domain used in public-share links (emails). In production set it to `https://cryptozing.app` so outbound links never point at localhost.

## Run Rules (Sail Only)
- All PHP/Artisan/Test commands must run through Sail (`./vendor/bin/sail ...`). No host PHP or package installs.
- Bring stack up with `./vendor/bin/sail up -d` before running commands; tear down via `./vendor/bin/sail down` when finished.
- Keep PRs small and outcome-focused; CI is the hard gate before merging.
- Tests reside in `tests/Feature` (plus Unit) and must stay stable/readable.

## Source of Truth & Branching Policy
- GitHub `main` is canonical and protected.
- New work branches follow `codex/<task>`. Existing PRs must be updated via their original source branch—never create alternates.
- Keep branches synced with `origin/main` (merge/rebase) before requesting review.

## CI Gate
- GitHub Actions workflow “PR Tests” runs on pushes to `codex/**`, `feat/**`, `feature/**`, and on PR events.
- Pipeline steps: PHP 8.4 + MySQL services → `composer install` → Vite build → database migrate → `php artisan test`.
- `main` merges require green CI and up-to-date status.

## Time Handling
- Server timezone: America/Denver. Persist timestamps in that zone unless explicitly stored UTC via Laravel defaults.
- Invoice “as of” timestamps on Show page display in viewer-local time via client-side script, while server caches calculations using Denver time.

## Key Paths
- Controllers: `app/Http/Controllers/{InvoiceController,ClientController}.php`.
- Models: `app/Models/{Invoice,Client}.php`.
- Services: `app/Services/BtcRate.php` (rate cache/fetch).
- Views: `resources/views/invoices/{index,create,edit,show,print}.blade.php` and errors (403).
- Routes: `routes/web.php`.
- Shared assets: server-side QR generation.

## Completed Milestones
1. **Ownership & Access**
    - Client/Invoice controllers enforce policies via `authorizeResource`; soft-delete flows (trash/restore/force) are locked down.
    - Shared exception handling renders the friendly 403 copy asserted in Authorization tests.
2. **Invoice UX Foundations**
    - Auto-numbering, status transitions (draft/sent/paid/void + reset-to-draft), and live USD↔BTC conversions across show/print/public views with QR + BIP21 output.
    - Public share links (`/p/{token}`) support enable/disable/rotate/expiry and always render `noindex` headers.
3. **Test Hardening (codex/test-hardening-*)**
    - Feature tests now cover public share lifecycle, SEO/noindex, QR/BIP21 accuracy, soft-delete trash/restore/force, and rate refresh/cache reuse.
4. **Rate & Currency Correctness (codex/rate-precision)**
    - `docs/RATES.md` codifies USD-as-source + 8-decimal BTC rounding; controllers/views share a formatter and stale cache safeguards.
5. **Wallet Onboarding & Derived Addresses (codex/phase-a-wallet)**
    - `/wallet/settings` collects a BIP84 xpub per user (testnet/mainnet); invoice creation now derives a unique Bech32 address via `node_scripts/derive-address.cjs`.
    - Legacy invoices can be backfilled with the `wallet:assign-invoice-addresses` command, restoring QR/BIP21 output everywhere.
6. **Blockchain Payment Detection (codex/blockchain-watcher)**
    - Sail command `wallet:watch-payments` polls mempool.space (testnet3/testnet4/mainnet) for each invoice address, stores tx metadata, logs partials, and auto-marks invoices paid once confirmations hit the threshold.
    - `MempoolClient` caches tip height lookups while `InvoicePaymentDetector` enforces sat tolerance + confirmation thresholds for all invoices with wallet settings.
    - Scheduler runs `wallet:watch-payments` every minute without overlapping so invoices update continuously in the background.
    - Docker Compose ships a dedicated `scheduler` service that runs `php artisan schedule:work`, so local/dev stacks keep the watcher alive automatically (2025-11-14).
7. **Partial Payments & Outstanding Summaries (codex/partial-payments)**
    - `invoice_payments` table (plus `wallet:backfill-payments`) stores every transaction with sats, USD snapshot, optional notes, and tip detection backed by `Invoice::PAYMENT_SAT_TOLERANCE`.
    - Invoice show/print/public views now surface USD-first Expected/Received/Outstanding totals, outstanding-targeted QR/BIP21 links, and payment history tables with editable owner notes.
    - `InvoicePaymentDetector` + watcher refresh the outstanding balance after each detection so `partial` status, `paid_at`, and tolerance handling stay accurate everywhere.
    - Owners can record manual adjustments for significant discrepancies, and clients see over/under-payment alerts once the variance exceeds 15% (overpayment alert reminds them gratuities are default). See [`docs/PARTIAL_PAYMENTS.md`](PARTIAL_PAYMENTS.md) for the full spec.
    - Proactive partial-payment alerts now warn clients (and notify owners) when multiple payment attempts are detected, and invoice emails/public views remind clients to send the full balance in one payment to avoid extra miner fees.
    - Display residuals exactly (no masking via sat tolerance) and expose a “Resolve small balance” control for tiny residuals (threshold = `max($1, min(1% of expected USD, $50))`) that logs a credit adjustment and marks the invoice paid; see updated partial payments spec.
8. **Invoice Delivery & Auto Receipts (codex/invoice-delivery)**
    - `/invoices/{invoice}/deliver` gate-keeps on client email + public share, then queues `DeliverInvoiceMail` jobs that render `InvoiceReadyMail` with optional CC + note.
    - `invoice_deliveries` log table fuels the show page’s delivery log with status, CC, dispatch/sent timestamps, and surfaced errors.
    - `InvoicePaid` events trigger the `SendInvoiceReceipt` listener (respecting each user’s `auto_receipt_emails` toggle) which logs/queues `InvoicePaidReceiptMail` receipts after invoices cross the paid threshold.
    - Pre-production stacks rewrite outbound recipients via `MAIL_ALIAS_ENABLED/MAIL_ALIAS_DOMAIN` (pointed at `mailer.cryptozing.app`) so every message lands in the Mailgun catch-all route; disable this before the RC deploy.
9. **Print & Public Polish (main)**
    - Print + public templates now share the same biller heading/name/contact/footer data with profile defaults + per-invoice overrides rendered via a collapsible “Branding & footer” block on create/edit forms.
    - Public links differentiate active/disabled states, keep the QR + “rate as of” hints polished, and show friendlier expired messaging with owner contact info.
    - Feature tests assert private/public views render the customizable fields; docs/specs (`PRINT_PUBLIC_POLISH.md`) stay in sync with the shipped behavior.
10. **User Settings (main)**
    - Profile page now includes invoice memo + payment-term defaults so new invoices auto-fill description/due dates when owners leave those fields blank.
    - Additional wallet storage remains in the backend, but the UI is deferred to post-RC until multi-wallet selection is in scope (see [`docs/FuturePLAN.md`](FuturePLAN.md)).
    - Wallet settings are now mainnet-first: network derives from `WALLET_NETWORK` env, the selector is removed, and the UI only surfaces a testnet badge/helper in non-mainnet stacks while keeping additional wallets on the configured network.
    - Wallet key columns (`wallet_settings.bip84_xpub`, `user_wallet_accounts.bip84_xpub`) now use `TEXT` so encrypted xpub payloads fit without truncation failures.
    - Invoice creation applies the defaults server-side, and new Feature tests cover both the defaults + multi-wallet storage flow.
11. **Observability & Safety (main)**
    - Structured logs cover payment detection, rate fetches, mail queueing/delivery failures, and public link access (invoice/user IDs + IP where appropriate).
    - Health probe added (DB + cache); external API timeouts enforced; 403/404/500 and public-print flows hardened to avoid leaks; Mailgun aliasing remains enforced in non-prod.
    - Wallet xpubs are validated on save via a derive test; invoice creation guards derivation failures with a friendly redirect to wallet settings.
12. **Payment & Address Accuracy (main)**
    - Legacy derivation mismatches corrected via `wallet:reassign-invoice-addresses` (supports `--include-paid --reset-payments --use-next-index`), moving all affected invoices to the proper external chain and advancing wallet indices.
    - Verified on 2025-12-06: invoices 7/8/10 (testnet, indices 11/12/13) derive correctly from the stored xpub; watcher sanity runs (`wallet:watch-payments --invoice=7,8,10`) processed paid/partial states without derivation issues.
    - Payment/confirmation behavior documented (USD canonical, per-payment rate locking, floating BTC outstanding) and outstanding sats now clamp to zero once USD is settled to avoid residual dust after adjustments.

## Roadmap: Milestones to Release Candidate
13. **UX Overhaul**
    - Spec: [`docs/UX_OVERHAUL_SPEC.md`](UX_OVERHAUL_SPEC.md) captures scope and Definition of Done.
    - Onboarding wizard details: [`docs/ONBOARD_SPEC.md`](ONBOARD_SPEC.md) is the working spec for UX Task 11.
    - Wallet/Xpub UX details: [`docs/WALLET_XPUB_UX_SPEC.md`](WALLET_XPUB_UX_SPEC.md) captures the shipped wallet UX scope.
    - UX guardrails: apply [`docs/UX_GUARDRAILS.md`](UX_GUARDRAILS.md) across all UX work (Nielsen/WCAG + form/error/accessibility norms).
    - [x] Dashboard snapshot and light/dark theme toggle.
    - [x] Helpful Notes: public, context-linked explanations (`/help`, starting with xpub safety + why we ask; treat as an SEO surface and link it from landing).
    - [x] Wallet UX improvements (xpub guidance, network cues, validation helpers).
    - [x] Invoices & Clients UI polish across CRUD surfaces (show/edit, print/public/share, delivery/receipts, trash/restore); client detail can route to edit until a dedicated show view is needed.
    - [x] Public/share layout refresh to mirror updated show/print patterns; friendly disabled/expired states.
      - Completed per the Task 10 checklist in [`docs/UX_OVERHAUL_SPEC.md`](UX_OVERHAUL_SPEC.md): shared print/public template rendering, explicit active vs disabled/expired public states, public-safe controls only, and narrower-screen public/share sanity verification.
    - [x] Guided onboarding wizard: wallet setup → create invoice → deliver.
      - Implemented per [`docs/ONBOARD_SPEC.md`](ONBOARD_SPEC.md): `/getting-started` step shells, dismiss/reopen, derived progress, contextual success redirects, and progress strips on wallet/invoice pages.
      - v1 auto-show scope is login redirect + dashboard/invoice empty-state/menu prompts (no global route interception middleware).
    - [x] Login entry now routes incomplete users into `/getting-started` (replacing the temporary `/wallet/settings` redirect bridge).
    - [x] Task 12: Profile communication toggles + targeted settings/auth consistency pass.
      - [x] Add per-user toggles (default on): overpayment gratuity note + QR refresh reminder.
      - [x] Wire toggles into invoice show/public/print client-facing copy only; keep owner operational warnings visible.
      - [x] Keep settings/auth pass implementation-light (Profile + Invoice Settings + Login/Logout consistency; no auth-flow redesign).
    - [ ] Task 13: Invoice settings and invoice UX finish-up from Task12 deltas.
      - Execution order: lightweight pre-implementation Browser QA baseline -> implementation -> post-implementation Browser QA acceptance/regression pass.
      - Priorities: heading/footer/address microcopy clarity, focus/error parity, and save-state consistency.
      - Preserve existing per-invoice override behavior; no structural redesign.
      - Include invoice create simplification: remove create-time status selection and force all new invoices to start as `draft`; users can set another status after creation.
      - Require client email for create/edit flows and harden schema by making `clients.email` non-null (include safe backfill/migration handling for existing null data).
      - Expand paid-print state visibility: show a large translucent diagonal `PAID` watermark on paid owner prints as well as active public prints.
      - Refine client-facing payment exception copy to use biller/brand identity (invoice billing name with fallback) instead of generic “invoice sender” wording.
      - Remove payment-action surfaces on paid invoices across owner/client views: hide QR + BIP21/copy actions on owner show, owner print, and active public print to prevent accidental rescans/overpayments.
      - Add a “Reset to my custom defaults” action near the top of create/edit `Branding & footer` sections so per-invoice overrides can be reverted to Invoice Settings defaults in one click.
      - Hide the editable invoice-level `TXID` field from owner edit UI while keeping backend/internal compatibility for legacy/manual/recovery workflows.
      - Move the owner invoice Footer note card to render immediately before Payment Details instead of near the top status/action area.
    - Deferred post-MVP: invoice/client list searching and filtering (tracked in [`docs/FuturePLAN.md`](FuturePLAN.md) Item 1).
    - Note: keep docs/quick start in sync after UX changes land.
14. **On-Chain Payment Attribution Hardening**
    - Source finding: [`docs/qa/Finding1.md`](qa/Finding1.md) documents the shared account xpub collision issue (new invoices falsely inheriting unrelated on-chain payments).
    - **Phase 14.1 — Wallet key lineage + derivation cursor safety (architecture prerequisite)**
      - Maintain per-key history/cursors (fingerprint + network + next derivation index) so derivation state is key-aware.
      - Enforce key-aware NDI behavior: same key resumes/clamps safely; new key starts at derivation index 0; switching back resumes that key’s prior cursor.
      - Persist key identity on invoices alongside derivation index so attribution and recovery behavior remain auditable.
    - **Phase 14.2 — Dedicated-wallet UX hardening**
      - Lock in reliable on-chain payment detection as a core feature by requiring a dedicated account xpub/derivation namespace for automatic attribution.
      - Update Wallet Settings/onboarding guidance to explain the dedicated-account requirement, the shared-account collision risk, and that viewing/spending from the account in other wallets is fine, but using that same account for additional receives/address generation outside CryptoZing is not.
      - Review onboarding/wallet flows for where to acknowledge or reinforce this requirement.
    - **Phase 14.3 — Correction tooling + safeguards**
      - Add corrective tooling to void/ignore wrongly attributed on-chain payments for shared-account mistakes, with strong warning copy (escape hatch, not the recommended workflow).
    - Verification: reproduce the shared-account false-paid scenario in QA, confirm guidance clarity, and confirm correction tooling can recover invoice state safely.
15. **Mailer & Alerts Polish + Audit**
    - Revisit the mailer pipeline and alerting flows (under/over/partial, past-due, receipts) to ensure cooldowns, deduping, and queue processing behave correctly.
    - Add per-user editable email templates (invoice send, reminders/alerts) with safe variables, preview + reset-to-default, and validation; verify/test them during or late in MS15 alongside the mailer audit.
    - Add a per-invoice notice cooldown guard: do not send the same notice content/class for the same invoice within a configurable threshold unless the sender explicitly chooses a follow-up class (for example, “Second notice”).
    - Validate queue worker configuration, delivery logs, and error handling; tighten safeguards to prevent runaway enqueues and confirm aliasing/production modes.
    - Backfill any missing specs/tests for mail/alert behavior, document operational runbooks for mail queue health, and review/refresh all customer-facing email copy (wording + tone); align with [`docs/NOTIFICATIONS.md`](NOTIFICATIONS.md) and update it as needed.
    - Verification: one alias-off drill in a safe env (DKIM/SPF/DMARC + links) and observe queue/backoff/alerts.
16. **Docs & DX**
    - Spec: [`docs/DOCS_DX_SPEC.md`](DOCS_DX_SPEC.md) defines the deliverables and Definition of Done.
    - Sail-first quick start and contributor walkthrough docs now live at [`docs/get-live/QUICK_START.md`](get-live/QUICK_START.md) and [`docs/get-live/CONTRIBUTOR_WALKTHROUGH.md`](get-live/CONTRIBUTOR_WALKTHROUGH.md) (clone → `./vendor/bin/sail up -d` → migrate/seed → wallet → invoice → deliver).
    - Add any future contributor walkthrough polish (screenshots, new flows) in those docs and keep env references current.
    - Align notifications with [`docs/NOTIFICATIONS.md`](NOTIFICATIONS.md): document which mails are live (paid, past-due, over/under), which are stubbed, and where they’re tested.
    - Keep RC-scoped work in this PLAN; route anything deferred to [`docs/FuturePLAN.md`](FuturePLAN.md) with a brief pointer here.
    - Definition of Done: the quick start + contributor walkthrough docs exist and match current UX, notification coverage is documented and tested, and PLAN/FuturePLAN reflect what’s in vs. out for RC.
17. **Mainnet Cutover Preparation**
    - Plan and execute the switch from testnet to mainnet once UX/mail audits are stable. Define env flips, wallet/xpub validation on mainnet, and a pilot send on mainnet before general availability.
    - Create a backout plan and audit steps to ensure existing testnet invoices remain intact or are clearly segregated.
    - Verification: mainnet dress rehearsal (env flip in staging, sample invoice/address derivation, watcher sanity, mail/send sanity) with sign-off.
18. **CryptoZing.app Deployment (RC)**
    - Stand up the cloud environment under `CryptoZing.app` post-UX overhaul and deploy the release candidate.
    - Remove the temporary mail aliasing (set `MAIL_ALIAS_ENABLED=false` / clear the alias domain) so production mail goes to real customer addresses.
    - CryptoZing.app is dedicated to this product—plan DNS/email/infra assuming the root domain and its subdomains are exclusively for the invoice platform.
    - Verification: RC rollout checklist (APP_PUBLIC_URL, alias flip, migrate/test, smoke send, public-link sanity); see [`docs/RC_ROLLOUT_CHECKLIST.md`](RC_ROLLOUT_CHECKLIST.md).

## Testing Approach
- Execute suite via Sail: `./vendor/bin/sail artisan test`.
- Feature tests emphasize authorization, rate refresh, public tokens, partial payment summaries, delivery queue/log flows, and print/public artifacts.
- Favor model factories and clear policy assertions to keep fixtures simple.
- Currency/rate expectations live in [`docs/RATES.md`](RATES.md) and must stay in sync with controller + service behavior.
- Upcoming coverage is drafted in [`docs/tests/TEST_HARDENING.md`](tests/TEST_HARDENING.md); implement those scenarios next.
- New feature work (delivery enhancements, print/public polish, user settings, alerts, etc.) should follow a TDD flow wherever practical, while existing areas continue to pick up pragmatic coverage as they evolve.

## Decisions & Changelog
See [`docs/CHANGELOG.md`](CHANGELOG.md) for the running changelog.
