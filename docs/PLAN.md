# PROJECT PLAN — Bitcoin Invoice Generator
_Last updated: 2025-11-16_

> Maintained by Codex – this document is updated whenever PRs land or the delivery plan changes.

## Overview
A Laravel application for generating and sharing Bitcoin invoices. Users can manage clients and invoices, lock BTC/USD rates, surface BIP21 links + copy + QR, provide public share links, and print friendly invoices. Focus areas: strict ownership enforcement, friendly 403 UX, accurate BTC calculations, and a deployment-ready experience.

## Stack / Environment
- Laravel 12 + PHP 8.4 with Breeze + Tailwind UI.
- Dockerized via Laravel Sail; containers: `laravel.test` (PHP-FPM/Nginx) and `mysql`.
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
    - Sail command `wallet:watch-payments` polls mempool.space (testnet4/mainnet) for each invoice address, stores tx metadata, logs partials, and auto-marks invoices paid once confirmations hit the threshold.
    - `MempoolClient` caches tip height lookups while `InvoicePaymentDetector` enforces sat tolerance + confirmation thresholds for all invoices with wallet settings.
    - Scheduler runs `wallet:watch-payments` every minute without overlapping so invoices update continuously in the background.
    - Docker Compose ships a dedicated `scheduler` service that runs `php artisan schedule:work`, so local/dev stacks keep the watcher alive automatically (2025-11-14).
7. **Partial Payments & Outstanding Summaries (codex/partial-payments)**
    - `invoice_payments` table (plus `wallet:backfill-payments`) stores every transaction with sats, USD snapshot, optional notes, and tip detection backed by `Invoice::PAYMENT_SAT_TOLERANCE`.
    - Invoice show/print/public views now surface USD-first Expected/Received/Outstanding totals, outstanding-targeted QR/BIP21 links, and payment history tables with editable owner notes.
    - `InvoicePaymentDetector` + watcher refresh the outstanding balance after each detection so `partial` status, `paid_at`, and tolerance handling stay accurate everywhere.
    - Owners can record manual adjustments for significant discrepancies, and clients see over/under-payment alerts once the variance exceeds 15% (overpayment alert reminds them gratuities are default). See [`docs/PARTIAL_PAYMENTS.md`](PARTIAL_PAYMENTS.md) for the full spec.
    - Proactive partial-payment alerts now warn clients (and notify owners) when multiple payment attempts are detected, and invoice emails/public views remind clients to send the full balance in one payment to avoid extra miner fees.
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
    - Wallet settings gained an “additional wallets” section to stash extra xpubs for the future multi-wallet selector; stored accounts aren’t active yet but keep DNS/Xpub data ready.
    - Invoice creation applies the defaults server-side, and new Feature tests cover both the defaults + multi-wallet storage flow.

## Roadmap to Release Candidate
11. **Observability & Safety**
    - Add structured logging around payment detection, rate fetches, mail queueing/delivery failures, and public link access (invoice/user IDs + IP where appropriate), with a shared formatter.
    - Expose a lightweight health probe (DB + cache) and lay groundwork for metrics (counters for payments processed/mails queued).
    - Harden public/error flows: normalize token handling on public print, enforce external API timeouts, and ensure 403/404/500 templates leak no sensitive data. Keep Mailgun aliasing enforced in non-prod.
    - Validate wallet xpubs on save (derive test) and guard invoice creation with friendly errors when derivation fails; defer richer wallet UX to #13.
12. **Docs & DX**
    - Sail quick start, env vars, and automated onboarding walkthroughs.
    - Post-MVP initiatives live in [`docs/FuturePLAN.md`](FuturePLAN.md).
    - Notifications (paid, past-due, over/under payment) follow [`NOTIFICATIONS.md`](NOTIFICATIONS.md); ensure owner + client emails are covered before RC.
13. **UX Overhaul**
    - Wallet UX improvements (explain xpubs, wallet-specific steps, QR parsing, validation helpers).
    - Dashboard snapshot redesign that surfaces invoice/client health at a glance.
        - Guided onboarding wizard and refreshed invoice public/share layouts.
        - Add an Edit button on the invoice show view that links to the edit form, and return to the show view after saving.
    - User-level customization toggles for the overpayment note and QR refresh reminder, with controls exposed under profile settings.
    - Email templates (client invoices, reminders, alerts) become per-user editable via profile settings so copy can be customized without code changes.
    - **Invoice Settings polish:** revisit the new Invoice Settings page/branding defaults as part of the UX pass—capture copy tweaks, layout improvements, and any additional controls after design feedback.
14. **CryptoZing.app Deployment (RC)**
    - Stand up the cloud environment under `CryptoZing.app` post-UX overhaul and deploy the release candidate.
    - Remove the temporary mail aliasing (set `MAIL_ALIAS_ENABLED=false` / clear the alias domain) so production mail goes to real customer addresses.
    - CryptoZing.app is dedicated to this product—plan DNS/email/infra assuming the root domain and its subdomains are exclusively for the invoice platform.

## Testing Approach
- Execute suite via Sail: `./vendor/bin/sail artisan test`.
- Feature tests emphasize authorization, rate refresh, public tokens, partial payment summaries, delivery queue/log flows, and print/public artifacts.
- Favor model factories and clear policy assertions to keep fixtures simple.
- Currency/rate expectations live in [`docs/RATES.md`](RATES.md) and must stay in sync with controller + service behavior.
- Upcoming coverage is drafted in [`docs/tests/TEST_HARDENING.md`](tests/TEST_HARDENING.md); implement those scenarios next.
- New feature work (delivery enhancements, print/public polish, user settings, alerts, etc.) should follow a TDD flow wherever practical, while existing areas continue to pick up pragmatic coverage as they evolve.

## Decisions & Changelog
| Date (UTC) | Change | Notes |
|------------|--------|-------|
| 2025-11-07 | PR #13 merged | Friendly 403 copy standardized to satisfy Authorization tests. |
| 2025-11-07 | Docker daemon access adjusted | AL9 configured for Sail via socket group. |
| 2025-11-07 | PLAN.md established | Plan maintained by Codex; README links to doc. |
| 2025-11-07 | Added test hardening suites | Public share SEO, rate refresh caching, BIP21 output, and trash/restore flows covered by Sail tests. |
| 2025-11-07 | Documented rate precision | `docs/RATES.md` defines USD/BTC rounding and cache TTL rules. |
| 2025-11-08 | Wallet onboarding & derived addresses | `/wallet/settings`, Node-based derivation, and legacy backfill command landed (codex/phase-a-wallet). |
| 2025-11-10 | Blockchain watcher command wired | `wallet:watch-payments` + mempool client integrated into bootstrap; invoices now auto-mark when payments land. |
| 2025-11-10 | Watcher scheduling automated | Scheduler runs `wallet:watch-payments` every minute with overlap protection + background execution. |
| 2025-11-14 | Partial payments UI + outstanding summaries | `invoice_payments` table shipped with watcher backfill, USD-first summaries, and outstanding-targeted QR/BIP21 output. |
| 2025-11-15 | Invoice delivery + automated receipts | `invoice_deliveries` log, manual send form, queue job, and `auto_receipt_emails` toggle landed (codex/invoice-delivery). |
