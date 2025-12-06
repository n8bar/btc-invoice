# Onboarding Walkthrough

End-to-end guide from fresh clone to first paid invoice.

## 1) Start from Quick Start
- Follow [`docs/get-live/QUICK_START.md`](docs/get-live/QUICK_START.md) to bring up Sail, install deps, and migrate/seed.
- Ensure `WALLET_NETWORK` matches your test network and mail aliasing is enabled for safety in pre-prod.

## 2) Connect a Wallet
- Visit `/wallet/settings`.
- Paste a BIP84 xpub for the configured network (e.g., testnet4); use the derive test to confirm the sample address.
- Note the network badge (mainnet/testnet) and fix errors inline before saving.
- (Screenshot: wallet settings with network badge + derive test.)

## 3) Create an Invoice
- Go to Invoices → Create.
- Fill memo/terms (defaults are prefilled from Profile), set a USD amount, and observe the BTC conversion.
- Save; the invoice number and ownership are enforced automatically.
- (Screenshot: create form showing USD→BTC conversion.)

## 4) Enable Share & Deliver
- From the invoice show page, enable the public link (optional expiry), then click Deliver.
- Add an optional note and CC yourself; submit to queue the email and log the attempt.
- Check the delivery log on the show page for queued/sent status.
- (Screenshot: delivery form + delivery log row.)

## 5) View Public Page
- Open the public link; confirm QR/BIP21 and “rate as of” text render, with noindex headers applied.
- Print view mirrors the public layout and shows outstanding vs. paid state.
- (Screenshot: public view with QR and outstanding summary.)

## 6) Payment Visibility
- Scheduler container runs `php artisan schedule:work` and keeps `wallet:watch-payments` firing; you can also run it manually:
  ```bash
  ./vendor/bin/sail artisan wallet:watch-payments
  ```
- When a tx hits the derived address, the invoice shows partial/paid status, outstanding summaries update, and (when fully paid) a receipt email logs/sends.
- Partial-payment alerts remind clients to send one payment; owners get an FYI in the log.
- (Screenshot: invoice show with payment history/outstanding card.)

## 7) Wrap-up
- Keep `MAIL_ALIAS_ENABLED` on for non-prod to route mail to the catch-all; disable it before RC/production deploys.
- For test resets, rerun migrations/seeds:
  ```bash
  ./vendor/bin/sail artisan migrate:fresh --seed
  ```
