# Bitcoin Invoice Generator

This repository contains the CryptoZing Bitcoin invoicing app: a Laravel 12 + Sail stack for generating BTC invoices, locking USD amounts, tracking partial payments, and delivering invoices/receipts over email. Quick start instructions live in [`docs/ops/get-live/QUICK_START.md`](docs/ops/get-live/QUICK_START.md); current milestone status lives in [`docs/PLAN.md`](docs/PLAN.md), and global product rules live in [`docs/PRODUCT_SPEC.md`](docs/PRODUCT_SPEC.md).

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
- **Support access:** `SUPPORT_AGENT_EMAILS` defines the comma-separated support-account allowlist, and `SUPPORT_ACCESS_HOURS` defines the fixed owner-grant duration for temporary read-only support access.
- **Public links:** `APP_PUBLIC_URL` controls the base URL used in invoice emails/public share links. Set it per environment (localhost for dev, `https://cryptozing.app` for production).
- **Placeholder site:** The temporary GitHub Pages placeholder for `cryptozing.app` lives under `site/` and is intended to deploy separately from the Laravel app surface. For local review on the existing app host, it is exposed at `/site/index.html` via the tracked `public/site` alias.

## Documentation & Specs
- Plan: [`docs/PLAN.md`](docs/PLAN.md)
- Product spec: [`docs/PRODUCT_SPEC.md`](docs/PRODUCT_SPEC.md)
- Backlog: [`docs/BACKLOG.md`](docs/BACKLOG.md)
- Changelog: [`docs/CHANGELOG.log`](docs/CHANGELOG.log)
- UX guardrails reference: [`docs/UX_GUARDRAILS.md`](docs/UX_GUARDRAILS.md)
- Docs & DX ops: [`docs/ops/DOCS_DX.md`](docs/ops/DOCS_DX.md)
- Quick start: [`docs/ops/get-live/QUICK_START.md`](docs/ops/get-live/QUICK_START.md)
- Contributor walkthrough: [`docs/ops/get-live/CONTRIBUTOR_WALKTHROUGH.md`](docs/ops/get-live/CONTRIBUTOR_WALKTHROUGH.md)
- RC rollout checklist: [`docs/ops/RC_ROLLOUT_CHECKLIST.md`](docs/ops/RC_ROLLOUT_CHECKLIST.md)
- MS13 UX Overhaul milestone doc: [`docs/milestones/MS13_UX_OVERHAUL.md`](docs/milestones/MS13_UX_OVERHAUL.md)
- MS14 Payment Attribution Hardening milestone doc: [`docs/milestones/MS14_PAYMENT_ATTRIBUTION_HARDENING.md`](docs/milestones/MS14_PAYMENT_ATTRIBUTION_HARDENING.md)
- MS15 CryptoZing.app SEO Bootstrap milestone doc: [`docs/milestones/MS15_CRYPTOZING_APP_SEO_BOOTSTRAP.md`](docs/milestones/MS15_CRYPTOZING_APP_SEO_BOOTSTRAP.md)
- Onboarding wizard spec: [`docs/specs/ONBOARD_SPEC.md`](docs/specs/ONBOARD_SPEC.md)
- Wallet/Xpub UX spec: [`docs/specs/WALLET_XPUB_UX_SPEC.md`](docs/specs/WALLET_XPUB_UX_SPEC.md)
- Support access spec: [`docs/specs/SUPPORT_ACCESS.md`](docs/specs/SUPPORT_ACCESS.md)
- Print/Public polish spec: [`docs/specs/PRINT_PUBLIC_POLISH.md`](docs/specs/PRINT_PUBLIC_POLISH.md)
- Rate handling rules: [`docs/specs/RATES.md`](docs/specs/RATES.md)
- Partial payments, confirmations, and adjustments spec: [`docs/specs/PARTIAL_PAYMENTS.md`](docs/specs/PARTIAL_PAYMENTS.md)
- Payment correction / ignore-restore spec: [`docs/specs/PAYMENT_CORRECTIONS.md`](docs/specs/PAYMENT_CORRECTIONS.md)
- Notifications, delivery, and alerts spec: [`docs/specs/NOTIFICATIONS.md`](docs/specs/NOTIFICATIONS.md)
- Test hardening draft: [`docs/qa/tests/TEST_HARDENING.md`](docs/qa/tests/TEST_HARDENING.md)
- MS15 Phase 1 strategy: [`docs/strategies/MS15_PHASE1_DISCOVERY_INDEXING_BASELINE.md`](docs/strategies/MS15_PHASE1_DISCOVERY_INDEXING_BASELINE.md)
- Task 11 implementation strategy: [`docs/strategies/MS13_TASK11_GETTING_STARTED_STRATEGY.md`](docs/strategies/MS13_TASK11_GETTING_STARTED_STRATEGY.md)
- Task 11 UX Engineering pass: [`docs/strategies/MS13_TASK11_UX_ENGINEERING_PASS.md`](docs/strategies/MS13_TASK11_UX_ENGINEERING_PASS.md)
- Task 11 UX Engineering pass2: [`docs/strategies/MS13_TASK11_UX_ENGINEERING_PASS2.md`](docs/strategies/MS13_TASK11_UX_ENGINEERING_PASS2.md)

For coding conventions, workflow expectations, and per-environment reminders, see [`AGENTS.md`](AGENTS.md). Sail commands, migrations, and tests must run through `./vendor/bin/sail …`.
