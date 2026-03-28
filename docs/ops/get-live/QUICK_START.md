# Quick Start (Sail-first)

Fast path to run the app locally with Laravel Sail.

## Prerequisites
- Docker + Docker Compose
- Node 20+ (for Vite build)
- Mailgun API credentials (pre-prod can use aliasing)

## Setup
1. Copy env template and set required keys:
   ```bash
   cp .env.example .env
   ```
   - `APP_URL` / `APP_PUBLIC_URL` for links (localhost in dev).
   - Mail: `MAIL_MAILER=mailgun`, `MAILGUN_DOMAIN`, `MAILGUN_SECRET`, optional `MAILGUN_ENDPOINT`, and `MAIL_FROM_*`.
   - Mail safety: `MAIL_OUTBOUND_ENABLED`, `MAIL_MANUAL_SEND_COOLDOWN_MINUTES`, `MAIL_ALERT_COOLDOWN_MINUTES`.
   - Aliasing for pre-prod safety: `MAIL_ALIAS_ENABLED=true`, `MAIL_ALIAS_DOMAIN=mailer.cryptozing.app`.
   - Wallet network: `WALLET_NETWORK=mainnet` (real payments) or `testnet4`/`testnet3` (testing; matches the watcher’s mempool endpoint).
2. Start Sail and install dependencies:
   ```bash
   ./vendor/bin/sail up -d
   ./vendor/bin/sail composer install
   ./vendor/bin/sail npm install
   ./vendor/bin/sail npm run build
   ```
3. Migrate and seed (demo user + prompts):
   ```bash
   ./vendor/bin/sail artisan migrate --seed
   ```
4. Log in at `http://localhost`, visit `/wallet/settings`, and add a BIP84 xpub to derive invoice addresses.

## Smoke Check
- Create a client and invoice; confirm USD→BTC conversion renders.
- Enable the public link and view it; QR/BIP21 should show.
- `./vendor/bin/sail artisan wallet:watch-payments` (already run by the scheduler container) can be run manually if you want to see watcher logs.

## Env Vars (reference)
| Key | Purpose | Typical dev value |
| --- | --- | --- |
| APP_URL / APP_PUBLIC_URL | Base URLs for app/public links | http://localhost |
| WALLET_NETWORK | Derivation + mempool network | mainnet / testnet4 / testnet3 |
| MAIL_MAILER | Default transport | mailgun |
| MAILGUN_DOMAIN / MAILGUN_SECRET / MAILGUN_ENDPOINT | Mailgun HTTP API settings | sandbox domain / key / api.mailgun.net |
| MAIL_FROM_ADDRESS / MAIL_FROM_NAME | Default sender | no-reply@cryptozing.app / CryptoZing |
| MAIL_OUTBOUND_ENABLED | Emergency outbound-mail circuit breaker | true |
| MAIL_MANUAL_SEND_COOLDOWN_MINUTES | Manual invoice-send cooldown | 60 |
| MAIL_ALERT_COOLDOWN_MINUTES | Automated alert cooldown | 1440 |
| MAIL_ALIAS_ENABLED / MAIL_ALIAS_DOMAIN | Catch-all rewrite during pre-prod | true / mailer.cryptozing.app |
| DB_DATABASE / DB_USERNAME / DB_PASSWORD | Sail MySQL | from .env defaults |

## Testing
Run the suite via Sail:
```bash
./vendor/bin/sail artisan test
```

## Notes
- Sail stack includes `laravel.test` (app/web), `scheduler` (runs `php artisan schedule:work`), and `mysql`.
- Keep `MAIL_ALIAS_ENABLED` on until production traffic; disable it before RC deploys so real recipients are used.
