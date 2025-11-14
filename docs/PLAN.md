# PROJECT PLAN — Bitcoin Invoice Generator
_Last updated: 2025-11-14_

> Maintained by Codex – this document is updated whenever PRs land or the delivery plan changes.

## Overview
A Laravel application for generating and sharing Bitcoin invoices. Users can manage clients and invoices, lock BTC/USD rates, surface BIP21 links + copy + QR, provide public share links, and print friendly invoices. Focus areas: strict ownership enforcement, friendly 403 UX, accurate BTC calculations, and a deployment-ready experience.

## Stack / Environment
- Laravel 12 + PHP 8.4 with Breeze + Tailwind UI.
- Dockerized via Laravel Sail; containers: `laravel.test` (PHP-FPM/Nginx) and `mysql`.
- Database: MySQL (Sail). SQLite only for specific tests if ever configured.
- QR generation handled server-side via `simplesoftwareio/simple-qrcode`.
- Timezone default on server: **America/Denver**.

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

## Completed Milestones (2025-11-10)
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

## Roadmap to Release Candidate
7. **Partial Payments & Receipts** — see [`docs/PARTIAL_PAYMENTS.md`](PARTIAL_PAYMENTS.md)
    - Accept underpayments, log each tx (sats + USD snapshot), and surface a `partial` status until totals reach the invoice amount.
    - Increase watcher tolerance (±100 sats) and expose payment history on the invoice view.
8. **Invoice Delivery** — see [`docs/INVOICE_DELIVERY.md`](INVOICE_DELIVERY.md)
    - Queued Mailables with signed public link, delivery logs, and a “Send invoice” form.
    - Logged attempts surface on the invoice page; receipt emails trigger after auto-paid events.
9. **Print & Public Polish**
    - Improve print template spacing/contrast/fonts; tune QR sizing.
    - Public page: lightweight branding, “as of” note, clear disabled/expired states.
10. **User Settings**
    - Per-user invoice defaults (memo/terms) and future multi-wallet options.
11. **Observability & Safety**
    - Structured logs for rate fetches, emails, public access.
    - Ensure 403/404/500 templates are consistent and leak no sensitive data.
12. **Docs & DX**
    - Sail quick start, env vars, and automated onboarding walkthroughs.
    - Post-MVP initiatives live in [`docs/FuturePLAN.md`](FuturePLAN.md).
13. **UX Overhaul**
    - Wallet UX improvements (explain xpubs, wallet-specific steps, QR parsing, validation helpers).
    - Dashboard snapshot redesign that surfaces invoice/client health at a glance.
        - Guided onboarding wizard and refreshed invoice public/share layouts.
14. **CryptoZing.app Deployment (RC)**
    - Stand up the cloud environment under `CryptoZing.app` post-UX overhaul and deploy the release candidate.

## Testing Approach
- Execute suite via Sail: `./vendor/bin/sail artisan test`.
- Feature tests emphasize authorization, rate refresh, public tokens, email queueing, and print artifacts.
- Favor model factories and clear policy assertions to keep fixtures simple.
- Currency/rate expectations live in [`docs/RATES.md`](RATES.md) and must stay in sync with controller + service behavior.
- Upcoming coverage is drafted in [`docs/tests/TEST_HARDENING.md`](tests/TEST_HARDENING.md); implement those scenarios next.
- New feature work (partial payments UI, invoice delivery, etc.) should follow a TDD flow wherever practical, while existing areas continue to pick up pragmatic coverage as they evolve.

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
