# Bitcoin Invoice Generator

This repository contains the CryptoZing Bitcoin invoicing app: a Laravel 12 + Sail stack for generating BTC invoices, locking USD amounts, tracking partial payments, and delivering invoices/receipts over email. Quick start instructions live in [`docs/get-live/QUICK_START.md`](docs/get-live/QUICK_START.md); the delivery plan, roadmap, and branching policy live in [`docs/PLAN.md`](docs/PLAN.md).

## Highlights
- **BTC-native invoicing:** Create invoices with live BTC/USD conversions, BIP21 links, QR codes, print/public views, and strict ownership enforcement.
- **Wallet integration:** Each user configures a BIP84 xpub under `/wallet/settings`; invoices derive unique Bech32 addresses and the watcher (`wallet:watch-payments`) logs incoming payments.
- **Partial payment ledger:** Every transaction lands in `invoice_payments` with sats + USD snapshots, outstanding balance summaries, editable owner notes, and tip detection.
- **Invoice delivery + receipts:** Owners send invoices via email, see real-time delivery logs, and automatically email receipts when the watcher marks an invoice paid.
- **Catch-all friendly testing:** Outbound mail is routed through Mailgun and, while `MAIL_ALIAS_ENABLED=true`, every recipient rewrites to the Mailgun catch-all (`MAIL_ALIAS_DOMAIN`) so we can test safely.
- **Branding controls:** Set a default invoice heading plus billing name/contact/footer text in your profile and override them per invoice; public/print views (including public links) stay in sync automatically.
- **Invoice defaults & wallets:** Profile settings include memo/terms defaults so new invoices auto-fill description/due dates, and wallet settings can stash extra xpubs ahead of multi-wallet selection.

## Getting Started

### Prerequisites
- Docker + Docker Compose
- Node 20+ (for optional local Vite builds)
- Mailgun (or equivalent SMTP provider) for outbound email

### Initial setup
1. Copy the env template and customize credentials:
   ```bash
   cp .env.example .env
   ```
   Required keys:
   - `APP_URL` / `APP_PUBLIC_URL` – domain used in generated links (e.g., `https://cryptozing.app`).
   - `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_*`.
   - `MAIL_ALIAS_ENABLED=true` and `MAIL_ALIAS_DOMAIN=mailer.cryptozing.app` while routing everything to the Mailgun catch-all.
2. Install dependencies & start Sail:
   ```bash
   ./vendor/bin/sail up -d
   ./vendor/bin/sail composer install
   ./vendor/bin/sail npm install && ./vendor/bin/sail npm run build
   ```
3. Run migrations and seeders (includes demo user + wallet prompts):
   ```bash
   ./vendor/bin/sail artisan migrate --seed
   ```
4. Log in via `http://localhost` (or your hosts-mapped domain) using the seeded credentials, configure a wallet xpub under `/wallet/settings`, and you’re ready to issue invoices.

### Running tests
Execute the full suite via Sail:
```bash
./vendor/bin/sail artisan test
```

## Configuration Notes
- **Wallet watcher:** `./vendor/bin/sail artisan wallet:watch-payments` polls mempool.space and updates invoice/payment state. It runs automatically via the scheduler container once Sail is up.
- **Mail aliasing:** Keep `MAIL_ALIAS_ENABLED` on while the product is in pre-production so all outgoing mail lands in the Mailgun catch-all route. Disable it (or clear the domain) before the RC deploy so recipients get their real email addresses.
- **Public links:** `APP_PUBLIC_URL` controls the base URL used in invoice emails/public share links. Set it per environment (localhost for dev, `https://cryptozing.app` for production).

## Documentation & Specs
- Delivery plan & roadmap: [`docs/PLAN.md`](docs/PLAN.md)
- Future backlog: [`docs/FuturePLAN.md`](docs/FuturePLAN.md)
- Docs & DX spec: [`docs/DOCS_DX_SPEC.md`](docs/DOCS_DX_SPEC.md)
- Quick start: [`docs/get-live/QUICK_START.md`](docs/get-live/QUICK_START.md)
- Onboarding walkthrough: [`docs/get-live/ONBOARDING_WALKTHROUGH.md`](docs/get-live/ONBOARDING_WALKTHROUGH.md)
- RC rollout checklist: [`docs/RC_ROLLOUT_CHECKLIST.md`](docs/RC_ROLLOUT_CHECKLIST.md)
- UX Overhaul spec: [`docs/UX_OVERHAUL_SPEC.md`](docs/UX_OVERHAUL_SPEC.md)
- Onboarding wizard spec: [`docs/ONBOARD_SPEC.md`](docs/ONBOARD_SPEC.md)
- Wallet/Xpub UX spec: [`docs/WALLET_XPUB_UX_SPEC.md`](docs/WALLET_XPUB_UX_SPEC.md)
- UX guardrails reference: [`docs/UX_GUARDRAILS.md`](docs/UX_GUARDRAILS.md)
- Print/Public polish spec: [`docs/PRINT_PUBLIC_POLISH.md`](docs/PRINT_PUBLIC_POLISH.md)
- Rate handling rules: [`docs/RATES.md`](docs/RATES.md)
- Partial payments spec: [`docs/PARTIAL_PAYMENTS.md`](docs/PARTIAL_PAYMENTS.md)
- Invoice delivery spec: [`docs/INVOICE_DELIVERY.md`](docs/INVOICE_DELIVERY.md)
- Notification spec: [`docs/NOTIFICATIONS.md`](docs/NOTIFICATIONS.md)
- Test hardening draft: [`docs/tests/TEST_HARDENING.md`](docs/tests/TEST_HARDENING.md)

For coding conventions, workflow expectations, and per-environment reminders, see [`AGENTS.md`](AGENTS.md). Sail commands, migrations, and tests must run through `./vendor/bin/sail …`.
