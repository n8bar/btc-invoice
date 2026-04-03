# RC Rollout Checklist (CryptoZing.app)

Use this when preparing the Release Candidate deployment; keep APP_PUBLIC_URL and mail settings aligned per environment.

## Pre-flight
- Confirm environment: APP_PUBLIC_URL set to intended host (e.g., https://cryptozing.app), WALLET_NETWORK matches network in use.
- Verify secrets: MAIL_* creds valid for production domain; MAILGUN_WEBHOOK_SIGNING_KEY set to the signing key from the Mailgun dashboard (Webhooks section); queue/DB/cache endpoints reachable; env files up to date.
- Current Mailgun sending-domain assumption is US-region `mailer.cryptozing.app`, so the matching endpoint is `MAILGUN_ENDPOINT=api.mailgun.net` unless the provider region changes later.

## Mail aliasing flip
- Set MAIL_ALIAS_ENABLED=false (and clear MAIL_ALIAS_DOMAIN if present).
- MS16 Phase 2 already proved the app can deliver through Mailgun HTTP API with a temporary alias-off send to controlled dev inboxes on 2026-03-28; treat that as transport proof only, not as the RC sign-off for the target environment.
- Send a test invoice email and a paid receipt to real recipients; verify links, headers, and rendering.
- Register the Mailgun webhook in the Mailgun dashboard: POST `https://cryptozing.app/webhooks/mailgun` for `delivered`, `failed`, and `permanent_fail` event types. Copy the signing key to `MAILGUN_WEBHOOK_SIGNING_KEY`.
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
