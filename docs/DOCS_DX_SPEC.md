# Docs & DX Spec (RC)

Item 12 clarification for Release Candidate: what we will ship for developer docs and onboarding quality.

## Outputs
- `docs/QUICK_START.md`: Sail-first quick start + env var reference for new contributors.
- `docs/ONBOARDING_WALKTHROUGH.md`: end-to-end walkthrough from clone → wallet setup → invoice creation → delivery → payment visibility (screenshots OK).
- `docs/NOTIFICATIONS.md`: add a status/coverage section that flags which emails are live vs. stubbed and points to their tests/logging.
- `README.md` and `docs/PLAN.md` updated to link the above and keep RC scope vs. FuturePLAN clear.

## Quick Start + Env Var Reference
Audience: new contributors running the stack locally with Sail.
- Include prerequisites (Docker/Compose, Node for Vite build, Mailgun or SMTP test credentials, optional browser extensions unnecessary).
- Steps: clone → `cp .env.example .env` → set `APP_URL`/`APP_PUBLIC_URL`, `MAIL_*`, `MAIL_ALIAS_ENABLED/MAIL_ALIAS_DOMAIN` (pre-prod) → `./vendor/bin/sail up -d` → `./vendor/bin/sail composer install` → `./vendor/bin/sail npm install && ./vendor/bin/sail npm run build` → `./vendor/bin/sail artisan migrate --seed`.
- Env reference: table of required keys for dev/test with short descriptions (public URL for links, mail aliasing notes, database defaults).
- Smoke check: confirm login with seeded user, visit `/wallet/settings`, create a test invoice, and render a public link without errors; remind that the scheduler container keeps `wallet:watch-payments` running.

## Onboarding Walkthrough
Goal: show the first successful invoice lifecycle with minimal branching.
- Start from a fresh clone + seeded DB (link to quick start).
- Configure wallet: add a testnet BIP84 xpub under `/wallet/settings`; note expected address format and derive test feedback.
- Create invoice: fill memo/terms defaults, enter USD amount, observe BTC conversion, and save.
- Deliver: enable/share public link, use the Deliver form (owner CC + optional note), confirm delivery log entry.
- Payment visibility: explain how the watcher marks payments (scheduler runs `wallet:watch-payments`; manual command if needed), where to see partials/outstanding in show/public views, and how receipts are triggered.
- Call out where screenshots belong (wallet settings, create invoice, delivery log, public view).

## Notification Coverage (tie-in to `docs/NOTIFICATIONS.md`)
- Add a matrix with columns: Audience, Trigger, Mailable/Job, Status (`live` / `stubbed` / `planned`), Tests (Feature test names/paths), Delivery log type(s).
- Must cover: paid notices (owner + client receipt), past-due (owner + client), overpayment alert (client + optional owner), underpayment alert (client + owner notice).
- Note any gaps and whether they are deferred to FuturePLAN; align copy guidelines with the spec.

## Definition of Done
- Quick start and onboarding walkthrough docs exist, are linked from README and PLAN, and match current UX/commands.
- Notification coverage is documented with live vs. stub status and test pointers in `docs/NOTIFICATIONS.md`.
- PLAN keeps RC scope for docs/DX; anything deferred is explicitly routed to `docs/FuturePLAN.md`.
