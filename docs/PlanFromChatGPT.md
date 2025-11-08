# PROJECT PLAN — Bitcoin Invoice Generator
_Last updated: 2025-11-07_

## Overview
A small Laravel app to generate and share BTC invoices: create clients and invoices, compute BTC from cached USD→BTC rate, show BIP21 link + copy + server-side QR, public share page with token, and a printer-friendly view.

## Stack / Env
- Laravel 12 + PHP 8.4, Breeze, Tailwind UI
- Docker + Laravel Sail, MySQL
- Time: server America/Denver; Show page displays “as of” in viewer local time (client-side)

## Run rules
- Use Sail for all commands. No host PHP or host package installs.
- Keep changes small, outcome-focused PRs. CI is the gate.
- Tests live in `tests/Feature` and should remain stable and readable.

## Source of truth & branching
- GitHub is canonical.
- New tasks: `codex/<task>`.
- Existing PRs: push to the same source branch. Never invent local branches.

## CI gate
- GitHub Actions “PR Tests” on push to `codex/**`, `feat/**`, `feature/**` and on PR events.
- Pipeline: PHP 8.4 + MySQL → composer install → Vite build → migrate → `php artisan test`.
- `main` is protected: green CI + up-to-date required.

## Time handling
- Server default America/Denver.
- “As of” timestamps on Show are viewer-local via client script.

## Key paths
- Controllers: `app/Http/Controllers/{InvoiceController,ClientController}.php`
- Models: `app/Models/{Invoice,Client}.php`
- Services: `app/Services/BtcRate.php`
- Views: `resources/views/invoices/{index,create,edit,show,print}.blade.php`
- Routes: `routes/web.php`
- QR: server-side SVG via `simplesoftwareio/simple-qrcode`

## Current status
- Clients: full CRUD + soft delete + trash/restore + ownership checks.
- Invoices: list/create/edit/show; statuses draft/sent/paid/void; reset-to-draft; soft delete + trash/restore/force.
- Numbering: per-user auto-number.
- Rates: current rate buttons; live USD↔BTC calc; consistent cache key.
- Dates: `invoice_date` end-to-end.
- Payments UX: BIP21 link + Copy; Print view shows server-side SVG QR + “Thank you.”
- Public sharing: `/p/{token}` enable/disable/rotate/expiry; noindex; public view fetches fresh rate each time.
- R1/R1b: Show “Refresh rate” updates rate and “as of” with shared cache key.
- R2: Show computes BTC = amount_usd ÷ cached rate (non-persistent) and updates BIP21/QR.
- A1: Ownership via policies; friendly 403 page aligned with tests.
- **PR #13 merged**: 403 copy stabilized to satisfy tests.

## Roadmap to Release Candidate (code-first)
1) **Test hardening**
    - Add/clarify Feature tests for: public share enable/disable/rotate/expiry, noindex meta, Show “as of” behavior, QR present with correct BIP21, soft-delete visibility, and rate cache reuse across Show requests.
    - Prefer assertions on stable elements (headings/data-testids) over phrasing.
2) **Rate & currency correctness**
    - Document rounding rules and precision for BTC display and BIP21 amount.
    - Add guardrails for stale cache TTL and network failure fallbacks.
3) **Invoice delivery**
    - Email invoice: queued Mailable with signed public URL and optional PDF print attachment.
    - Basic send log on invoice (timestamp + to + status). Retries via queue.
4) **Print & public polish**
    - Print template spacing/contrast, fallback fonts, QR size tuning.
    - Public page: minimal brand header, “as of” note, disabled/expired states.
5) **User settings**
    - Address/BIP21 label preset, optional memo, default invoice terms.
6) **Observability & safety**
    - Structured logs on rate fetch, email send, and public access.
    - 403/404/500 templates consistent, no leaks.
7) **Docs & DX**
    - README quick start with Sail, test strategy, and environment vars.
    - `docs/PLAN.md` maintained by Codex on every meaningful PR merge.

## Testing approach
- Run under Sail. Feature tests cover authorization, rates, public token lifecycle, email queueing, and print artifacts presence.
- Keep fixtures simple; prefer factories and policies.

## Decisions & changelog
- 2025-11-07: PR #13 merges; 403 copy standardized to pass AuthorizationTest.
- 2025-11-07: Docker daemon access fixed via socket group mode on AL9.
- 2025-11-07: PLAN.md becomes Codex-maintained.
