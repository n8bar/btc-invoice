# RC Rollout Checklist (CryptoZing.app)

Use this when preparing the Release Candidate deployment; keep APP_PUBLIC_URL and mail settings aligned per environment.

## Pre-flight
- Confirm environment: APP_PUBLIC_URL set to intended host (e.g., https://cryptozing.app), WALLET_NETWORK matches network in use.
- Verify secrets: MAIL_* creds valid for production domain; queue/DB/cache endpoints reachable; env files up to date.

## Mail aliasing flip
- Set MAIL_ALIAS_ENABLED=false (and clear MAIL_ALIAS_DOMAIN if present).
- Send a test invoice email and a paid receipt to real recipients; verify links, headers, and rendering.
- Confirm DKIM/SPF/DMARC pass on the production domain.

## Database + migrations
- Take a backup/snapshot.
- Run `./vendor/bin/sail artisan migrate --force` (or equivalent in prod) and confirm no pending migrations remain.

## Application smoke
- Run basic smoke tests: dashboard loads, create invoice, enable share, view public link, send delivery, mark paid (or simulate via watcher/manual).
- Verify public links show correct APP_PUBLIC_URL and retain noindex headers.
- Confirm watcher/queue processes are running and logs are clean.

## Post-deploy checks
- Review logs/alerts for mail, watcher, and error rates.
- Spot-check invoices/clients for ownership/auth anomalies.
- Update CHANGELOG/PLAN with any scope or operational notes observed during rollout.
