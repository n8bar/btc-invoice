# PROJECT PLAN — Bitcoin Invoice Generator
_Last updated: 2025-11-07_

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

## Current Status (2025-11-07)
- Client CRUD with soft delete, trash/restore, ownership policies enforced.
- Invoice flow supports listing, creation, editing, show, status transitions (draft/sent/paid/void), reset-to-draft, soft delete + trash/restore/force delete.
- Auto-numbering per user.
- Rate management: current rate buttons, live USD↔BTC conversion, consistent cache key, “Refresh rate” updates on Show page.
- BTC computation on Show recalculates display amounts without persisting.
- Payment UX: BIP21 link + “Copy” + print view with server-side SVG QR and thank-you message.
- Public sharing: `/p/{token}` with enable/disable/rotate/expiry, `noindex`, fetches fresh rate each view.
- Friendly 403 page aligned with Authorization tests (PR #13 merged).

## Roadmap to Release Candidate
1. **Test Hardening**
   - Expand Feature coverage: public share lifecycle (enable/disable/rotate/expiry), noindex meta, Show “as of” behavior, QR presence/BIP21 accuracy, soft-delete visibility, rate cache reuse.
   - Prefer assertions on stable selectors/headings.
2. **Rate & Currency Correctness**
   - Document rounding rules for BTC display and BIP21 amounts.
   - Add safeguards for stale cache TTL + network fallback behavior.
3. **Invoice Delivery**
   - Implement queued Mailables for invoices (signed public URL, optional PDF print attachment).
   - Track send attempts per invoice (timestamp, recipient, status) with retry support.
4. **Print & Public Polish**
   - Improve print template spacing/contrast/fonts; tune QR sizing.
   - Public page: lightweight branding, “as of” note, clear disabled/expired states.
5. **User Settings**
   - Allow default BTC address/BIP21 label/memo + invoice terms.
6. **Observability & Safety**
   - Structured logs for rate fetches, emails, public access.
   - Ensure 403/404/500 templates are consistent and leak no sensitive data.
7. **Docs & DX**
   - README quick start (Sail), env var references, testing strategy.
   - Keep this plan updated by Codex on every meaningful merge.

## Testing Approach
- Execute suite via Sail: `./vendor/bin/sail artisan test`.
- Feature tests emphasize authorization, rate refresh, public tokens, email queueing, and print artifacts.
- Favor model factories and clear policy assertions to keep fixtures simple.
- Currency/rate expectations live in [`docs/RATES.md`](RATES.md) and must stay in sync with controller + service behavior.
- Upcoming coverage is drafted in [`docs/tests/TEST_HARDENING.md`](tests/TEST_HARDENING.md); implement those scenarios next.

## Decisions & Changelog
| Date (UTC) | Change | Notes |
|------------|--------|-------|
| 2025-11-07 | PR #13 merged | Friendly 403 copy standardized to satisfy Authorization tests. |
| 2025-11-07 | Docker daemon access adjusted | AL9 configured for Sail via socket group. |
| 2025-11-07 | PLAN.md established | Plan maintained by Codex; README links to doc. |
| 2025-11-07 | Added test hardening suites | Public share SEO, rate refresh caching, BIP21 output, and trash/restore flows covered by Sail tests. |
| 2025-11-07 | Documented rate precision | `docs/RATES.md` defines USD/BTC rounding and cache TTL rules. |
