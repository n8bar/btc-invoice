# AGENTS

## Working Style
- Always run artisan/composer/npm commands through Sail (`./vendor/bin/sail ...`).
- Keep `docs/PLAN.md` and `docs/FuturePLAN.md` in sync with every merge or scope change.
- Keep `docs/CHANGELOG.md` updated alongside PLAN when scope/decisions shift.
- When adding features, update or create migrations + tests, then run `./vendor/bin/sail artisan test`.
- Also keep AGENTS.md updated to save on churn from session switching.
- Sail Compose includes a dedicated `scheduler` service that runs `php artisan schedule:work`; `./vendor/bin/sail up -d` keeps the watcher alive automatically.
- Specs come first: align on the requirement in the spec docs, implement, then update the docs to reflect what shipped; only reverse-engineer specs from existing code when we’ve explicitly agreed to do so.
- If a request is highly ambiguous between “do it” vs. “explain it,” err on answering first and confirm before making changes; clear requests can be acted on directly.
- Before any push/PR, keep all docs in sync: update specs first when scope shifts, then code, and ensure everything under `docs/` (plus README links) reflects the same state in the same commit.

## Handy Commands
```
./vendor/bin/sail up -d
./vendor/bin/sail artisan test
./vendor/bin/sail artisan wallet:assign-invoice-addresses --dry-run
./vendor/bin/sail artisan wallet:watch-payments
```

## Environment Notes
- Wallet xpub onboarding lives at `/wallet/settings`; invoices expect a configured wallet or redirect there.
- Node helper for BTC derivation lives in `node_scripts/derive-address.cjs` and is invoked via `App\Services\HdWallet`.
- **Data hygiene:** As of 2025-11-16 the app only holds seed/test data—no real customers yet. Remove this note (and treat production emails accordingly) once live customer data exists.
- Email delivery currently rewrites recipients to the CryptoZing catch-all via `MAIL_ALIAS_ENABLED/MAIL_ALIAS_DOMAIN` (set to `mailer.cryptozing.app` so Mailgun routes everything to Proton). Disable the aliasing before RC or any real-customer deployment.
- The CryptoZing.app domain is reserved solely for this project; feel free to provision DNS/subdomains/mail for app needs without saving it for other products.
- Set `APP_PUBLIC_URL` to whatever domain should appear in public invoice links (localhost for dev, `https://cryptozing.app` for production) so emails never point at the wrong host.
- Keep the Sail stack (`./vendor/bin/sail up -d`) running during active work/testing unless there’s a clear reason to tear it down.
- Codex owns the terminal tooling: you drive Sail, git, and related commands—assume the user doesn’t have a shell open unless they say otherwise.
- Whenever `docs/**` changes, commit/push those updates right away (I review docs via GitHub’s UI).
- When you add or rename spec docs, update the README’s documentation section in the same commit so GitHub viewers always see the latest links.
