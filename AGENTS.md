# AGENTS

## Working Style
- Always run artisan/composer/npm commands through Sail (`./vendor/bin/sail ...`).
- Keep `docs/PLAN.md` and `docs/FuturePLAN.md` in sync with every merge or scope change.
- When adding features, update or create migrations + tests, then run `./vendor/bin/sail artisan test`.
- Also keep AGENTS.md updated to save on churn from session switching.

## Handy Commands
```
./vendor/bin/sail up -d
./vendor/bin/sail artisan test
./vendor/bin/sail artisan wallet:assign-invoice-addresses --dry-run
```

## Environment Notes
- Wallet xpub onboarding lives at `/wallet/settings`; invoices expect a configured wallet or redirect there.
- Node helper for BTC derivation lives in `node_scripts/derive-address.cjs` and is invoked via `App\Services\HdWallet`.
