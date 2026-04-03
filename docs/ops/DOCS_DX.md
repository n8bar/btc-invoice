# Docs & DX Ops (RC)

Release Candidate contributor-docs and documentation-operations scope.

## Outputs
- `docs/ops/get-live/QUICK_START.md`: Sail-first quick start + env var reference for new contributors.
- `docs/ops/get-live/CONTRIBUTOR_WALKTHROUGH.md`: end-to-end contributor walkthrough from clone → wallet setup → invoice creation → delivery → payment visibility (screenshots OK).
- `docs/specs/POST_PAYMENT_ONBOARDING.md`: small Part 2 onboarding spec for the first reviewed client receipt and its receipt-review blockers.
- `docs/specs/NOTIFICATIONS.md`: add a status/coverage section that flags which emails are live vs. stubbed and points to their tests/logging.
- `README.md`, `docs/PLAN.md`, `docs/PRODUCT_SPEC.md`, and `docs/BACKLOG.md` updated to link the above and keep RC scope separate from post-MVP backlog.

## Quick Start + Env Var Reference
Audience: new contributors running the stack locally with Sail.
- Include prerequisites (Docker/Compose, Node for Vite build, Mailgun API test credentials, optional browser extensions unnecessary).
- Steps: clone → `cp .env.example .env` → set `APP_URL`/`APP_PUBLIC_URL`, `MAIL_*`, `MAIL_ALIAS_ENABLED/MAIL_ALIAS_DOMAIN` (pre-prod) → `./vendor/bin/sail up -d` → `./vendor/bin/sail composer install` → `./vendor/bin/sail npm install && ./vendor/bin/sail npm run build` → `./vendor/bin/sail artisan migrate --seed`.
- Env reference: table of required keys for dev/test with short descriptions (public URL for links, mail aliasing notes, database defaults).
- Smoke check: confirm login with seeded user, visit `/wallet/settings`, create a test invoice, and render a public link without errors; remind that the scheduler container keeps `wallet:watch-payments` running.

## Contributor Walkthrough
Goal: show the first successful invoice lifecycle with minimal branching.
- Start from a fresh clone + seeded DB (link to quick start).
- Configure wallet: add a testnet BIP84 xpub under `/wallet/settings`; note expected address format and derive test feedback.
- Create invoice: fill memo/terms defaults, enter USD amount, observe BTC conversion, and save.
- Deliver: enable/share public link, use the Deliver form (owner CC + optional note), confirm delivery log entry.
- Payment visibility: explain how the watcher marks payments (scheduler runs `wallet:watch-payments`; manual command if needed), where to see partials/outstanding in show/public views, and how receipts are triggered.
- Call out where screenshots belong (wallet settings, create invoice, delivery log, public view).

## Notification Coverage (tie-in to `docs/specs/NOTIFICATIONS.md`)
- Add a matrix with columns: Audience, Trigger, Mailable/Job, Status (`live` / `stubbed` / `planned`), Tests (Feature test names/paths), Delivery log type(s).
- Must cover: paid notices (owner + client receipt), past-due (owner + client), overpayment alert (client + optional owner), underpayment alert (client + owner notice).
- Note any gaps and whether they are deferred to `docs/BACKLOG.md`; align copy guidelines with the spec.

## Post-Payment Onboarding (early MS17)
Goal: make the first owner-reviewed client receipt feel intentional without turning RC into a broad operator-training project.
- Keep onboarding Part 2 mostly invisible until a first paid invoice is actually receipt-eligible.
- Teach the owner-reviewed receipt step, the acknowledgment-versus-receipt distinction, and the fact that correction work should happen before any untruthful receipt.
- Use lightweight contextual guidance and existing review surfaces rather than fake invoices, scenario simulation, or a dedicated tutorial sandbox.
- If the first paid invoice needs ignore or reattribution work before a truthful receipt can be sent, suspend the Part 2 prompt and point the owner at the correction surface instead.

## Off-Chain Payment Recording
Goal: allow the issuer to record a payment received outside the Bitcoin network (wire transfer, cash, check, etc.) so the invoice ledger stays accurate without requiring a voided and recreated invoice.

- Add an owner-facing "Record manual payment" form on the invoice page, alongside existing adjustment tools.
- Fields: amount (USD), optional reference/note (e.g. "Wire transfer ref #12345"), date received.
- On save: create an `InvoicePayment` record flagged as manual (no on-chain txid), recalculate the payment ledger, and transition the invoice to `paid` if the balance is fully closed.
- Manual payment rows must be clearly distinguishable from on-chain payments in the payment history UI.
- The existing small-balance resolver and reattribution tools remain unchanged.
- If a manual payment closes the balance, the receipt panel should become available as with any other paid state.
- Do not allow manual payments on voided invoices.

## Definition of Done
- Quick start and contributor walkthrough docs exist, are linked from README and `docs/PLAN.md`, and match current UX/commands.
- Post-payment onboarding scope is documented and linked from the canonical spec docs, with MS17 treating it as early activation/help UX rather than a separate milestone.
- Notification coverage is documented with live vs. stub status and test pointers in `docs/specs/NOTIFICATIONS.md`.
- `docs/PLAN.md` keeps RC scope for Docs & DX; anything deferred is explicitly routed to `docs/BACKLOG.md`.
