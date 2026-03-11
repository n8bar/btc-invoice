# AGENTS

## Working Style
- Always run artisan/composer/npm commands through Sail (`./vendor/bin/sail ...`).
- Keep canonical docs in sync with every merge or scope change:
  - `docs/ROADMAP.md` for RC milestone order/status/dependencies
  - `docs/PRODUCT_SPEC.md` for global product behavior and invariants
  - `docs/BACKLOG.md` for post-MVP and deferred work only
- Keep `docs/CHANGELOG.md` updated alongside canonical docs when scope or doc structure shifts.
- Where dates are necessary in docs, use the date from the system you're running on.
- When adding features, update or create migrations + tests, then run `./vendor/bin/sail artisan test`.
- Also keep AGENTS.md updated to save on churn from session switching.
- Keep `.cybercreek/` local-only and untracked; do not commit agent coordination logs or local recovery files.
- Sail Compose includes a dedicated `scheduler` service that runs `php artisan schedule:work`; `./vendor/bin/sail up -d` keeps the watcher alive automatically.
- Specs come first: align on the requirement in the spec docs, implement, then update the docs to reflect what shipped; only reverse-engineer specs from existing code when we’ve explicitly agreed to do so.
- Docs are primarily internal architecture/engineering notes for us and future maintainers, not end-user documentation.
- Strategy docs (implementation approaches / execution notes, e.g. `docs/strategies/**`) are advisory working docs, not canonical like specs/`docs/ROADMAP.md`/`docs/PRODUCT_SPEC.md`/`docs/BACKLOG.md`/`docs/CHANGELOG.md`; they may be deleted after implementation if they’re no longer useful.
- Any doc with numbered tasks/milestones/todos is assumed to be done in order unless that doc explicitly says otherwise—flag any intentional deviations.
- If the user is asking for your input/feedback (e.g. “what do you think?”, “should we…?”, “does this make sense?”), answer first and confirm before making changes—even if the request sounds actionable.
- If asked to implement code before a spec exists, pause to confirm and recommend documenting the scope first (write the spec, then ship the code) unless the user explicitly insists otherwise.
- Before any push/PR, keep all docs in sync: update specs first when scope shifts, then code, and ensure everything under `docs/` (plus README links) reflects the same state in the same commit.
- Whenever `docs/**` or AGENTS.md changes, commit/push those updates right away, except single-item checklist checkoffs which may be batched and committed together later in the same active workstream.
- Apply the UX guardrails in [`docs/UX_GUARDRAILS.md`](docs/UX_GUARDRAILS.md) on every UX touch: Nielsen/WCAG as baseline; inline guidance, preserved input, no layout shift, focus/error handling, mobile/accessibility.
- GitHub `main` is canonical and protected. New work branches follow `codex/<task>`, and existing PRs must be updated via their original source branch rather than alternate branches.
- PRs are gated by GitHub Actions `PR Tests`; keep branches current with `origin/main` before requesting review.

## Multi-Agent Coordination
- Primary and secondary agents are role-based, not capability-limited: secondaries can work docs, code, tests, or modules within their stated task.
- Expect a dirty worktree during multi-agent sessions; do not stop for unrelated file changes outside your scoped paths.
- Pause only when unexpected changes appear in the same file you need to edit, or when a destructive/revert action would be required.
- Use path-scoped staging/commits (`git add <paths>`) so unrelated agent work is never swept into your commit.
- Keep agent coordination logs local-only and untracked; use `Agents.comm` in the untracked `.cybercreek/` area for agent-to-agent notes/checkouts/checkins.
- Checkout/checkin in `Agents.comm` is optional but recommended for high-conflict files; include agent, file paths, purpose, and lease/expiry so stale claims are obvious.
- If you checkout files in `Agents.comm`, check them back in before any wait state (before asking the user a clarifying question, when blocked/waiting, before switching tasks, and before ending your session).
- On checkin, leave a short handoff note: what changed, what remains, and any risks/tests to run.

## Handy Commands
```
./vendor/bin/sail up -d
./vendor/bin/sail artisan test
./vendor/bin/sail artisan wallet:assign-invoice-addresses --dry-run
./vendor/bin/sail artisan wallet:watch-payments
```

## Environment Notes (Do these without having to be reminded)
- Wallet xpub onboarding lives at `/wallet/settings`; invoices expect a configured wallet or redirect there.
- Node helper for BTC derivation lives in `node_scripts/derive-address.cjs` and is invoked via `App\Services\HdWallet`.
- **Data hygiene:** As of 2025-11-16 the app only holds seed/test data—no real customers yet. Remove this note (and treat production emails accordingly) once live customer data exists.
- Email delivery currently rewrites recipients to the CryptoZing catch-all via `MAIL_ALIAS_ENABLED/MAIL_ALIAS_DOMAIN` (set to `mailer.cryptozing.app` so Mailgun routes everything to Proton). Disable the aliasing before RC or any real-customer deployment.
- The CryptoZing.app domain is reserved solely for this project; feel free to provision DNS/subdomains/mail for app needs without saving it for other products.
- Set `APP_PUBLIC_URL` to whatever domain should appear in public invoice links (localhost for dev, `https://cryptozing.app` for production) so emails never point at the wrong host.
- Keep the Sail stack (`./vendor/bin/sail up -d`) running during active work/testing unless there’s a clear reason to tear it down.
- Codex owns the terminal tooling: you drive Sail, git, and related commands—assume the user doesn’t have a shell open unless they say otherwise.
- Whenever `docs/**` changes, commit/push those updates right away, except single-item checklist checkoffs which may be batched and committed together later in the same active workstream.
- When you add or rename spec docs, update the README’s documentation section in the same commit so GitHub viewers always see the latest links.

## Roles
- **Harvey (Devil’s Advocate Progress Reporter):** virtual stakeholder who is skeptical but honest; invoked when we need a harsh readout. Focuses only on risk/gaps of “done” items, not future scope; calls out missing verification, operational proof, and doc drift. Keep tone blunt but actionable.
